<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\OutboundRequest;
use App\Currency;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Libraries\PeexCorridors;
use App\Outbound;
use App\Sender;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

/**
 * @group Outbound
 * This class is intended to manage all actions related to Outbound resource
 * Class OutboundController
 * @package App\Api\V1\Controllers
 *
 * Integration Peex Remittance API (doc: https://peex-api-docs.peexit.com/)
 * Base URL / SECRETKEY sont lus depuis .env (PEEX_URL, SECRET_KEY).
 *
 * IMPORTANT : la nouvelle API Peex documentee n'expose que 6 endpoints :
 *   GET  /clients/me
 *   POST /clients/verify_phoneNumber
 *   POST /clients/verify-wallet   (tiret, confirme par le support Peex le 2026-07-04)
 *   POST /clients/request_payment
 *   POST /clients/request_bank_payment
 *   GET  /clients/all_requests
 *
 * NOTE : la doc Peex est organisee par service (Remittance, Disbursement,
 * Collecte) — bien verifier que la section consultee correspond au bon
 * service avant de comparer un chemin a l'implementation.
 * Elle n'a PAS d'endpoint de cotation (quote) ni de verification de compte
 * bancaire : le taux (fxrate) et l'aval AML/CFT sont fournis par le
 * partenaire (nous) lors de l'appel, pas retournes par Peex.
 */
class OutboundController extends Controller
{
    /**
     * Entry point where we list all Outbounds from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Outbound::class);
    }

    /**
     * Store a newly created Outbound in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(OutboundRequest $request)
    {
        return RestHelper::store(Outbound::class, $request->all());
    }

    /**
     * Display the specified Outbound.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Outbound::class,$id);
    }

    /**
     * Update the specified Outbound in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(OutboundRequest $request,$id)
    {
        return RestHelper::update(Outbound::class,$request->all(),$id);
    }

    /**
     * Remove the specified Outbound from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Outbound::class,$id);
    }

    /**
     * Build a pre-configured Guzzle client for the Peex API.
     * Header name is SECRETKEY (per official doc), not AGENT-SECRET-KEY.
     */
    private function peexClient()
    {
        return new \GuzzleHttp\Client([
            'verify' => false,
            'headers' => [
                'SECRETKEY' => env('SECRET_KEY'),
                'Content-Type' => 'application/json',
            ],
            'base_uri' => env('PEEX_URL'),
        ]);
    }

    /**
     * Normalize a phone number to international format (+xxxxxxxxxx).
     */
    private function toInternationalPhone($rawPhone)
    {
        if (!$rawPhone) {
            return null;
        }
        $clean = str_replace(' ', '', $rawPhone);
        $clean = ($clean[0] === '+') ? $clean : '+' . $clean;
        // FIX (2026-07-04) : un numéro réduit au seul indicatif pays (ex: "+237",
        // sans aucun chiffre local) passait ce contrôle tel quel car il n'est ni
        // vide ni null — Peex le rejetait ensuite en 422 avec un message peu
        // clair côté agent. Un tel cas a été observé en base (bug de saisie/
        // navigation côté mobile — voir §4.19/§4.20/§4.21 du rapport
        // d'intégration Peex). On exige désormais au moins 8 chiffres au total
        // (indicatif + numéro local) ; en dessous, ce ne peut être qu'un
        // indicatif seul ou un numéro tronqué.
        $digitsOnly = preg_replace('/\D/', '', $clean);
        if (strlen($digitsOnly) < 8) {
            return null;
        }
        return $clean;
    }

    /**
     * Uniform error handling for Peex calls: tries to decode Peex's
     * {error: {statusCode, name, message}} JSON body when available.
     */
    private function peexErrorResponse(\Exception $e, $context = '')
    {
        $status = method_exists($e, 'getCode') ? $e->getCode() : 500;
        $message = $e->getMessage();
        $rawBody = null;

        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $rawBody = (string) $e->getResponse()->getBody();
            $decoded = json_decode($rawBody, true);
            if (isset($decoded['error'])) {
                $status = $decoded['error']['statusCode'] ?? $status;
                $message = $decoded['error']['message'] ?? $message;
            }
        }

