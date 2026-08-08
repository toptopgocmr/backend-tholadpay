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
        if ($this->isRejected($transaction)) {
            return response()->json(['status' => 409, 'message' => 'Ce transfert a été rejeté (motif : '
                . $this->rejectionReasonLabel($transaction->rejection_reason) . ').'], 409);
        }

        $agentId = $request->get('agent_id');
        // AJOUT (2026-08-08, demande utilisateur) : une agence qui a été
        // EXPÉDITRICE d'un transfert ne peut pas également en effectuer le
        // retrait — évite qu'une agence encaisse l'argent qu'elle vient
        // elle-même d'envoyer (contrôle anti-fraude). Uniquement quand
        // agent_id est fourni (donc un agent, pas le bénéficiaire public).
        if ($this->isSameSendingAgency($agentId, $transaction)) {
            return response()->json(['status' => 409, 'message' => 'Votre agence est l\'expéditrice de ce transfert : elle ne peut pas également en effectuer le retrait. Merci de faire payer ce retrait par une autre agence.'], 409);
        }
        $warning = $this->countryMismatchWarning($agentId, $transaction);
        $sendingAgent = $transaction->agent;
        $sender = $transaction->user;

        // AJOUT (2026-08-08) : quand cette route est appelée SANS agent_id, c'est
        // le bénéficiaire lui-même depuis l'écran public (SuiviRetraitPage), pas un
        // agent. On horodate ce "check-in" pour que l'écran "Retrait interne" côté
        // agent puisse lister les transferts déjà consultés par leur bénéficiaire,
        // au lieu d'obliger l'agent à ressaisir le code à la main (demande
        // utilisateur : "le bénéficiaire saisit le code... l'agent pourra juste
        // cliquer sur vérifier ou rechercher depuis son interface").
        if (!$agentId && !$transaction->beneficiary_checked_in_at) {
            $transaction->beneficiary_checked_in_at = now();
            $transaction->save();
        }

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
                if ($this->isRejected($tx)) {
                    throw new \RuntimeException('Ce transfert a été rejeté (motif : '
                        . $this->rejectionReasonLabel($tx->rejection_reason) . '), impossible de le payer.');
                }
                // AJOUT (2026-08-08, demande utilisateur) : voir lookup_internal_transaction —
                // même contrôle ici en défense en profondeur (au cas où ce endpoint serait
                // appelé directement sans passer par lookup_internal_transaction).
                if ($this->isSameSendingAgency($agent->id, $tx)) {
                    throw new \RuntimeException('Votre agence est l\'expéditrice de ce transfert : elle ne peut pas également en effectuer le retrait. Merci de faire payer ce retrait par une autre agence.');
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

    /**
     * Rejet d'un retrait interne par l'agent payeur — demande utilisateur du
     * 2026-08-08 : "donner la possibilité d'annuler ou rejeter [un retrait
     * interne] en donnant la raison du rejet (nom pas conforme, identité, ou
     * autre raison valable)". Même verrouillage que payout_internal_transaction
     * pour éviter qu'un transfert soit payé et rejeté en même temps par deux
     * agents.
     *
     * NB (décision par défaut) : AUCUN montant n'est déplacé automatiquement —
     * cohérent avec le reste du règlement entre agences pour les transferts
     * internes, qui se fait hors application. Si un remboursement automatique
     * de l'agence expéditrice est souhaité, le dire pour l'ajouter.
     */
    public function reject_internal_transaction(Request $request)
    {
        $code = strtoupper(trim((string) $request->get('pickup_code')));
        $agentId = $request->get('agent_id');
        $reason = $request->get('rejection_reason');
        $note = trim((string) $request->get('rejection_note'));
        $validReasons = array_keys(self::REJECTION_REASONS);

        if (!$code || !$agentId || !$reason) {
            return response()->json(['status' => 422, 'message' => 'pickup_code, agent_id et rejection_reason sont requis'], 422);
        }
        if (!in_array($reason, $validReasons, true)) {
            return response()->json(['status' => 422, 'message' => 'Motif de rejet invalide.'], 422);
        }
        if ($reason === 'other' && !$note) {
            return response()->json(['status' => 422, 'message' => 'Merci de préciser le motif du rejet.'], 422);
        }

        $agent = Agent::find($agentId);
        if (!$agent) {
            return response()->json(['status' => 422, 'message' => 'Agent introuvable.'], 422);
        }

        try {
            $transaction = DB::transaction(function () use ($code, $agent, $reason, $note) {
                $tx = Transaction::where('internal_pickup_code', $code)->lockForUpdate()->first();
                if (!$tx) {
                    throw new \RuntimeException('Aucun transfert ne correspond à ce code.');
                }
                if ((string) $tx->corridor_id !== '3') {
                    throw new \RuntimeException('Ce code ne correspond pas à un transfert interne.');
                }
                if ($this->isAlreadyPaid($tx)) {
                    throw new \RuntimeException('Ce transfert a déjà été payé, impossible de le rejeter.');
                }
                if ($this->isRejected($tx)) {
                    throw new \RuntimeException('Ce transfert a déjà été rejeté.');
                }

                $tx->etat_transac = 'Rejected';
                $tx->rejection_reason = $reason;
                $tx->rejection_note = $note ?: null;
                $tx->rejected_by_agent_id = $agent->id;
                $tx->rejected_at = now();
                $tx->save();

                return $tx;
            });
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 409, 'message' => $e->getMessage()], 409);
        } catch (\Exception $e) {
            Log::error('[InternalTransfer reject] ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => 'Erreur inattendue lors du rejet.'], 500);
        }

        return response()->json([
            'status' => 200,
            'message' => 'Retrait rejeté (motif : ' . $this->rejectionReasonLabel($reason) . ').',
            'ranking' => $transaction->ranking,
        ]);
    }

    // Catalogue des motifs de rejet — demande utilisateur du 2026-08-08. 'other'
    // exige une note libre (voir reject_internal_transaction ci-dessus).
    const REJECTION_REASONS = [
        'name_mismatch' => "Nom du bénéficiaire non conforme",
        'invalid_id' => "Pièce d'identité invalide ou non conforme",
        'other' => 'Autre motif',
    ];

    private function rejectionReasonLabel($reason): string
    {
        return self::REJECTION_REASONS[$reason] ?? (string) $reason;
    }

    private function isAlreadyPaid(Transaction $transaction): bool
    {
        if ($transaction->etat_transac === 'success') {
            return true;
        }
        return Inbound::where('transaction_id', $transaction->id)->whereNotNull('paid_at')->exists();
    }

    private function isRejected(Transaction $transaction): bool
    {
        return $transaction->etat_transac === 'Rejected' || !empty($transaction->rejected_at);
    }

    /**
     * Une agence ne peut pas payer un retrait qu'elle a elle-même envoyé —
     * demande utilisateur du 2026-08-08 : "une agence qui a été expéditrice ne
     * peut plus faire de retrait pour la même opération" (contrôle anti-fraude).
     */
    private function isSameSendingAgency($agentId, Transaction $transaction): bool
    {
        if (!$agentId || !$transaction->agent_id) {
            return false;
        }
        return (string) $agentId === (string) $transaction->agent_id;
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
