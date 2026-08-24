<?php

namespace App\Libraries;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

/**
 * Client pour l'API WACEPAY / DigitWace (doc : "WACEPAY INTEGRATION API SERVICE
 * SPECIFICATION", v2.0.0, DigitWace Cameroun Sarl).
 *
 * Contrairement a Peex (SECRETKEY statique en en-tete, voir OutboundController::
 * peexClient), DigitWace utilise une authentification par jeton : POST /login
 * avec {email, password} renvoie un access_token Bearer valable 3600s (voir
 * doc §IV AUTHENTICATION). On met ce jeton en cache pour eviter de se
 * reconnecter a chaque appel, et on rejoue automatiquement l'appel une fois
 * en cas de 401 (jeton expire/revoque).
 *
 * DigitWace impose egalement un modele en deux etapes avant tout envoi :
 *   1) POST /sender/create   -> renvoie un "Code" expediteur a reutiliser
 *   2) POST /beneficiary/create -> renvoie un "Code" beneficiaire a reutiliser
 * puis, selon le mode de livraison :
 *   - Mobile money : POST /transaction/payercode (recupere payerCode) puis
 *     POST /transaction/wallet/create
 *   - Bancaire     : POST /transaction/payercode puis POST /transaction/bank/create
 *   - Cash pickup  : POST /transaction/cash/collection_code_by_country puis
 *     POST /transaction/cash/create
 *
 * Cette classe expose une methode brute request() plus des helpers typés par
 * endpoint ; l'orchestration metier (recuperation/creation sender local,
 * normalisation de reponse, etc.) reste dans OutboundController pour rester
 * au meme niveau que l'integration Peex existante.
 */
class DigitwaceClient
{
    /** Cle de cache du jeton d'acces DigitWace (partagee par tous les appelants). */
    private const TOKEN_CACHE_KEY = 'digitwace_access_token';

