<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Client pour l'API Merchant PawaPay v2 (doc : https://docs.pawapay.io/v2/docs/welcome
 * et https://docs.pawapay.io/v2/api-reference/remittances/*), consultée le
 * 2026-08-20 suite à l'email de Max Hickey (Strategic Account Executive
 * PawaPay) du 2026-08-18.
 *
 * PawaPay est le 3e partenaire payeur Tholadpay (après Peex et DigitWace,
 * voir OutboundController::resolvePartner), intégré via l'API "Remittances"
 * (POST /v2/remittances) — spécifiquement conçue pour un envoi cross-border
 * avec KYC complet de l'expéditeur (contrairement à l'API "Payouts" générique
 * de PawaPay, qui ne demande que le destinataire), ce qui correspond au métier
 * Tholadpay. Voir https://docs.pawapay.io/v2/api-reference/remittances/initiate-remittance.
 *
 * Authentification : contrairement à DigitWace (login email/password ->
 * access_token temporaire, voir DigitwaceClient), PawaPay utilise un jeton
 * Bearer STATIQUE généré une fois depuis le Dashboard (Sandbox ou Production,
 * voir https://docs.pawapay.io/dashboard/other/system_conf/api_tokens) et
 * stocké tel quel dans .env (PAWAPAY_TOKEN) — pas de rafraîchissement/cache
 * nécessaire ici.
 *
 * Base URL (doc "How to start") :
 *   Sandbox    : https://api.sandbox.pawapay.io
 *   Production : https://api.pawapay.io
 * Lue depuis PAWAPAY_URL (.env) plutôt que codée en dur, pour permettre de
 * basculer sandbox -> prod sans toucher au code (même approche que
 * PEEX_URL/DIGITWACE_URL).
 */
class PawapayClient
{
    /**
     * Client Guzzle authentifié (Authorization: Bearer {token}).
     */
    private function client(): Client
    {
        $token = env('PAWAPAY_TOKEN');
        if (!$token) {
            // Même logique que DigitwaceClient::login() : message explicite plutôt
            // qu'un 401 Guzzle cryptique si .env n'a pas encore été rempli (le
            // sandbox PawaPay n'est pas encore créé au moment où ce code est écrit,
            // voir tâche "Sign up for sandbox" de l'email Max Hickey).
            throw new \RuntimeException('PAWAPAY_TOKEN n\'est pas configuré dans .env.');
        }

        return new Client([
            'verify' => false,
            'base_uri' => rtrim((string) env('PAWAPAY_URL'), '/') . '/',
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
    }

    /**
     * POST /v2/remittances — initie un virement mobile money cross-border
     * (doc Remittance API). Idempotent via $payload['remittanceId'] (UUID v4) :
     * un renvoi avec le même remittanceId renvoie "DUPLICATE_IGNORED" côté
     * PawaPay au lieu de créer un doublon — voir
     * OutboundController::sendPawapayRemittance, qui dérive ce champ du même
     * track_id que Peex/DigitWace pour rester cohérent avec le reste du code.
     *
     * Réponse synchrone : {remittanceId, status: ACCEPTED|REJECTED|
     * DUPLICATE_IGNORED, created, failureReason?}. "ACCEPTED" ne veut PAS dire
     * que l'argent est arrivé chez le bénéficiaire (asynchrone, voir
     * getRemittanceStatus / le callback webhook) — comportement à traiter
     * différemment de Peex (qui répond en général déjà avec un statut final
     * ou quasi-final) et plus proche de DigitWace (WAITING CONFIRMATION).
     */
    public function initiateRemittance(array $payload): array
    {
        $response = $this->client()->post('v2/remittances', ['json' => $payload]);
        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    /**
     * GET /v2/remittances/{remittanceId} — statut à jour d'un virement (doc
     * "Check remittance status"). À utiliser en secours si le callback
     * webhook n'est pas (encore) configuré côté dashboard PawaPay, ou pour
     * une vérification à la demande (voir OutboundController::
     * check_transaction_status).
     *
     * Réponse : {status: FOUND|NOT_FOUND, data: {remittanceId, status:
     * ACCEPTED|ENQUEUED|PROCESSING|IN_RECONCILIATION|COMPLETED|FAILED, ...}}.
     */
    public function getRemittanceStatus(string $remittanceId): array
    {
        $response = $this->client()->get('v2/remittances/' . rawurlencode($remittanceId));
        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    /**
     * POST /v2/remittances/{remittanceId}/resend-callback — redemande à
     * PawaPay l'envoi du callback pour ce virement (utile si notre endpoint
     * de callback était indisponible au moment du premier essai).
     */
    public function resendCallback(string $remittanceId): array
    {
        $response = $this->client()->post('v2/remittances/' . rawurlencode($remittanceId) . '/resend-callback');
        return json_decode($response->getBody()->getContents(), true) ?: [];
    }

    /**
     * POST /v2/remittances/{remittanceId}/cancel — annule un virement encore
     * au statut ENQUEUED (mis en attente côté PawaPay, ex : opérateur
     * temporairement indisponible). Sans effet si le virement a déjà avancé
     * au-delà de ce statut.
     */
    public function cancelEnqueued(string $remittanceId): array
    {
        $response = $this->client()->post('v2/remittances/' . rawurlencode($remittanceId) . '/cancel');
        return json_decode($response->getBody()->getContents(), true) ?: [];
    }
}
