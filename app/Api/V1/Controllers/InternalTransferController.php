<?php

namespace App\Api\V1\Controllers;

use App\Agent;
use App\Country;
use App\Http\Controllers\Controller;
use App\Inbound;
use App\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Transferts internes tholadpay — sans Peex ni DigitWace (voir
 * OutboundController::resolvePartner, partenaire 'internal', corridor_id=3).
 *
 * Principe (voir migration add_internal_transfer_fields) : l'agent
 * expéditeur envoie via le même formulaire/workflow de validation que pour
 * Peex/DigitWace ; si "Interne" est choisi à la validation, aucun appel
 * externe n'est fait — send_internal_transaction() génère juste un code de
 * retrait aléatoire (distinct de `ranking`, qui est prévisible et donc
 * impropre à servir de secret). Le bénéficiaire retire ensuite en espèces
 * chez N'IMPORTE QUEL agent tholadpay du pays destinataire, en présentant ce
 * code + une pièce d'identité (lookup_internal_transaction puis
 * payout_internal_transaction). L'agent payeur est crédité du montant
 * décaissé ; le règlement entre agences se fait hors application.
 *
 * @group InternalTransfer
 */
class InternalTransferController extends Controller
{
    /**
     * Étape "envoi" côté validation (admin/mobile) quand le partenaire choisi
     * est 'internal' — miroir de send_transaction/send_bank_transaction/
     * send_cash_transaction (OutboundController), mais sans aucun appel HTTP
     * externe. Ne persiste rien elle-même : l'appelant (admin) fait le PUT
     * transactions/{id} habituel avec le code renvoyé ici, exactement comme
     * pour Peex/DigitWace (voir TransactionController::sendtransaction()).
     */
    public function send_internal_transaction(Request $request)
    {
        try {
            $code = $this->generateUniquePickupCode();
        } catch (\Exception $e) {
            Log::error('[InternalTransfer send] ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }

        return response()->json([
            'status' => 200,
            'track_id' => $code,
            'reference' => $code,
            'pickup_code' => $code,
            'message' => 'Code de retrait interne généré.',
        ]);
    }

    /**
     * Recherche d'un transfert interne par code de retrait.
     *
     * AJOUT (2026-08-08) : utilisé par DEUX parcours désormais — l'agent payeur
     * AVANT de confirmer le paiement (agent_id fourni, avertissement de pays
     * possible), ET le bénéficiaire lui-même depuis un écran public sans
     * connexion (voir routes/api.php : cette route est volontairement HORS
     * jwt.auth, le code de retrait sert lui-même de secret). D'où l'ajout des
     * informations expéditeur/pays de provenance ci-dessous, utiles aux deux
     * mais indispensables pour que le bénéficiaire puisse vérifier qu'il s'agit
     * bien du bon envoi avant de se déplacer en agence.
     */
    public function lookup_internal_transaction(Request $request)
    {
        $code = strtoupper(trim((string) $request->get('pickup_code')));
        if (!$code) {
            return response()->json(['status' => 422, 'message' => 'pickup_code est requis'], 422);
        }

        $transaction = Transaction::with(['user', 'agent', 'agent.country'])
            ->where('internal_pickup_code', $code)->first();
        if (!$transaction) {
            return response()->json(['status' => 404, 'message' => 'Aucun transfert ne correspond à ce code.'], 404);
        }
        if ((string) $transaction->corridor_id !== '3') {
            return response()->json(['status' => 422, 'message' => 'Ce code ne correspond pas à un transfert interne.'], 422);
        }
        // NB : on garde le rejet immédiat (409) plutôt qu'un simple champ
        // already_paid — préserve le comportement existant côté agent
        // (admin/mobile affichent déjà "Ce transfert a déjà été payé." dès la
        // recherche, sans laisser accéder à l'étape de confirmation pour rien).
        if ($this->isAlreadyPaid($transaction)) {
            return response()->json(['status' => 409, 'message' => 'Ce transfert a déjà été payé.'], 409);
        }

        $warning = $this->countryMismatchWarning($request->get('agent_id'), $transaction);
        $sendingAgent = $transaction->agent;
        $sender = $transaction->user;

        return response()->json([
            'status' => 200,
            'warning' => $warning,
            'transaction' => [
                'id' => $transaction->id,
                'ranking' => $transaction->ranking,
                'amount_to_pay' => $transaction->montant_beneficiaire,
                'currency' => $transaction->to_currency,
                'receiving_country' => $transaction->receiving_country,
                'receiving_country_code' => $transaction->receiving_country_code,
                'beneficiary_first_name' => $transaction->recipient_first_name,
                'beneficiary_last_name' => $transaction->recipient_last_name,
                'beneficiary_phone' => $transaction->recipient_phone,
                // AJOUT (2026-08-08) : informations expéditeur / provenance —
                // demande utilisateur du 2026-08-08 ("affiche aussi les
                // informations de l'expéditeur, le pays de provenance et
                // autres détails").
                'sender_first_name' => $sender->first_name ?? null,
                'sender_last_name' => $sender->last_name ?? null,
                'sender_phone' => $sender->phone_number ?? null,
                'sending_country' => $sendingAgent->country->name ?? null,
                'sending_agency' => $sendingAgent->nom_commercial ?? null,
                'amount_sent' => $transaction->amount,
                'sent_currency' => $transaction->from_currency,
                'sent_at' => $transaction->date_init ?? $transaction->created_at,
                'transaction_reason' => $transaction->transaction_reason,
            ],
        ]);
    }

    /**
     * Confirme le paiement : enregistre l'Inbound (qui/quand/pièce d'identité),
     * marque la transaction payée, crédite l'agent payeur. Verrouillage
     * (lockForUpdate + transaction SQL) pour empêcher qu'un même code soit payé
     * deux fois par deux agents en même temps (double paiement).
     */
    public function payout_internal_transaction(Request $request)
    {
        $code = strtoupper(trim((string) $request->get('pickup_code')));
        $agentId = $request->get('agent_id');
        $idNumber = trim((string) $request->get('payout_id_number'));
        $idType = $request->get('payout_id_type') ?: 'CNI';

        if (!$code || !$agentId || !$idNumber) {
            return response()->json(['status' => 422, 'message' => 'pickup_code, agent_id et payout_id_number sont requis'], 422);
        }

        $agent = Agent::find($agentId);
        if (!$agent) {
            return response()->json(['status' => 422, 'message' => 'Agent payeur introuvable.'], 422);
        }

        try {
            $transaction = DB::transaction(function () use ($code, $agent, $idNumber, $idType) {
                $tx = Transaction::where('internal_pickup_code', $code)->lockForUpdate()->first();
                if (!$tx) {
                    throw new \RuntimeException('Aucun transfert ne correspond à ce code.');
                }
                if ((string) $tx->corridor_id !== '3') {
                    throw new \RuntimeException('Ce code ne correspond pas à un transfert interne.');
                }
                if ($this->isAlreadyPaid($tx)) {
                    throw new \RuntimeException('Ce transfert a déjà été payé.');
                }

                Inbound::create([
                    'remitance_purpose' => 'Retrait interne',
                    'description' => 'Payé par agent #' . $agent->id . ' (' . $agent->nom_commercial . ')',
                    'transaction_id' => $tx->id,
                    'paying_agent_id' => $agent->id,
                    'paid_at' => now(),
                    'payout_id_number' => $idNumber,
                    'payout_id_type' => $idType,
                ]);

                $tx->etat_transac = 'success';
                $tx->date_complete = @date('Y-m-d H:i:s');
                $tx->save();

                // AJOUT (2026-08-08, décision utilisateur) : l'agent payeur est
                // CRÉDITÉ du montant qu'il vient de décaisser de sa propre caisse —
                // ce montant représente ce que la plateforme lui doit, à régler hors
                // application. Rien n'est débité côté agent expéditeur ici : il a déjà
                // été débité normalement (amount + fees) au moment de l'envoi.
                $agent->solde = floatval($agent->solde) + floatval($tx->montant_beneficiaire);
                $agent->save();

                return $tx;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 409, 'message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            Log::error('[InternalTransfer payout] ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Erreur inattendue lors du paiement.'], 500);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Paiement enregistré avec succès.',
            'ranking' => $transaction->ranking,
            'amount_paid' => $transaction->montant_beneficiaire,
        ]);
    }

    private function isAlreadyPaid(Transaction $transaction): bool
    {
        if ($transaction->etat_transac === 'success') {
            return true;
        }
        return Inbound::where('transaction_id', $transaction->id)->whereNotNull('paid_at')->exists();
    }

    /**
     * Avertissement non-bloquant (voir décision utilisateur : n'importe quel
     * agent du pays destinataire peut payer) — signale juste le cas où l'agent
     * payeur est enregistré dans un pays différent, sans jamais empêcher le
     * paiement (utile aussi pour les agents existants sans country_id renseigné,
     * qui ne déclenchent jamais cet avertissement).
     */
    private function countryMismatchWarning($agentId, Transaction $transaction): ?string
    {
        if (!$agentId) {
            return null;
        }
        $agent = Agent::find($agentId);
        if (!$agent || !$agent->country_id) {
            return null;
        }
        $country = Country::find($agent->country_id);
        if (!$country) {
            return null;
        }
        $agentIso = strtoupper((string) ($country->iso_3166_2 ?? ''));
        $txIso = strtoupper((string) ($transaction->receiving_country_code ?? ''));
        if ($agentIso && $txIso && $agentIso !== $txIso) {
            return "Attention : votre agence est enregistrée dans un pays différent du pays destinataire (" . $transaction->receiving_country . ").";
        }
        return null;
    }

    /**
     * Code de retrait aléatoire — alphabet volontairement privé des caractères
     * ambigus à l'oral/à l'écrit (0/O, 1/I/L) pour limiter les erreurs de
     * transcription par un bénéficiaire qui le lit au téléphone à un agent.
     */
    private function generateUniquePickupCode(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        for ($attempt = 0; $attempt < 15; $attempt++) {
            $raw = '';
            for ($i = 0; $i < 8; $i++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = substr($raw, 0, 4) . '-' . substr($raw, 4);
            if (!Transaction::where('internal_pickup_code', $code)->exists()) {
                return $code;
            }
        }
        throw new \RuntimeException('Impossible de générer un code de retrait unique après plusieurs tentatives.');
    }
}