    /**
     * Client Guzzle "nu", sans jeton — utilise uniquement pour /login.
     */
    private function baseClient(): Client
    {
        return new Client([
            'verify' => false,
            'base_uri' => rtrim((string) env('DIGITWACE_URL'), '/') . '/',
            'headers' => [
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * POST /login — authentifie le partenaire et renvoie l'access_token brut
     * (doc §IV). Ne met PAS en cache lui-meme : voir token().
     */
    private function login(): string
    {
        $email = env('DIGITWACE_EMAIL');
        $password = env('DIGITWACE_PASSWORD');

        if (!$email || !$password) {
            // Pas de throw d'exception generique ici : on remonte un message
            // explicite (identifiants DigitWace non configures) plutot qu'un
            // 500 Guzzle cryptique si .env n'a pas encore ete rempli.
            throw new \RuntimeException('DIGITWACE_EMAIL / DIGITWACE_PASSWORD ne sont pas configures dans .env.');
        }

        $response = $this->baseClient()->post('login', [
            'json' => ['email' => $email, 'password' => $password],
        ]);
        $body = json_decode($response->getBody()->getContents(), true) ?: [];

        $token = $body['access_token'] ?? null;
        if (!$token) {
            throw new \RuntimeException('Reponse DigitWace /login sans access_token.');
        }

        // Marge de securite de 60s avant l'expiration reelle (3600s par defaut,
        // doc §IV) pour eviter d'utiliser un jeton perime pile au moment de l'appel.
        $ttl = max(60, intval($body['expires_in'] ?? 3600) - 60);
        Cache::put(self::TOKEN_CACHE_KEY, $token, $ttl);

        return $token;
    }

    /**
     * Retourne un jeton valide, depuis le cache si possible, sinon en se
     * reconnectant via /login.
     */
    private function token(): string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);
        if ($cached) {
            return $cached;
        }
        return $this->login();
    }

    /**
     * Client Guzzle authentifie (Authorization: Bearer {token}).
     */
    private function authedClient(string $token): Client
    {
        return new Client([
            'verify' => false,
            'base_uri' => rtrim((string) env('DIGITWACE_URL'), '/') . '/',
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $token,
            ],
        ]);
    }

    /**
     * Appel generique authentifie, avec un retry unique sur 401 (jeton
     * expire/revoque cote DigitWace avant l'echeance annoncee par /login).
     *
     * @param string $method GET|POST
     * @param string $path chemin relatif (ex: 'sender/create')
     * @param array $json corps JSON (POST) — ignore pour GET
     * @param array $query parametres de query string (GET)
     * @return array corps de reponse decode
     */
    public function request(string $method, string $path, array $json = [], array $query = []): array
    {
        $attempt = function () use ($method, $path, $json, $query) {
            $client = $this->authedClient($this->token());
            $options = [];
            if (!empty($json)) {
                $options['json'] = $json;
            }
            if (!empty($query)) {
                $options['query'] = $query;
            }
            $response = strtoupper($method) === 'GET'
                ? $client->get($path, $options)
                : $client->post($path, $options);
            return json_decode($response->getBody()->getContents(), true) ?: [];
        };

        try {
            return $attempt();
        } catch (RequestException $e) {
            $status = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
            if ($status === 401) {
                Log::warning('[DigitWace] 401 sur ' . $path . ' — jeton expire, reconnexion et nouvelle tentative.');
                Cache::forget(self::TOKEN_CACHE_KEY);
                return $attempt();
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // Sender / Beneficiary (doc §V, §VI)
    // ------------------------------------------------------------------

    public function createSender(array $data): array
    {
        return $this->request('POST', 'sender/create', $data);
    }

    public function createBeneficiary(array $data): array
    {
        return $this->request('POST', 'beneficiary/create', $data);
    }

    // ------------------------------------------------------------------
    // Referentiels (doc §VII, §X, §XIV, §XV, §XVI, §XVII, §XVIII, §XIX)
    // ------------------------------------------------------------------

    public function getPayerCode(array $data): array
    {
        return $this->request('POST', 'transaction/payercode', $data);
    }

    public function getBankList(string $iso2, $payercode): array
    {
        return $this->request('POST', 'transaction/bank/list', [
            'iso2' => $iso2,
            'payercode' => $payercode,
        ]);
    }

    /**
     * FIX (2026-08-24) : la doc DigitWace v2.0.0 avait initialement un bug de
     * copier-coller — la section XIV PayoutServiceCode affichait le meme
     * "Sample URL request" que la section X Bank juste au-dessus
     * (POST /transaction/bank/create), pour un payload totalement different.
     * Aucune des 4 variantes devinees (GET/POST x snake_case/camelCase sur
     * 'payout_service_code') ne fonctionnait (404/405 en cascade, voir
     * historique git pour l'ancienne implementation).
     *
     * WACEPAY a republie le PDF (meme version 2.0.0, meme date de couverture,
     * contenu de la section XIV corrige) avec le vrai chemin, confirme par un
     * test reel en sandbox le 2026-08-24 (login + requete effectues depuis
     * Postman) : POST /transaction/payouts/services, meme payload JSON que
     * documente ({payoutCountry, payoutCurrency}). Plus besoin de repli en
     * cascade.
     */
    public function getPayoutServiceCode(string $payoutCountry, string $payoutCurrency): array
    {
        return $this->request('POST', 'transaction/payouts/services', [
            'payoutCountry' => $payoutCountry,
            'payoutCurrency' => $payoutCurrency,
        ]);
    }

    public function getCollectionCode(array $data): array
    {
        return $this->request('POST', 'transaction/cash/collection_code_by_country', $data);
    }

    public function getRelation(): array
    {
        return $this->request('GET', 'transaction/relation');
    }

    public function getOriginFund(string $businessType): array
    {
        return $this->request('GET', 'transaction/origin_fund/' . rawurlencode($businessType));
    }

    public function getReason(string $businessType): array
    {
        return $this->request('GET', 'transaction/reason/' . rawurlencode($businessType));
    }

    public function getBalance(): array
    {
        return $this->request('GET', 'account/balance');
    }

    // ------------------------------------------------------------------
    // Transactions (doc §VIII, §IX, §X, §XI, §XII)
    // ------------------------------------------------------------------

    public function createWalletTransaction(array $data): array
    {
        return $this->request('POST', 'transaction/wallet/create', $data);
    }

    public function createBankTransaction(array $data): array
    {
        return $this->request('POST', 'transaction/bank/create', $data);
    }

    public function createCashTransaction(array $data): array
    {
        return $this->request('POST', 'transaction/cash/create', $data);
    }

    public function getStatus(string $reference): array
    {
        return $this->request('GET', 'transaction/status/' . rawurlencode($reference));
    }

    public function confirm(string $reference): array
    {
        return $this->request('POST', 'transaction/confirm', ['reference' => $reference]);
    }
}