        // Log de diagnostic : la doc Peex diverge parfois de ce qui est réellement déployé
        // en sandbox (voir OutboundController::check_account_status) — on trace ici le corps
        // brut renvoyé par Peex pour pouvoir comparer avec ce qu'on a envoyé.
        Log::error('[Peex' . ($context ? " $context" : '') . '] échec appel Peex : HTTP ' . $status
            . ' — message : ' . $message
            . ($rawBody ? (' — corps brut : ' . mb_substr($rawBody, 0, 1000)) : ''));

        return response()->json(['status' => $status, 'message' => $message], is_int($status) ? $status : 400);
    }

    /**
     * Liste des corridors mobile money supportés par Peex (source: doc officielle,
     * pas d'endpoint Peex pour ça — voir App\Libraries\PeexCorridors).
     * À utiliser côté app pour filtrer le sélecteur de pays avant même d'appeler Peex.
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_corridors()
    {
        return response()->json(['corridors' => PeexCorridors::forApp()]);
    }

    /**
     * Résout le "partenaire" pour un pays donné.
     *
     * IMPORTANT (correctif) : à l'époque TerraPay, get_partner interrogeait une table
     * de partenaires par pays et renvoyait {client: {id, name}}. Le mobile (transaction.page.ts,
     * validatetransaction.page.ts) ET l'admin (TransactionController::update/getquotation/
     * sendtransaction) dépendent tous de cette forme précise pour résoudre corridor_id/nom_api
     * avant de continuer la validation.
     *
     * Le précédent correctif Peex avait remplacé le corps de cette méthode par un appel
     * réseau à Peex `GET /clients/me` (infos de compte/solde — sans rapport avec un pays).
     * Deux problèmes : (1) cette réponse ne contient jamais de clé "client" (voir doc Peex :
     * name/email/solde/... uniquement), donc les 3 appelants recevaient toujours
     * "Corridor non disponible" ; (2) quand le sandbox Peex renvoie lui-même une erreur 5xx
     * (observé en prod : {"status":500,"message":"Internal Server Error"}), cette erreur
     * remontait telle quelle jusqu'à l'admin, d'où le bandeau "Erreur backend : Internal
     * Server Error" au moment de valider une transaction.
     *
     * Peex étant désormais l'unique partenaire (plus de partenaire par pays), on renvoie
     * un descripteur statique sans dépendre d'un appel réseau tiers pour cette étape.
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_partner(Request $request){
        return response()->json([
            'client' => [
                'id' => 1,
                'name' => 'Peex',
            ],
        ]);
    }

    /**
     * GET /clients/me — infos de compte Peex (solde, frais, statut du compte).
     * Séparé de get_partner() (voir ci-dessus) : à utiliser uniquement pour un futur
     * affichage de solde/statut de compte, pas pour la résolution de corridor.
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_peex_account(){
        $client = $this->peexClient();

        try{
            $response = $client->get('clients/me');
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->peexErrorResponse($e);
        }

        return response()->json(json_decode($response->getBody()->getContents(), true));
    }

    /**
     * POST /clients/verify_phoneNumber — validite du numero du beneficiaire.
     * Nouveau endpoint (n'existait pas dans l'ancienne integration).
     * @return \Illuminate\Http\JsonResponse
     */
    public function verify_phone_number(Request $request)
    {
        $phone = $this->toInternationalPhone($request->get('receiver_phone') ?? $request->get('mobile_phone'));
        if (!$phone) {
            return response()->json(['status' => 422, 'message' => 'receiver_phone is required'], 422);
        }

        $client = $this->peexClient();

        try {
            $response = $client->post('clients/verify_phoneNumber', [
                'json' => ['mobile_phone' => $phone],
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->peexErrorResponse($e);
        }

        return response()->json(json_decode($response->getBody()->getContents(), true));
    }

    /**
     * POST /clients/verify-wallet — verifie qu'un compte mobile money est valide
     * (remplace l'ancien check_momo_account_status, qui n'existe plus chez Peex).
     * @return \Illuminate\Http\JsonResponse
     */
    public function check_account_status(Request $request)
    {
        $rawPhone = $request->get('receiver_phone');
        if (!$rawPhone) {
            return response()->json(['status' => 422, 'message' => 'receiver_phone is required'], 422);
        }
        $phone_number = $this->toInternationalPhone($rawPhone);

        $countryCode = $request->get('receiving_country') ?? $request->get('country_code');
        if (!$countryCode) {
            return response()->json(['status' => 422, 'message' => 'receiving_country is required'], 422);
        }
        if (!PeexCorridors::isMomoSupported($countryCode)) {
            return response()->json([
                'status' => 422,
                'message' => "Le pays $countryCode n'est pas un corridor mobile money supporté par Peex.",
                'supported_corridors' => array_keys(PeexCorridors::list()),
            ], 422);
        }

        $client = $this->peexClient();

        // FIX : deux écarts par rapport à l'exemple officiel
        // (https://peex-api-docs.peexit.com/verify-wallet : {"countryCode":"CM","accountNumber":"690123456"}) :
        // 1) countryCode était envoyé en minuscule (strtolower) alors que l'exemple Peex est en MAJUSCULE.
        // 2) accountNumber était envoyé au format international complet (+237694694661, via
        //    toInternationalPhone) alors que l'exemple Peex attend le numéro LOCAL sans indicatif
        //    ni "+" (690123456). Un format inattendu peut faire planter le parsing côté Peex et
        //    expliquer la 500 générique observée jusqu'ici, quel que soit le chemin (underscore/tiret).
        $countryCodeUpper = strtoupper($countryCode);
        $dial = PeexCorridors::list()[$countryCodeUpper]['dial'] ?? null;
        $localAccountNumber = $phone_number;
        if ($dial && strpos($localAccountNumber, $dial) === 0) {
            $localAccountNumber = substr($localAccountNumber, strlen($dial));
        }

        $payload = [
            'countryCode' => $countryCodeUpper,
            'accountNumber' => $localAccountNumber,
        ];

        // NOTE (2026-07-04) : le support Peex a confirmé que "clients/verify-wallet"
        // (tiret, chemin documenté) est déployé en sandbox/dev et que le 404 précédent
        // était dû à une maintenance. Retesté le jour même : le 404 "Endpoint not found"
        // persiste EXACTEMENT à l'identique (voir laravel-2026-07-04.log) — donc soit la
        // confirmation était prématurée, soit le fix n'est pas allé jusqu'au bout côté
        // Peex. Plutôt que de dépendre d'une info Peex qui s'est révélée fausse à l'usage,
        // on tente le chemin documenté (tiret) puis, uniquement si Peex répond 404 "route
        // inconnue", on retente automatiquement l'ancien chemin (underscore) avant
        // d'abandonner. Objectif : rester fonctionnel quel que soit celui des deux chemins
        // réellement actif côté Peex à un instant donné, sans intervention manuelle.
        $paths = ['clients/verify-wallet', 'clients/verify_wallet'];
        $lastException = null;
        $response = null;

        foreach ($paths as $i => $path) {
            Log::info("[Peex check_account_status] appel $path — payload envoyé : " . json_encode($payload));
            try {
                $response = $client->post($path, ['json' => $payload]);
                $lastException = null;
                break;
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $lastException = $e;
                $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : null;
                // FIX (2026-07-22) : le repli vers le chemin suivant ne se déclenchait
                // qu'en cas de 404. Or l'historique de cette intégration (voir
                // rapport_integration_peex.md §4.6) montre que "clients/verify-wallet"
                // (tiret, doc officielle) répond parfois 500 en sandbox alors que
                // "clients/verify_wallet" (underscore, legacy) répond correctement au
                // même instant. Sans repli sur 500, ces 500 remontaient telles quelles
                // à l'utilisateur alors qu'un chemin fonctionnel existait. On tente donc
                // aussi le chemin suivant sur toute erreur 404 ou 5xx.
                $shouldFallback = $statusCode === 404 || ($statusCode !== null && $statusCode >= 500);
                $hasMorePaths = $i < count($paths) - 1;
                if ($shouldFallback && $hasMorePaths) {
                    Log::warning("[Peex check_account_status] $path a échoué (HTTP $statusCode), tentative du chemin suivant.");
                    continue;
                }
                break;
            }
        }

        if ($lastException !== null) {
            return $this->peexErrorResponse($lastException, 'check_account_status');
        }

        $responseBody = (string) $response->getBody();
        Log::info('[Peex check_account_status] réponse Peex OK : ' . mb_substr($responseBody, 0, 1000));
        $peexData = json_decode($responseBody, true) ?: [];

        // NORMALISATION (2026-07-04) : selon lequel des deux chemins a répondu
        // (voir foreach ci-dessus), Peex renvoie DEUX formats différents pour
        // le même résultat :
        //   - "clients/verify-wallet" (doc officielle, pas encore déployé en
        //     sandbox à ce jour) : {isValid, accountName, operator, status}
        //     avec "status" = statut du compte en texte ("ACTIVE"...).
        //   - "clients/verify_wallet" (legacy, actuellement le seul qui répond
        //     en sandbox) : {valid, accountTitle, accountStatus, accountType}
        //     — PAS de clé "status" du tout.
        // Les consommateurs (admin TransactionController::update(), mobile)
        // testent `$p['status'] === 200` pour détecter un succès HTTP — avec
        // le format brut de Peex, cette clé est soit absente (-> "Undefined
        // array key" observé en admin), soit une chaîne texte qui ne vaut
        // jamais 200. On renvoie donc une enveloppe normalisée et stable,
        // quel que soit le format Peex sous-jacent.
        $isValid = $peexData['isValid'] ?? $peexData['valid'] ?? null;
        $accountName = $peexData['accountName'] ?? $peexData['accountTitle'] ?? null;
        $accountStatus = $peexData['status'] ?? $peexData['accountStatus'] ?? null;
        $operator = $peexData['operator'] ?? $peexData['accountType'] ?? null;

        // FIX (2026-07-07) : Peex peut répondre HTTP 200 tout en indiquant business-side
        // que le wallet n'a pas pu être vérifié, ex: {"valid":false,"message":"Error
        // verifying wallet"} (observé en sandbox pour le numéro de test "Pending"
        // 699000001, voir laravel-2026-07-07.log ~00:32). Ce cas n'est PAS une erreur
        // HTTP (pas d'exception Guzzle), donc il ne passait jamais par peexErrorResponse()
        // — mais ce n'est pas non plus une vérification réussie. Le message Peex
        // ("message" au niveau racine du body, absent seulement du format officiel
        // isValid/accountName) est désormais remonté explicitement pour que l'appelant
        // (admin TransactionController::update()) puisse le distinguer d'un succès et
        // bloquer la suite du parcours au lieu de continuer avec un compte invalide.
        if ($isValid === false) {
            Log::warning('[Peex check_account_status] wallet invalide (valid=false) : ' . $responseBody);
        }

        return response()->json([
            'status' => 200,
            'valid' => $isValid,
            'account_name' => $accountName,
            'account_status' => $accountStatus,
            'operator' => $operator,
            'message' => $peexData['message'] ?? null,
            'raw' => $peexData,
        ]);
    }

    /**
     * Peex n'expose plus de verification de compte bancaire dans sa doc actuelle
     * (seul /clients/verify-wallet pour le mobile money existe). On garde la
     * route pour ne pas casser les clients existants, mais on renvoie une
     * reponse explicite plutot que d'appeler un endpoint qui n'existe pas.
     * @return \Illuminate\Http\JsonResponse
     */
    public function check_bank_account_status(Request $request)
    {
        return response()->json([
            'status' => 501,
            'message' => "La verification de compte bancaire n'est pas disponible dans l'API Peex documentee actuellement (aucun endpoint /clients/verify_bank). "
                . "Vous pouvez soumettre directement la transaction via send_bank_transaction ; Peex validera l'IBAN/SWIFT a ce moment-la.",
        ], 501);
    }

    /**
     * Calcule un taux de change local a partir de la table currencies
     * (alimentee par App\Libraries\CurrencyLayer). Peex ne fournissant plus
     * d'endpoint de cotation, c'est nous qui devons fournir le fxrate lors
     * de l'envoi de la transaction (send_transaction / send_bank_transaction).
     */
    private function computeLocalQuotation(Request $request)
    {
        $sendingCurrency = $request->get('sendingCurrency') ?? $request->get('requestCurrency');
        $receivingCurrency = $request->get('receivingCurrency');
        $amount = floatval($request->get('amount'));

        if (!$sendingCurrency || !$receivingCurrency) {
            return response()->json(['status' => 422, 'message' => 'sendingCurrency and receivingCurrency are required'], 422);
        }

        $from = Currency::whereCode($sendingCurrency)->first();
        $to = Currency::whereCode($receivingCurrency)->first();

        if (!$from || !$to || !$from->rate || !$to->rate) {
            return response()->json(['status' => 422, 'message' => 'Unknown or unrated currency'], 422);
        }

        $fxrate = $to->rate / $from->rate;
        $convertedAmount = round($amount * $fxrate, 2);

        return response()->json([
            'quoteId' => (string) Str::uuid(),
            'fxrate' => $fxrate,
            'sendingCurrency' => $sendingCurrency,
            'receivingCurrency' => $receivingCurrency,
            'amount' => $amount,
            'convertedAmount' => $convertedAmount,
            'fees' => $to->fees ?? 0,
            'note' => "Taux calcule localement : l'API Peex ne fournit plus de cotation. Ce fxrate doit etre renvoye tel quel dans l'appel send_transaction / send_bank_transaction.",
        ]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_quotation(Request $request)
    {
        $user = User::find($request->get('user_id'));
        if (!$user) {
            return response()->json(['status' => 422, 'message' => 'user not found'], 422);
        }

        return $this->computeLocalQuotation($request);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_bank_quotation(Request $request)
    {
        $user = User::find($request->get('user_id'));
        if (!$user) {
            return response()->json(['status' => 422, 'message' => 'user not found'], 422);
        }

        return $this->computeLocalQuotation($request);
    }

    /**
     * POST /clients/request_payment — envoi mobile money.
     * @return \Illuminate\Http\JsonResponse
     */
    public function send_transaction(Request $request)
    {
        $user = User::find($request->get('user_id'));
        $sender = Sender::find($request->get('sender_id'));

        if (!$user || !$sender) {
            return response()->json(['status' => 422, 'message' => 'user or sender not found'], 422);
        }

        $phone_number = $this->toInternationalPhone($request->get('receiver_phone'));
        if (!$phone_number) {
            return response()->json(['status' => 422, 'message' => 'receiver_phone is required'], 422);
        }

        $receivingCountry = $request->get('receiving_country');
        if (!PeexCorridors::isMomoSupported($receivingCountry)) {
            return response()->json([
                'status' => 422,
                'message' => "Le pays $receivingCountry n'est pas un corridor mobile money supporté par Peex.",
                'supported_corridors' => array_keys(PeexCorridors::list()),
            ], 422);
        }

        // FIX (2026-07-06) : le track_id n'était garanti unique QUE lorsque l'appelant
        // le générait lui-même (admin : ranking-uniqid(), voir TransactionController::
        // sendtransaction() côté admin) — le mobile (validatetransaction.page.ts)
        // n'envoie qu'un 'reference' brut (ranking), l'exposant au 422 Peex "This
        // transaction reference has already been used" en cas de collision de ranking
        // (compteur de test remis à zéro, voir rapport §4.20/§4.23). On garantit
        // désormais l'unicité ICI, au niveau backend, quel que soit l'appelant : si
        // aucun 'track_id' explicite n'est fourni, on en dérive un unique nous-mêmes.
        $baseRef = $request->get('track_id') ?: $request->get('reference') ?: (string) Str::uuid();
        $trackId = $request->get('track_id') ?: ($baseRef . '-' . uniqid());

        // FIX (2026-07-04) : cet appel cible "disbursement/request_payment" (Disbursement
        // API), mais envoyait encore les noms de champs de "clients/request_payment"
        // (Remittance API) — deux endpoints Peex distincts avec des schémas différents
        // (voir https://peex-api-docs.peexit.com/disbursement/request-payment). Peex
        // rejetait la requête en 422 : "must have required property 'country'", car
        // Disbursement attend "country"/"currency", pas "to_country"/"from_currency", et
        // n'a ni "aml_cft" ni "fxrate" dans son schéma. Champs alignés sur la doc
        // Disbursement exclusivement (plus de mélange Remittance/Disbursement/TerraPay).
        $data = [
            'track_id' => $trackId,
            'amount' => floatval($request->get('amount')),
            'currency' => $request->get('sendingCurrency') ?: $request->get('currency') ?: 'XAF',
            'mobile_phone' => $phone_number,
            'sender_first_name' => $user->first_name,
            'sender_last_name' => $user->last_name,
            'sender_mobile_phone' => $user->phone_number,
            'first_name' => $request->get('receiver_first_name'),
            'last_name' => $request->get('receiver_last_name'),
            'country' => $receivingCountry,
            'purpose' => $request->get('purpose') ?: 'FAMILY',
            'fund_origin' => $request->get('fund_origin') ?: 'SALARY',
        ];

        try{
            // Doc : https://peex-api-docs.peexit.com/disbursement/request-payment
            $response = $this->peexClient()->post('disbursement/request_payment', [
                'json' => $data,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->peexErrorResponse($e);
        }

        // FIX (2026-07-04) : TransactionController::sendtransaction() côté admin
        // teste `$p['status'] === 200` pour détecter le succès de l'envoi — mais
        // le corps JSON renvoyé par Peex sur un succès ne contient PAS forcément
        // de clé 'status' (il expose plutôt un objet 'transaction'). Résultat :
        // ce test échouait TOUJOURS silencieusement (PHP "Undefined array key
        // status"), même quand l'envoi à Peex avait réellement réussi — la
        // transaction n'était alors jamais marquée validée côté admin. On force
        // donc explicitement 'status' => 200 ici : atteindre cette ligne garantit
        // déjà un HTTP 2xx (toute erreur HTTP part dans le catch RequestException
        // ci-dessus et renvoie peexErrorResponse(), qui a SON PROPRE 'status' =
        // code d'erreur réel). On ne laisse jamais un éventuel champ 'status' du
        // corps Peex écraser ce marqueur de succès.
        //
        // FIX (2026-07-06) : la réponse Peex enveloppe réellement le résultat sous une
        // clé "request" (doc officielle : https://peex-api-docs.peexit.com/disbursement/
        // request-payment — {"request":{"id","amount","status","track_id","type",...}}),
        // PAS "transaction", et ne fournit ni "created_at" ni "updated_at". Le code admin
        // (TransactionController::sendtransaction()) hérité de TerraPay lisait
        // $p['transaction']['transaction_reference']/['created_at']/['updated_at'] : des
        // clés qui n'ont jamais existé chez Peex, donc 'reference' était enregistré à
        // null en base — décorrélant silencieusement la transaction du vrai track_id
        // envoyé à Peex, et rendant tout suivi de statut ultérieur impossible (voir
        // check_transaction_status ci-dessous). On expose donc ici une enveloppe stable
        // ('track_id', 'reference', 'peex_status') que les appelants (admin/mobile)
        // peuvent lire sans dépendre du format brut de Peex.
        $body = json_decode($response->getBody()->getContents(), true);
        if (!is_array($body)) { $body = []; }
        $peexRequest = $body['request'] ?? $body['transaction'] ?? [];
        $body['status'] = 200;
        $body['track_id'] = $peexRequest['track_id'] ?? $trackId;
        $body['reference'] = $body['track_id'];
        $body['peex_status'] = $peexRequest['status'] ?? null;
        return response()->json($body);
    }


    /**
     * POST /clients/request_bank_payment — envoi vers un compte bancaire (IBAN).
     * @return \Illuminate\Http\JsonResponse
     */
    public function send_bank_transaction(Request $request)
    {
        $user = User::find($request->get('user_id'));
        $sender = Sender::find($request->get('sender_id'));

        if (!$user || !$sender) {
            return response()->json(['status' => 422, 'message' => 'user or sender not found'], 422);
        }

        $bankIban = $request->get('bank_iban') ?: $request->get('bankaccountno');
        $bankSwift = $request->get('bank_swift') ?: $request->get('sortcode');
        $bankAddress = $request->get('bank_address') ?: $request->get('address');

        if (!$bankIban || !$bankSwift || !$bankAddress) {
            return response()->json(['status' => 422, 'message' => 'bank_iban, bank_swift and bank_address are required'], 422);
        }

        // FIX (2026-07-06) : voir commentaire identique dans send_transaction() —
        // garantit l'unicité du track_id au niveau backend, quel que soit l'appelant
        // (admin envoie déjà un track_id unique ; le mobile n'envoyait qu'un
        // 'reference' brut, exposé à la collision "track_id already used").
        $baseRef = $request->get('track_id') ?: $request->get('reference') ?: (string) Str::uuid();
        $trackId = $request->get('track_id') ?: ($baseRef . '-' . uniqid());

        $data = [
            'track_id' => $trackId,
            'amount' => floatval($request->get('amount')),
            'from_currency' => $request->get('sendingCurrency') ?: $request->get('currency'),
            'to_currency' => $request->get('receivingCurrency') ?: 'XAF',
            'fxrate' => floatval($request->get('fxrate') ?: 1),
            'aml_cft' => 1,
            'bank_iban' => $bankIban,
            'bank_swift' => $bankSwift,
            'bank_address' => $bankAddress,
            'sender_first_name' => $user->first_name,
            'sender_last_name' => $user->last_name,
            'sender_mobile_phone' => $user->phone_number,
            'sender_country' => $sender->country,
            'first_name' => $request->get('receiver_first_name'),
            'last_name' => $request->get('receiver_last_name'),
            'to_country' => $request->get('receiving_country'),
            'purpose' => $request->get('purpose') ?: 'FAMILY',
            'fund_origin' => $request->get('fund_origin') ?: 'SALARY',
        ];

        if ($request->get('bank_name') ?? $request->get('bankname')) {
            $data['bank_name'] = $request->get('bank_name') ?? $request->get('bankname');
        }
        if ($request->get('sender_email') ?? $user->email) {
            $data['sender_email'] = $request->get('sender_email') ?? $user->email;
        }
        if ($request->get('sender_city')) {
            $data['sender_city'] = $request->get('sender_city');
        }
        if ($request->get('receiver_phone')) {
            $data['mobile_phone'] = $this->toInternationalPhone($request->get('receiver_phone'));
        }
        if ($request->get('receiver_email')) {
            $data['email'] = $request->get('receiver_email');
        }

        try {
            $response = $this->peexClient()->post('clients/request_bank_payment', [
                'json' => $data,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->peexErrorResponse($e);
        }

        // FIX (2026-07-04) : même correctif que send_transaction() ci-dessus —
        // voir son commentaire pour l'explication complète.
        // FIX (2026-07-06) : idem send_transaction() — Peex enveloppe le résultat
        // sous "request" (doc officielle : https://peex-api-docs.peexit.com/bank-payment-request),
        // pas "transaction", et sans "created_at"/"updated_at". On expose la même
        // enveloppe stable ('track_id', 'reference', 'peex_status') que send_transaction().
        $body = json_decode($response->getBody()->getContents(), true);
        if (!is_array($body)) { $body = []; }
        $peexRequest = $body['request'] ?? $body['transaction'] ?? [];
        $body['status'] = 200;
        $body['track_id'] = $peexRequest['track_id'] ?? $trackId;
        $body['reference'] = $body['track_id'];
        $body['peex_status'] = $peexRequest['status'] ?? null;
        return response()->json($body);
    }

    /**
     * GET /clients/all_requests (Remittance, bancaire) ou /disbursement/all_requests
     * (Disbursement, mobile money) — statut d'une transaction.
     * NB: Peex ne conserve ces infos que 3 jours (limite documentee).
     * @return \Illuminate\Http\JsonResponse
     */
    public function check_transaction_status(Request $request){
        $trackId = $request->get('track_id') ?: $request->get('referenceID');
        if (!$trackId) {
            return response()->json(['status' => 422, 'message' => 'track_id is required'], 422);
        }

        // FIX (2026-07-06) : Peex expose DEUX endpoints "Get All Requests" distincts,
        // un par service (doc officielle) :
        //   - clients/all_requests      -> service Remittance (transactions envoyées via
        //     clients/request_bank_payment, type "bank")
        //   - disbursement/all_requests -> service Disbursement (transactions envoyées via
        //     disbursement/request_payment, type "mobile")
        // Le code interrogeait auparavant toujours "disbursement/all_requests", quel que
        // soit le type réel de la transaction : un virement bancaire (envoyé via
        // Remittance) n'y était donc JAMAIS trouvé (404 "Transactions not found"), même
        // quand Peex l'avait bien enregistré côté Remittance. Les appelants (admin)
        // doivent désormais préciser 'type' ('bank' ou 'mobile', défaut 'mobile' pour
        // compat ascendante).
        $type = strtolower((string) ($request->get('type') ?? 'mobile'));
        $path = ($type === 'bank') ? 'clients/all_requests' : 'disbursement/all_requests';

        $client = $this->peexClient();

        try{
            $response = $client->get($path, [
                'query' => ['track_id' => $trackId],
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->peexErrorResponse($e);
        }

        return response()->json(json_decode($response->getBody()->getContents(), true));
    }
}
