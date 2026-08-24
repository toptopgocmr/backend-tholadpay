<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\OutboundRequest;
use App\Currency;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Libraries\DigitwaceClient;
use App\Libraries\PawapayClient;
use App\Libraries\PawapayCorridors;
use App\Libraries\PeexCorridors;
use App\Outbound;
use App\Sender;
use App\Transaction;
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
 *
 * Integration DigitWace / WACEPAY (doc : "WACEPAY INTEGRATION API SERVICE
 * SPECIFICATION" v2.0.0) ajoutee en 2026-08 : DigitWace est propose comme
 * SECOND partenaire, choisi explicitement par l'agent/l'admin a l'etape de
 * validation (voir App\Libraries\DigitwaceClient). Tous les endpoints
 * ci-dessous acceptent desormais un parametre 'partner' ('peex' par defaut,
 * pour ne rien casser des appelants existants, ou 'digitwace').
 *
 * Integration PawaPay ajoutee en 2026-08 : PawaPay est propose comme
 * TROISIEME partenaire payeur ('pawapay'), sur demande explicite du
 * 2026-08-20, suite a l'email de Max Hickey (Strategic Account Executive
 * PawaPay). Contrairement a Peex/DigitWace, PawaPay n'est integre ICI que
 * pour le corridor Congo-Brazzaville (mobile money AIRTEL_COG/MTN_MOMO_COG,
 * voir App\Libraries\PawapayCorridors), via l'API "Remittances" (doc :
 * https://docs.pawapay.io/v2/api-reference/remittances/initiate-remittance).
 * PawaPay ne propose ni virement bancaire ni retrait en especes : seul
 * send_transaction() (mobile money) accepte partner=pawapay ; send_bank_transaction
 * et send_cash_transaction le rejettent explicitement.
 *
 * IMPORTANT : au moment ou ce code est ecrit, le compte sandbox PawaPay
 * (https://dashboard.sandbox.pawapay.io) n'est pas encore cree et aucun
 * jeton API n'est configure (PAWAPAY_TOKEN/.env vides) — voir
 * App\Libraries\PawapayClient. Ce code n'a donc PAS pu etre teste contre un
 * vrai sandbox PawaPay ; a valider dès que les identifiants sandbox sont
 * disponibles, notamment le format exact attendu pour nationality/
 * identification.type et le corps precis du callback webhook (non confirme
 * dans la doc publique au moment de l'integration — voir pawapay_callback()).
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
     * Dérive le numéro LOCAL (sans indicatif pays) attendu par Peex/DigitWace
     * à partir d'un numéro international (+237...).
     *
     * FIX (2026-08-20, incident Cameroun) : après avoir retiré l'indicatif
     * (+237), il restait un '0' de tronc initial (convention de numérotation
     * locale ouest/centre-africaine — ex: on compose "0987654332" en local,
     * mais le numéro "réel" une fois combiné à l'indicatif est "+237987654332",
     * 9 chiffres) que ce code ne retirait jamais. Peex rejetait alors
     * systématiquement le numéro camerounais avec "Cameroon phone number must
     * have 9 digits !" (10 chiffres envoyés au lieu de 9 — voir
     * check_account_status, storage/logs/laravel : 19:33 UTC). Ce même motif
     * (indicatif retiré, '0' de tronc jamais retiré) existait aussi dans
     * sendDigitwaceWalletTransaction ci-dessous — probablement responsable des
     * mêmes rejets côté DigitWace pour tout corridor utilisant cette
     * convention, pas seulement le Cameroun.
     */
    private function toLocalPhoneNumber(?string $internationalPhone, ?string $dial): ?string
    {
        if (!$internationalPhone) {
            return null;
        }
        $local = ($dial && strpos($internationalPhone, $dial) === 0)
            ? substr($internationalPhone, strlen($dial))
            : ltrim($internationalPhone, '+');
        return preg_replace('/^0+/', '', $local);
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
     * Détermine le partenaire choisi pour cette requête. 'peex' par défaut :
     * tous les appelants existants (avant l'ajout de DigitWace) continuent
     * de fonctionner sans envoyer ce paramètre.
     */
    private function resolvePartner(Request $request): string
    {
        $partner = strtolower((string) ($request->get('partner') ?: 'peex'));
        // AJOUT (2026-08-08) : 'internal' — transfert 100% interne au réseau
        // d'agences tholadpay, sans passer par Peex ni DigitWace (voir
        // send_internal_transaction / InternalTransferController). Le
        // bénéficiaire retire chez n'importe quel agent tholadpay du pays
        // destinataire, via un code de retrait généré à l'envoi.
        // AJOUT (2026-08-20) : 'pawapay' — 3e partenaire payeur, mobile money
        // uniquement, corridor Congo-Brazzaville uniquement (voir
        // App\Libraries\PawapayCorridors et sendPawapayRemittance ci-dessous).
        return in_array($partner, ['peex', 'digitwace', 'internal', 'pawapay'], true) ? $partner : 'peex';
    }

    private function digitwaceClient(): DigitwaceClient
    {
        return new DigitwaceClient();
    }

    private function pawapayClient(): PawapayClient
    {
        return new PawapayClient();
    }

    /**
     * Gestion d'erreur uniforme pour les appels PawaPay — miroir de
     * peexErrorResponse()/digitwaceErrorResponse() ci-dessus. Le format
     * d'erreur PawaPay documenté (401/403/500) expose un champ
     * "failureReason": {failureCode, failureMessage} — voir doc "Check
     * remittance status". Les erreurs de validation (400) sur
     * POST /v2/remittances ne sont pas entièrement documentées dans la doc
     * publique consultée ; on tente donc plusieurs formes plausibles avant de
     * se replier sur le message Guzzle brut.
     */
    private function pawapayErrorResponse(\Exception $e, $context = '')
    {
        $status = method_exists($e, 'getCode') ? $e->getCode() : 500;
        $message = $e->getMessage();
        $rawBody = null;

        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            $rawBody = (string) $e->getResponse()->getBody();
            $status = $e->getResponse()->getStatusCode();
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $message = $decoded['failureReason']['failureMessage']
                    ?? $decoded['failureMessage']
                    ?? $decoded['message']
                    ?? $decoded['error']
                    ?? $message;
            }
        }

        Log::error('[PawaPay' . ($context ? " $context" : '') . '] échec appel PawaPay : HTTP ' . $status
            . ' — message : ' . $message
            . ($rawBody ? (' — corps brut : ' . mb_substr($rawBody, 0, 1000)) : ''));

        return response()->json(['status' => $status, 'message' => $message], is_int($status) ? $status : 400);
    }

    /**
     * Enums stricts imposés par l'API Remittance PawaPay pour
     * sender.transactionDetails.purposeOfFunds / sourceOfFunds (doc
     * "Initiate remittance", consultée le 2026-08-20) — pas de valeur libre
     * acceptée comme chez Peex (purpose/fund_origin en texte libre), même
     * logique que requireDigitwaceReferenceFields() ci-dessus pour DigitWace.
     * On exige donc explicitement ces paramètres plutôt que de deviner une
     * valeur par défaut qui risquerait d'être rejetée par PawaPay.
     */
    private const PAWAPAY_PURPOSE_OF_FUNDS = [
        'FAMILY_SUPPORT', 'MEDICAL_EXPENSES', 'TUITION_FEES', 'EDUCATION_SUPPORT',
        'GIFT_AND_OTHER_DONATIONS', 'HOME_IMPROVEMENT', 'DEBT_SETTLEMENT', 'REAL_ESTATE',
        'TAXES', 'SALARY', 'SAVINGS', 'PERSONAL_TRANSFER', 'OTHER',
    ];

    private const PAWAPAY_SOURCE_OF_FUNDS = [
        'SALARY', 'SAVINGS', 'LOTTERY', 'LOAN', 'BUSINESS_INCOME', 'GIFT', 'OTHER',
    ];

    private function requirePawapayReferenceFields(Request $request): array
    {
        $purpose = strtoupper((string) $request->get('pawapay_purpose_of_funds', ''));
        $source = strtoupper((string) $request->get('pawapay_source_of_funds', ''));

        if (!in_array($purpose, self::PAWAPAY_PURPOSE_OF_FUNDS, true)) {
            throw new \InvalidArgumentException(
                'pawapay_purpose_of_funds est requis et doit être l\'une des valeurs suivantes : '
                . implode(', ', self::PAWAPAY_PURPOSE_OF_FUNDS)
            );
        }
        if (!in_array($source, self::PAWAPAY_SOURCE_OF_FUNDS, true)) {
            throw new \InvalidArgumentException(
                'pawapay_source_of_funds est requis et doit être l\'une des valeurs suivantes : '
                . implode(', ', self::PAWAPAY_SOURCE_OF_FUNDS)
            );
        }

        return ['purposeOfFunds' => $purpose, 'sourceOfFunds' => $source];
    }

    /**
     * Convertit le type de pièce d'identité local (Sender::type_id, texte
     * libre saisi côté mobile/admin — voir aussi la même heuristique dans
     * ensureDigitwaceSenderCode ci-dessus) vers l'enum PawaPay
     * sender.senderDetails.identification.type (doc "Initiate remittance") :
     * NATIONAL_ID | PASSPORT | DRIVING_LICENSE | SOCIAL_SECURITY_ID |
     * RESIDENCE_PERMIT. 'CNI' (carte nationale d'identité, valeur la plus
     * courante en zone CEMAC) correspond à NATIONAL_ID.
     */
    private function resolvePawapayIdentificationType(?string $localTypeId): string
    {
        $type = strtolower((string) $localTypeId);
        if (strpos($type, 'pass') !== false) {
            return 'PASSPORT';
        }
        if (strpos($type, 'permis') !== false || strpos($type, 'driv') !== false) {
            return 'DRIVING_LICENSE';
        }
        if (strpos($type, 'sejour') !== false || strpos($type, 'séjour') !== false || strpos($type, 'residence') !== false || strpos($type, 'résidence') !== false) {
            return 'RESIDENCE_PERMIT';
        }
        if (strpos($type, 'secu') !== false || strpos($type, 'sécu') !== false || strpos($type, 'social') !== false) {
            return 'SOCIAL_SECURITY_ID';
        }
        return 'NATIONAL_ID';
    }

    /**
     * AJOUT (2026-08-13, demande explicite) : calcule le "businessType" combiné
     * (p2p/b2b/b2p/p2b, en minuscules — doc §XVII/§XVIII) attendu par
     * /transaction/reason/{businessType} et /transaction/origin_fund/{businessType}
     * à partir du type sender ('P'/'B') et du type bénéficiaire ('P'/'B').
     * Auparavant ces deux endpoints étaient toujours interrogés avec 'p2p' en
     * dur (voir get_digitwace_reasons/get_digitwace_origin_funds ci-dessous),
     * ce qui proposait de mauvais motifs/origines de fonds dès qu'un des deux
     * côtés était Business, avec risque de rejet DigitWace (erreur 2013/2014).
     */
    private function resolveDigitwaceBusinessType(?string $senderType, ?string $receiverType): string
    {
        $s = strtoupper((string) ($senderType ?: 'P')) === 'B' ? 'b' : 'p';
        $r = strtoupper((string) ($receiverType ?: 'P')) === 'B' ? 'b' : 'p';
        return "{$s}2{$r}";
    }

    /**
     * Gestion d'erreur uniforme pour les appels DigitWace — miroir de
     * peexErrorResponse() ci-dessus, adapté au format d'erreur DigitWace
     * (doc §III : la plupart des erreurs renvoient soit {"message":"..."}
     * soit {"messages":"..."} au niveau racine, jamais d'enveloppe imbriquée
     * "error" comme Peex).
     */
    private function digitwaceErrorResponse(\Exception $e, $context = '')
    {
        $status = method_exists($e, 'getCode') ? $e->getCode() : 500;
        $message = $e->getMessage();
        $rawBody = null;
        // FIX (2026-08-15) : jusqu'ici, ce log ne contenait que le code HTTP et le
        // corps de réponse — impossible de savoir, a posteriori, LEQUEL des appels
        // enchaînés dans sendDigitwace*/send_cash_transaction (sender/create,
        // beneficiary/create, payercode, bank/list, wallet|bank|cash/create,
        // confirm) a réellement échoué, puisqu'ils sont tous capturés par le même
        // bloc try/catch avec un $context générique ('send_transaction',
        // 'send_bank_transaction', ...). On journalise désormais la méthode + l'URL
        // exacte de la requête en échec (disponible sur la RequestException via
        // getRequest()), ce qui permet de diagnostiquer précisément un futur
        // incident (ex: "accès restreint") sans deviner l'étape en cause.
        $requestUrl = null;

        if ($e instanceof \GuzzleHttp\Exception\RequestException) {
            $requestUrl = $e->getRequest()->getMethod() . ' ' . (string) $e->getRequest()->getUri();
            if ($e->hasResponse()) {
                $rawBody = (string) $e->getResponse()->getBody();
                $status = $e->getResponse()->getStatusCode();
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded)) {
                    $message = $decoded['message'] ?? $decoded['messages'] ?? $message;

                    // FIX (2026-08-20) : sur un 422 de validation, DigitWace renvoie un
                    // message générique au niveau racine ("Erreur de validation des
                    // données, veuillez trouver la raison dans le message.") qui,
                    // ironiquement, NE CONTIENT PAS la raison — le détail champ par champ
                    // vit dans un sous-objet séparé ('errors' à la Laravel, ou parfois
                    // 'data'/'reason'/'reasons'/'details' selon l'endpoint DigitWace). On
                    // ne relayait jusqu'ici que ce message générique à l'admin (via
                    // send_bank_transaction -> $p['message'] -> TransactionController::
                    // sendtransaction -> flash 'error'), rendant l'erreur inexploitable en
                    // pratique (voir incident transaction #82, 2026-08-20 ~17:20 UTC :
                    // DigitWace a bien renvoyé le détail, mais celui-ci n'était visible
                    // que dans storage/logs/laravel.log sur le conteneur, pas dans le
                    // flux de logs Railway ni dans l'UI admin). On extrait et concatène
                    // ici ce détail au message pour qu'il remonte jusqu'à l'admin.
                    $detail = $decoded['errors'] ?? $decoded['data'] ?? $decoded['reason'] ?? $decoded['reasons'] ?? $decoded['details'] ?? null;
                    if (!empty($detail)) {
                        $detailText = is_array($detail) ? $this->flattenDigitwaceErrorDetail($detail) : (string) $detail;
                        if ($detailText !== '' && stripos($message, $detailText) === false) {
                            $message = rtrim($message, '. ') . ' — ' . $detailText;
                        }
                    }
                }
            }
        }

        Log::error('[DigitWace' . ($context ? " $context" : '') . '] échec appel DigitWace : HTTP ' . $status
            . ' — message : ' . $message
            . ($requestUrl ? (' — requête : ' . $requestUrl) : '')
            . ($rawBody ? (' — corps brut : ' . mb_substr($rawBody, 0, 1000)) : ''));

        return response()->json(['status' => $status, 'message' => $message], is_int($status) ? $status : 400);
    }

    /**
     * Aplati une structure d'erreurs DigitWace (souvent un objet
     * {champ: ["raison1", "raison2"]} façon Laravel, mais parfois une liste
     * de chaînes ou un objet imbriqué) en une seule chaîne lisible, pour
     * l'ajouter au message générique dans digitwaceErrorResponse() ci-dessus.
     */
    private function flattenDigitwaceErrorDetail($detail, int $depth = 0): string
    {
        if ($depth > 3) {
            return '';
        }
        if (is_string($detail) || is_numeric($detail)) {
            return (string) $detail;
        }
        if (!is_array($detail)) {
            return '';
        }
        $parts = [];
        foreach ($detail as $key => $value) {
            $flat = is_array($value) ? $this->flattenDigitwaceErrorDetail($value, $depth + 1) : (string) $value;
            if ($flat === '') {
                continue;
            }
            $parts[] = is_string($key) ? "{$key}: {$flat}" : $flat;
        }
        return implode(' ; ', $parts);
    }

    /**
     * Résout un nom de pays en clair (ex: 'Congo', tel que stocké sur
     * senders.country — voir mobile transaction.page.ts::addSender qui
     * l'écrit en dur) vers son code ISO2, via la même table
     * PeexCorridors::list() (clé ISO2 -> ['name' => ...]) déjà utilisée
     * ailleurs dans ce contrôleur, plutôt que de dupliquer une seconde liste
     * de pays. Repli sur les 2 premières lettres en majuscule si aucune
     * correspondance (mieux qu'un champ vide, DigitWace validera/rejettera
     * lui-même si c'est incorrect — voir digitwaceErrorResponse ci-dessus qui
     * remonte désormais le détail du rejet).
     */
    private function countryNameToIso2(?string $countryName): string
    {
        $countryName = trim((string) $countryName);
        if ($countryName === '') {
            return 'N/A';
        }
        if (strlen($countryName) === 2) {
            // Déjà un code ISO2 (pas le cas actuellement pour les senders locaux,
            // mais évite un aller-retour inutile si ça change un jour).
            return strtoupper($countryName);
        }
        foreach (PeexCorridors::list() as $iso2 => $info) {
            if (strcasecmp($info['name'] ?? '', $countryName) === 0) {
                return $iso2;
            }
        }
        // FIX (2026-08-22, vérification préventive USA/Chine après l'incident
        // 'city'/'country' bénéficiaire de la transaction #100) : PeexCorridors
        // ne couvre QUE les corridors mobile money Afrique de l'Ouest/Centrale
        // (voir sa doc de classe) — "France", "China", "United States" n'y
        // figurent jamais, donc CHAQUE pays hors mobile money africain tombait
        // systématiquement sur le repli par troncature ci-dessous. Ce repli est
        // correct par coïncidence pour "France" (FR) mais SILENCIEUSEMENT FAUX
        // pour "China" (tronqué en "CH" = Suisse, pas "CN") ou par exemple
        // "United States" (tronqué en "UN", pas "US") — un risque de rejet
        // WACEPAY (ou pire, un envoi accepté vers le mauvais pays côté
        // référentiel) qu'on ne peut pas se permettre de découvrir en
        // production comme pour 'city'. Avant de tronquer, on interroge donc la
        // table `countries` (déjà la source de vérité utilisée par le
        // sélecteur de pays mobile, voir country.page.ts qui expose son
        // iso_3166_2 comme "country_code" côté app) par correspondance de nom.
        $iso2 = \App\Country::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($countryName)])
            ->value('iso_3166_2');
        if ($iso2) {
            return strtoupper($iso2);
        }
        Log::warning("[DigitWace] Pays '$countryName' absent de PeexCorridors::list() ET de la table countries — ISO2 approximé par troncature, à vérifier si DigitWace le rejette.");
        return strtoupper(substr($countryName, 0, 2));
    }

    /**
     * FIX (2026-08-21, blocage envoi transaction #96) : résout une "ville
     * expéditeur" pour WACEPAY (/sender/create, champ 'city') en l'absence de
     * tout champ dédié en base (senders.postal_code contient un code postal
     * ou, à défaut, le pays — jamais une ville, voir ensureDigitwaceSenderCode
     * ci-dessous). Envoyer $sender->country (ex: "Congo") était rejeté par
     * WACEPAY ("This city does not exist or is disabled"), un nom de pays
     * n'étant jamais une ville valide.
     *
     * Solution provisoire : le réseau d'agents TholadPay/Send-Paz n'opère à ce
     * jour que depuis Congo-Brazzaville, donc "Brazzaville" est un défaut
     * fiable pour tous les senders actuels. Table à étendre (par ISO2) le jour
     * où un agent basé dans un autre pays est enregistré.
     *
     * CONFIRMÉ PAR WACEPAY (support digitwace, 2026-08-22), en réponse à nos
     * questions sur ce champ :
     *   1. 'city' valide contre une liste FERMÉE de villes déjà enregistrées
     *      dans leur système (même mécanisme que 'idType') ; si la ville n'y
     *      figure pas, la valeur à envoyer est littéralement "Any City".
     *   2. "Brazzaville" est confirmée présente dans cette liste — le défaut
     *      CG => 'Brazzaville' ci-dessous n'est donc plus une hypothèse.
     *   3. Il n'existe aucun endpoint pour récupérer la liste des villes
     *      valides par pays (contrairement à PayoutServiceCode ou à la liste
     *      des banques) — impossible de valider ce champ côté client avant
     *      l'envoi ; WACEPAY nous renvoie explicitement vers "Any City" pour
     *      tout pays/ville non couvert par une correspondance déjà confirmée.
     *
     * FIX (2026-08-22) : pour tout pays hors de la table ci-dessous, on
     * envoyait auparavant $sender->country brut (ex. "France"), qui aurait
     * échoué avec la même erreur 3003 que le bug initial — un nom de pays
     * n'étant jamais une ville valide. On utilise désormais "Any City" par
     * défaut (valeur que WACEPAY reconnaît explicitement), au lieu de
     * réintroduire silencieusement le même bug pour un pays non mappé.
     *
     * À remplacer par un vrai champ "Ville expéditeur" (migration senders +
     * formulaires mobile/admin) le jour où plusieurs villes confirmées par
     * WACEPAY doivent être distinguées (ex: agents dans plusieurs villes du
     * Congo) — voir aussi le champ "ville"/town_id déjà saisi côté mobile
     * (customermodify.page.ts, transaction.page.ts) pour l'adresse locale du
     * sender : il n'est PAS utilisé ici intentionnellement, faute de moyen de
     * vérifier au préalable si cette ville précise est enregistrée chez
     * WACEPAY (voir point 3 ci-dessus) — l'utiliser tel quel risquerait de
     * réintroduire l'erreur 3003 pour toute ville congolaise autre que
     * Brazzaville.
     */
    private function resolveSenderCity(Sender $sender): string
    {
        $iso2 = $this->countryNameToIso2($sender->country);
        $defaultCityByCountry = [
            'CG' => 'Brazzaville', // Congo-Brazzaville — confirmé valide par WACEPAY le 22/08/2026.
        ];
        // Fallback "Any City" confirmé par WACEPAY (22/08/2026) pour tout pays
        // non couvert par la table ci-dessus, au lieu du nom de pays brut
        // (systématiquement rejeté par leur validation de 'city').
        return $defaultCityByCountry[$iso2] ?? 'Any City';
    }

    /**
     * Récupère (ou crée si absent) le Code expéditeur DigitWace pour ce
     * Sender local, et le met en cache sur la ligne `senders.digitwace_code`
     * (voir migration 2026_08_08_090000) pour ne jamais recréer deux fois le
     * même expéditeur chez DigitWace (doc §V : "sender_code" doit être
     * réutilisé sur toutes les transactions suivantes du même expéditeur).
     */
    private function ensureDigitwaceSenderCode(Sender $sender, User $user): string
    {
        if (!empty($sender->digitwace_code)) {
            return $sender->digitwace_code;
        }

        // Heuristiques de correspondance entre les champs locaux (issus de
        // l'ancienne intégration TerraPay/Peex, plus permissifs) et le schéma
        // DigitWace, strict sur idType/civility (doc §V).
        // FIX (2026-08-21, incidents #81/#82/#92/#93) : WACEPAY confirme (retour
        // support digitwace, 2026-08-21) que 'CNI' n'existe PAS dans la liste des
        // idType supportés — d'où l'erreur 3003 "This Id type does not exist or is
        // disabled" sur /sender/create pour tout sender non-passeport. Le code
        // WACEPAY correct pour une carte d'identité nationale est 'CI' (IDENTITY
        // CARD). Liste complète confirmée : PP, CI, RCCM, AG, TAX, CPF, CNPJ, FID,
        // CC, CR, SSP, LI, CNIC, BI, CID, GCCID.
        $idTypeRaw = strtolower((string) ($sender->type_id ?? ''));
        $idType = (strpos($idTypeRaw, 'pass') !== false) ? 'PP' : 'CI';
        $gender = strtoupper((string) ($sender->sex ?? 'M')) === 'F' ? 'F' : 'M';

        // AJOUT (2026-08-13, demande explicite) : support des senders Business
        // ('B', voir migration add_business_fields_to_senders_table) en plus des
        // senders Personnels ('P', comportement historique). Doc §V Create Sender :
        // un sender Business exige businessName/businessType/regiterBusinessDate/
        // comment/email, et idType doit être IMPÉRATIVEMENT "RCCM" (sinon erreur
        // DigitWace 3001 "A business transaction cannot be of any type other than
        // RCCM"). Les champs personnels (dateOfBirth/dateExpireId/civility/gender)
        // restent envoyés même côté Business : la doc les documente comme "du
        // gérant de l'entreprise" ("business manager"), pas seulement du particulier.
        $isBusiness = strtoupper((string) ($sender->sender_type ?? 'P')) === 'B';

        $payload = [
            'type' => $isBusiness ? 'B' : 'P',
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'address' => $sender->postal_code ?: ($sender->country ?: 'N/A'),
            // Doc §V : email requis pour un compte Business. $user->email est
            // auto-généré (phone@send-paz.com, voir transaction.page.ts::addUser),
            // pas une vraie adresse — on privilégie donc sender->email (saisie
            // réelle facultative, voir migration add_business_fields_to_senders_table)
            // si renseignée.
            'email' => $sender->email ?: ($user->email ?: null),
            'phone' => $user->phone_number,
            // FIX (2026-08-20, incident transaction #82) : DigitWace /sender/create
            // exige un code pays ISO2 ("The country must not be greater than 2
            // characters.") — $sender->country est stocké en clair côté mobile
            // (littéralement 'Congo', voir transaction.page.ts::addSender), jamais
            // en ISO2. On le résout désormais via PeexCorridors::list() (déjà la
            // source de vérité nom<->ISO2 utilisée ailleurs dans ce contrôleur).
            'country' => $this->countryNameToIso2($sender->country),
            // FIX (2026-08-21, blocage envoi transaction #96 vers la Chine) : 'city'
            // envoyait jusqu'ici le NOM DU PAYS ($sender->country, ex. "Congo") faute
            // de champ "ville expéditeur" dédié en base (voir commentaire historique
            // ci-dessus, même lacune que pour PawaPay, sendPawapayRemittance). WACEPAY
            // rejette ça avec la même erreur d'enum que idType ("This city does not
            // exist or is disabled. Please contact support") — un nom de pays n'est
            // jamais une ville valide. Le réseau d'agents TholadPay/Send-Paz opère à
            // ce jour uniquement depuis Congo-Brazzaville : on utilise donc
            // "Brazzaville" par défaut. À remplacer par un vrai champ ville
            // expéditeur (mobile + admin + migration senders) dès qu'un agent basé
            // ailleurs qu'à Brazzaville est enregistré — voir resolveSenderCity().
            'city' => $this->resolveSenderCity($sender),
            'gender' => $gender,
            'civility' => 'Single', // Pas de champ "situation matrimoniale" en base ; valeur par défaut acceptée par DigitWace.
            'idNumber' => $sender->cni_number,
            'idType' => $isBusiness ? 'RCCM' : $idType,
            'nationality' => $this->countryNameToIso2($sender->country),
            'zipcode' => $sender->postal_code,
            'dateOfBirth' => $sender->birth_date,
            'dateExpireId' => $sender->date_exp_id,
            'pep' => 0,
            // FIX (2026-08-20, incident transaction #82) : DigitWace /sender/create
            // exige aussi 'occupation' et 'state' ("field must be present"), sans
            // équivalent en base côté senders (aucune migration ne les capture).
            // Valeurs par défaut acceptées par DigitWace, même esprit que le
            // 'civility' => 'Single' déjà en dur ci-dessus, en attendant d'éventuels
            // vrais champs formulaire mobile/admin si DigitWace se montre plus
            // strict sur leur contenu à l'usage.
            'occupation' => $sender->occupation ?: 'N/A',
            'state' => $sender->postal_code ?: ($sender->country ?: 'N/A'),
        ];

        if ($isBusiness) {
            if (empty($sender->business_name) || empty($sender->business_type) || empty($sender->business_register_date)) {
                throw new \InvalidArgumentException('business_name, business_type et business_register_date sont requis pour un sender DigitWace de type Business (voir doc §V Create Sender).');
            }
            $payload['businessName'] = $sender->business_name;
            $payload['businessType'] = $sender->business_type;
            $payload['regiterBusinessDate'] = $sender->business_register_date;
            $payload['comment'] = $sender->business_comment ?: $sender->business_name;
            if (empty($payload['email'])) {
                throw new \InvalidArgumentException('email est requis pour un sender DigitWace de type Business (voir doc §V Create Sender).');
            }
        } else {
            // FIX (2026-08-20, incident transaction #82) : 'comment' est en fait
            // requis par DigitWace même pour un sender Personnel ('P'), pas
            // seulement Business comme le laissait supposer le commentaire doc §V
            // ci-dessus ("comment" listé parmi les champs Business) — l'erreur
            // 'errors.comment' est bien remontée par l'API même hors Business.
            $payload['comment'] = 'Envoi Send-Paz pour ' . trim($user->first_name . ' ' . $user->last_name);
        }

        $response = $this->digitwaceClient()->createSender($payload);
        $code = $response['sender']['Code'] ?? null;
        if (!$code) {
            throw new \RuntimeException('DigitWace /sender/create n\'a pas renvoyé de Code : ' . json_encode($response));
        }

        $sender->digitwace_code = $code;
        $sender->save();

        return $code;
    }

    /**
     * Crée le bénéficiaire DigitWace pour cette transaction (doc §VI). Un
     * nouveau bénéficiaire est recréé à chaque envoi (contrairement au
     * sender, pas de mise en cache : DigitWace ne documente pas de recherche
     * par téléphone/nom, et les infos bénéficiaire peuvent varier d'une
     * transaction à l'autre pour un même destinataire).
     *
     * idNumber/idType ne sont capturés nulle part dans le formulaire actuel
     * (Peex ne les demande pas) : receiver_id_number est donc requis
     * explicitement côté admin/mobile quand DigitWace est sélectionné (voir
     * send_transaction/send_bank_transaction ci-dessous), avec idType par
     * défaut 'PP' si non précisé.
     */
    private function createDigitwaceBeneficiary(Request $request, string $senderCode): array
    {
        $idNumber = $request->get('receiver_id_number');
        if (!$idNumber) {
            throw new \InvalidArgumentException('receiver_id_number est requis pour un envoi via DigitWace.');
        }

        // AJOUT (2026-08-13, demande explicite) : bénéficiaire Business ('B', voir
        // migration add_receiver_business_fields_to_transactions_table), en plus du
        // bénéficiaire Personnel ('P', comportement historique). Doc §VI Create
        // Beneficiary : un bénéficiaire Business exige businessName/businessType/
        // expire_date, et idType doit être IMPÉRATIVEMENT "RCCM" (même contrainte
        // que le sender — erreur DigitWace 3001 sinon). receiver_id_number sert
        // alors de numéro d'immatriculation (RCCM/SIRET/VAT) plutôt que de pièce
        // d'identité personnelle.
        $isBusiness = strtoupper((string) ($request->get('receiver_type') ?: 'P')) === 'B';

        // FIX (2026-08-20, incident transaction #90) : le formulaire admin
        // (resources/views/transactions/update.blade.php) propose 6 valeurs pour
        // "Type de pièce" (PP/CNI/PERMIS/NIU/RESIDENCE/CONSULAIRE) — copiées du
        // formulaire mobile SANS jamais avoir été confirmées par DigitWace au-delà
        // de PP/CNI (voir commentaire déjà présent dans ce blade). L'agent avait
        // sélectionné une de ces 4 valeurs non confirmées pour la transaction #90,
        // et DigitWace a rejeté l'envoi avec "This ID type does not exist or is
        // disabled." On normalise donc désormais vers PP/CNI uniquement (même
        // logique que ensureDigitwaceSenderCode() ci-dessus pour l'expéditeur) au
        // lieu de transmettre la valeur brute du formulaire.
        // FIX (2026-08-21, incidents #81/#82/#92/#93) : idem ensureDigitwaceSenderCode()
        // ci-dessus — 'CNI' n'est pas un idType WACEPAY valide, le code correct pour
        // une carte d'identité est 'CI' (confirmé par le support digitwace le
        // 2026-08-21).
        $idTypeRaw = strtoupper((string) $request->get('receiver_id_type'));
        $idType = ($idTypeRaw === '' || $idTypeRaw === 'PP') ? 'PP' : 'CI';

        // FIX (2026-08-22, blocage transaction #100 vers la France) : même bug
        // que celui déjà corrigé côté sender (voir resolveSenderCity() et
        // ensureDigitwaceSenderCode() ci-dessus), mais côté bénéficiaire cette
        // fois. Deux champs distincts en souffraient :
        //
        //  - 'city' : pour un virement bancaire, le mobile envoie
        //    systématiquement receiver_city = '' (voir transaction.page.ts —
        //    ce champ n'est renseigné que pour le Cash Pickup, "ville de
        //    retrait"). Le repli tombait donc sur $receiving_country, un nom
        //    de pays ("France") — jamais une ville valide pour WACEPAY, qui
        //    valide 'city' contre une liste fermée (confirmé par leur support
        //    le 22/08/2026, même mécanisme que idType). D'où l'erreur "This
        //    city does not exist or is disabled" sur /beneficiary/create.
        //    WACEPAY nous a explicitement indiqué d'utiliser "Any City" pour
        //    toute ville non couverte par une correspondance confirmée (nous
        //    n'avons aucune ville bénéficiaire confirmée à ce jour, contrairement
        //    à "Brazzaville" côté sender) — voir resolveSenderCity() pour le
        //    même raisonnement.
        //  - 'country' : envoyait le nom de pays brut ("France") au lieu d'un
        //    code ISO2, alors que DigitWace l'exige au format ISO2 pour
        //    /sender/create ("The country must not be greater than 2
        //    characters.", voir ensureDigitwaceSenderCode() ci-dessus) — même
        //    contrainte documentée pour /beneficiary/create. Non détecté avant
        //    ce jour car l'erreur 'city' était systématiquement renvoyée en
        //    premier par WACEPAY, masquant ce second problème. On réutilise
        //    countryNameToIso2() (déjà la source de vérité nom<->ISO2 dans ce
        //    contrôleur) au lieu de dupliquer une conversion.
        $payload = [
            'type' => $isBusiness ? 'B' : 'P',
            'firstName' => $request->get('receiver_first_name'),
            'lastName' => $request->get('receiver_last_name'),
            'address' => $request->get('receiver_address') ?: $request->get('receiving_country'),
            'phone' => $this->toInternationalPhone($request->get('receiver_phone')),
            'mobile' => $this->toInternationalPhone($request->get('receiver_phone')),
            'country' => $this->countryNameToIso2($request->get('receiving_country')),
            'city' => $request->get('receiver_city') ?: 'Any City',
            'email' => $request->get('receiver_email'),
            'idNumber' => $idNumber,
            'idType' => $isBusiness ? 'RCCM' : $idType,
            'sender_code' => $senderCode,
        ];

        // CORRECTIF (2026-08-20, incident transaction #81 vers la Chine) : le
        // correctif précédent (incident #90, voir historique git) rendait 'dob'
        // ET 'expire_date' obligatoires pour TOUT bénéficiaire DigitWace, en se
        // basant sur une erreur 422 observée pour UNE destination précise. Or la
        // doc officielle (WACEPAY INTEGRATION API SERVICE SPECIFICATION, §VI
        // Create Beneficiary) est plus nuancée :
        //   - expire_date : "Mandatory for business account" -> uniquement pour
        //     un bénéficiaire Business, pas Personnel.
        //   - dob : "optional required for personnal account (depend
        //     destination)" -> optionnel, DigitWace ne l'exige que pour
        //     CERTAINES destinations, pas toutes.
        // En les rendant obligatoires partout, le correctif #90 bloquait donc à
        // tort des envois vers des destinations qui n'en ont pas besoin (ex:
        // Chine, transaction #81, échec 404/500 avant même d'atteindre
        // DigitWace). On transmet désormais ces champs quand ils sont fournis,
        // sans bloquer localement pour les comptes Personnels : si DigitWace les
        // exige réellement pour une destination donnée, il renverra une erreur
        // 422 explicite (voir digitwaceErrorResponse()) que l'admin pourra
        // corriger au cas par cas via les champs receiver_dob/receiver_expire_date
        // du formulaire (voir update.blade.php), plutôt que d'être bloqué
        // systématiquement pour toutes les destinations.
        $dob = $request->get('receiver_dob');
        $expireDate = $request->get('receiver_expire_date');
        if ($isBusiness && empty($expireDate)) {
            throw new \InvalidArgumentException('receiver_expire_date est requis pour un bénéficiaire DigitWace de type Business (voir doc §VI Create Beneficiary).');
        }
        if (!empty($dob)) {
            $payload['dob'] = $dob;
        }
        if (!empty($expireDate)) {
            $payload['expire_date'] = $expireDate;
        }

        if ($isBusiness) {
            $businessName = $request->get('receiver_business_name');
            $businessType = $request->get('receiver_business_type');
            if (empty($businessName) || empty($businessType)) {
                throw new \InvalidArgumentException('receiver_business_name et receiver_business_type sont requis pour un bénéficiaire DigitWace de type Business (voir doc §VI Create Beneficiary).');
            }
            $payload['businessName'] = $businessName;
            $payload['businessType'] = $businessType;
        }

        $response = $this->digitwaceClient()->createBeneficiary($payload);
        $code = $response['beneficiary']['Code'] ?? null;
        if (!$code) {
            throw new \RuntimeException('DigitWace /beneficiary/create n\'a pas renvoyé de Code : ' . json_encode($response));
        }
        return ['code' => $code, 'raw' => $response];
    }

    /**
     * Résout le payerCode DigitWace pour un pays/devise/mode de livraison
     * donné (doc §VII Get Payer Code). Le "service" (opérateur mobile money
     * ou "B" pour dépôt bancaire) peut être imposé par l'appelant
     * (digitwace_service) ; à défaut, on interroge PayoutServiceCode (doc
     * §XIV) et on prend automatiquement le premier service correspondant au
     * mode demandé, pour ne pas obliger l'agent à choisir manuellement dans
     * le cas le plus courant (un seul opérateur dispo pour le pays/la devise).
     */
    private function resolveDigitwacePayerCode(string $country, string $currency, ?string $serviceHint, bool $forBank): array
    {
        // FIX (2026-08-22, vérification préventive USA/Côte d'Ivoire/Chine après
        // l'incident 'city' bénéficiaire de la transaction #100) : $country
        // arrive ici tel quel depuis $request->get('receiving_country'), qui
        // est le NOM COMPLET du pays ("France", "Côte d'Ivoire", "China" —
        // voir transaction.page.ts::goto(), shareService.country), jamais un
        // code ISO2. Or getBankList() ci-dessous (App\Libraries\DigitwaceClient)
        // déclare explicitement son paramètre pays comme "string $iso2", et le
        // script de diagnostic check_digitwace_config() plus bas dans ce
        // contrôleur teste getPayoutServiceCode() avec 'CI' (ISO2), jamais un
        // nom de pays complet — signe que ces deux endpoints DigitWace
        // attendent un ISO2, comme /sender/create et /beneficiary/create déjà
        // corrigés (voir ensureDigitwaceSenderCode/createDigitwaceBeneficiary
        // ci-dessus, même contrainte documentée : "The country must not be
        // greater than 2 characters."). On convertit donc systématiquement ici
        // via countryNameToIso2() (idempotent : un ISO2 déjà reçu ressort
        // inchangé), plutôt que d'attendre qu'un pays précis échoue en
        // production comme 'city' vient de le faire pour la France.
        $country = $this->countryNameToIso2($country);
        $client = $this->digitwaceClient();
        $service = $serviceHint;

        if (!$service) {
            $list = $client->getPayoutServiceCode($country, $currency)['data'] ?? [];

            // FIX (2026-08-24) : bug découvert via un test réel en production
            // (transaction #102, Côte d'Ivoire/XOF, virement Mobile vers Traoré
            // Martial, rejeté par WACEPAY avec l'erreur 2008 "Payer code is not
            // match"). Cause racine : PayoutServiceCode(CI, XOF) renvoie 7
            // entrées — B (BANKS DEPOSIT), C (CASH), MOMO (MTN MONEY), MTN (MTN
            // MOBILE MONEY), MV (MOOV), OM (ORANGE MONEY), WV (WAVE) — dans cet
            // ordre. L'ancienne logique ne distinguait que "banque" vs "pas
            // banque" : pour un virement Mobile ($forBank=false), elle prenait
            // la première entrée non-bancaire de la liste, qui se trouve être
            // 'C' (CASH) — un mode de livraison totalement différent, pas un
            // opérateur mobile money. Le payerCode obtenu pour 'C' ne
            // correspondait donc à rien de valide pour un envoi Mobile,
            // d'où le rejet WACEPAY.
            //
            // On distingue maintenant explicitement 3 catégories (bank / cash /
            // wallet) et on ne choisit automatiquement QUE s'il y a exactement
            // un candidat de la catégorie demandée. S'il y en a plusieurs (cas
            // Côte d'Ivoire : 5 opérateurs mobile money possibles), on refuse
            // de deviner lequel correspond au bénéficiaire — envoyer les fonds
            // vers le mauvais opérateur serait pire qu'une erreur bloquante —
            // et on demande explicitement à l'appelant de préciser
            // 'digitwace_service' parmi la liste retournée.
            $classify = function (array $entry): string {
                $code = strtoupper((string) ($entry['ServiceCode'] ?? ''));
                $name = strtoupper((string) ($entry['ServiceName'] ?? ''));
                if ($code === 'B' || stripos($name, 'BANK') !== false) {
                    return 'bank';
                }
                if ($code === 'C' || stripos($name, 'CASH') !== false) {
                    return 'cash';
                }
                return 'wallet';
            };
            $describe = function (array $entries): string {
                return implode(', ', array_map(
                    fn($e) => ($e['ServiceCode'] ?? '?') . ' (' . ($e['ServiceName'] ?? '?') . ')',
                    $entries
                ));
            };

            $wanted = $forBank ? 'bank' : 'wallet';
            $candidates = array_values(array_filter($list, fn($entry) => $classify($entry) === $wanted));

            if (count($candidates) === 1) {
                $service = $candidates[0]['ServiceCode'];
            } elseif (count($candidates) === 0) {
                $available = $list ? (' (services trouvés : ' . $describe($list) . ')') : '';
                throw new \RuntimeException("Aucun service DigitWace de type '$wanted' disponible pour $country/$currency$available.");
            } else {
                throw new \InvalidArgumentException(
                    "Plusieurs services DigitWace de type '$wanted' sont disponibles pour $country/$currency, "
                    . "precisez 'digitwace_service' parmi : " . $describe($candidates) . '.'
                );
            }
        }

        $payerResponse = $client->getPayerCode([
            'toCountry' => $country,
            'payoutService' => $service,
            'toCurrency' => $currency,
        ]);
        $payerCode = $payerResponse['transaction']['PayerCode'] ?? null;
        if (!$payerCode) {
            throw new \RuntimeException('DigitWace /transaction/payercode n\'a pas renvoyé de PayerCode : ' . json_encode($payerResponse));
        }

        return ['payerCode' => $payerCode, 'service' => $service];
    }

    /**
     * Champs "relation / reason / originFund" imposés par DigitWace (doc
     * §XVI/§XVII/§XVIII) — pas de valeur libre acceptée comme chez Peex
     * (purpose/fund_origin en texte libre). Doivent venir des listes
     * renvoyées par get_digitwace_relations/get_digitwace_reasons/
     * get_digitwace_origin_funds ci-dessous ; on exige donc explicitement
     * ces 3 paramètres plutôt que de deviner une valeur par défaut qui
     * risquerait d'être rejetée par l'API.
     */
    private function requireDigitwaceReferenceFields(Request $request): array
    {
        $relation = $request->get('relation');
        $reason = $request->get('reason');
        $originFund = $request->get('origin_fund');
        $missing = array_filter([
            'relation' => $relation,
            'reason' => $reason,
            'origin_fund' => $originFund,
        ], function ($v) {
            return empty($v);
        });
        if (!empty($missing)) {
            throw new \InvalidArgumentException('Champ(s) requis pour DigitWace manquant(s) : ' . implode(', ', array_keys($missing)));
        }
        return ['relation' => $relation, 'reason' => $reason, 'originFund' => $originFund];
    }

    /**
     * Liste des corridors mobile money supportés par Peex (source: doc officielle,
     * pas d'endpoint Peex pour ça — voir App\Libraries\PeexCorridors).
     * À utiliser côté app pour filtrer le sélecteur de pays avant même d'appeler Peex.
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_corridors(Request $request)
    {
        // AJOUT (2026-08-20) : PawaPay ne couvre (pour l'instant) que le Congo-
        // Brazzaville, contrairement à Peex (plusieurs dizaines de pays). Reste
        // par défaut sur PeexCorridors (compat ascendante : aucun appelant
        // existant n'envoie 'partner' à cet endpoint) et ne bascule sur la
        // liste PawaPay que si explicitement demandé.
        if ($this->resolvePartner($request) === 'pawapay') {
            return response()->json(['corridors' => PawapayCorridors::forApp()]);
        }
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
     * Peex étant longtemps resté l'unique partenaire, on renvoyait un
     * descripteur statique sans dépendre d'un appel réseau tiers pour cette
     * étape.
     *
     * MàJ 2026-08 : DigitWace est désormais un second partenaire possible,
     * choisi explicitement par l'agent/l'admin à l'étape de validation (voir
     * TransactionController côté admin/mobile). On renvoie donc le
     * descripteur correspondant au paramètre 'partner' reçu (toujours
     * statique, sans appel réseau : id/name servent uniquement à alimenter
     * corridor_id/nom_api en base, comme avant).
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_partner(Request $request){
        $partner = $this->resolvePartner($request);
        if ($partner === 'digitwace') {
            return response()->json([
                'client' => [
                    'id' => 2,
                    'name' => 'DigitWace',
                ],
            ]);
        }
        if ($partner === 'internal') {
            // AJOUT (2026-08-08) : corridor_id=3 identifie désormais les transferts
            // internes (voir Transaction.corridor_id/nom_api, même convention que
            // Peex=1/DigitWace=2). Contrairement aux deux autres, ce "client" n'est
            // rattaché à aucun corridor Peex/DigitWace réel — id/nom purement
            // internes à tholadpay.
            return response()->json([
                'client' => [
                    'id' => 3,
                    'name' => 'Interne',
                ],
            ]);
        }
        if ($partner === 'pawapay') {
            // AJOUT (2026-08-20) : corridor_id=4 — 3e partenaire payeur (voir doc
            // classe ci-dessus), même convention Peex=1/DigitWace=2/Interne=3.
            return response()->json([
                'client' => [
                    'id' => 4,
                    'name' => 'PawaPay',
                ],
            ]);
        }
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
     * GET /account/balance — solde réel du compte DigitWace (doc §XIX Balance),
     * miroir de get_peex_account() ci-dessus. Normalisé sur la même forme plate
     * ('solde'/'currency') que la réponse Peex consommée par AdminController::
     * index (dashboard admin), plutôt que d'exposer le format imbriqué brut de
     * DigitWace ({"status":2000,"data":{"Balance":...,"Currency":...}}).
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_digitwace_account(){
        try {
            $response = $this->digitwaceClient()->getBalance();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->digitwaceErrorResponse($e, 'get_digitwace_account');
        }

        $data = $response['data'] ?? [];
        return response()->json([
            'solde' => $data['Balance'] ?? null,
            'currency' => $data['Currency'] ?? null,
            'agency' => $data['Agency'] ?? null,
        ]);
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

        $countryCode = $request->get('receiving_country') ?? $request->get('country_code');
        if (!$countryCode) {
            return response()->json(['status' => 422, 'message' => 'receiving_country is required'], 422);
        }

        // DigitWace (doc §VIII/§IX/§X Wallet/CashPickup/Bank) ne documente
        // aucun endpoint de vérification préalable de compte mobile money —
        // seul Peex l'expose (clients/verify-wallet). On renvoie donc un
        // succès neutre ('valid' => null, ni vrai ni faux) pour laisser
        // passer l'étape 1 de validation ; le compte sera de toute façon
        // validé par DigitWace au moment de l'envoi réel (send_transaction).
        if ($this->resolvePartner($request) === 'digitwace') {
            return response()->json([
                'status' => 200,
                'valid' => null,
                'message' => "DigitWace ne propose pas de vérification préalable du compte ; il sera validé lors de l'envoi.",
            ]);
        }
        // AJOUT (2026-08-20) : PawaPay (doc "Initiate remittance") ne documente
        // aucun endpoint de vérification préalable de compte mobile money —
        // même situation que DigitWace ci-dessus. Sans cette branche, l'étape 1
        // de validation admin (TransactionController::update) tenterait à tort
        // de vérifier le compte via Peex pour une transaction PawaPay.
        if ($this->resolvePartner($request) === 'pawapay') {
            return response()->json([
                'status' => 200,
                'valid' => null,
                'message' => "PawaPay ne propose pas de vérification préalable du compte ; il sera validé lors de l'envoi.",
            ]);
        }
        if ($this->resolvePartner($request) === 'internal') {
            // AJOUT (2026-08-08) : transfert interne — aucun compte externe à
            // vérifier (le bénéficiaire retire en espèces avec un code + pièce
            // d'identité chez n'importe quel agent tholadpay du pays).
            return response()->json([
                'status' => 200,
                'valid' => null,
                'message' => "Transfert interne : aucune vérification de compte nécessaire.",
            ]);
        }

        $phone_number = $this->toInternationalPhone($rawPhone);

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
        // FIX (2026-08-20, incident Cameroun) : voir toLocalPhoneNumber() —
        // il manquait le retrait du '0' de tronc initial, d'où le rejet
        // Peex "Cameroon phone number must have 9 digits !" (10 chiffres
        // envoyés au lieu de 9).
        $localAccountNumber = $this->toLocalPhoneNumber($phone_number, $dial);

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
        // DigitWace ne documente pas non plus de vérification bancaire
        // préalable (seul /transaction/bank/create existe, doc §X) — mais
        // contrairement à Peex (501 ci-dessous, conservé tel quel pour ne
        // rien changer à son comportement existant), on renvoie ici un
        // succès neutre pour ne pas bloquer l'étape 1 de validation côté
        // admin (TransactionController::update() attend `status === 200`
        // pour avancer à l'étape suivante).
        if ($this->resolvePartner($request) === 'digitwace') {
            return response()->json([
                'status' => 200,
                'valid' => null,
                'message' => "DigitWace ne propose pas de vérification bancaire préalable ; l'IBAN/SWIFT sera validé lors de l'envoi.",
            ]);
        }
        if ($this->resolvePartner($request) === 'internal') {
            return response()->json([
                'status' => 200,
                'valid' => null,
                'message' => "Transfert interne : aucune vérification de compte nécessaire.",
            ]);
        }

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

        $fxrate = $from->rate / $to->rate;
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

        $partner = $this->resolvePartner($request);
        if ($partner === 'digitwace') {
            return $this->sendDigitwaceWalletTransaction($request, $user, $sender);
        }
        if ($partner === 'pawapay') {
            return $this->sendPawapayRemittance($request, $user, $sender);
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

        $partner = $this->resolvePartner($request);
        if ($partner === 'digitwace') {
            return $this->sendDigitwaceBankTransaction($request, $user, $sender);
        }
        // AJOUT (2026-08-20) : PawaPay n'est intégré ici que via l'API Remittance
        // (destinataire type "MMO" uniquement, voir sendPawapayRemittance) — pas
        // de virement bancaire. Rejet explicite plutôt que de laisser tomber
        // silencieusement sur Peex (même esprit que le rejet 'digitwace'-only de
        // send_cash_transaction ci-dessous).
        if ($partner === 'pawapay') {
            return response()->json([
                'status' => 422,
                'message' => 'Le virement bancaire n\'est pas disponible via PawaPay dans cette intégration (mobile money uniquement, voir send_transaction).',
            ], 422);
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
     * Orchestration DigitWace pour un envoi mobile money (doc §VIII Wallet) :
     * 1) sender (créé/réutilisé, voir ensureDigitwaceSenderCode)
     * 2) beneficiary (créé à chaque envoi, voir createDigitwaceBeneficiary)
     * 3) payerCode (résolu via PayoutServiceCode + GetPayerCode, voir
     *    resolveDigitwacePayerCode)
     * 4) POST /transaction/wallet/create
     * Réponse normalisée dans la même enveloppe stable que Peex
     * (status/track_id/reference) pour que admin/mobile n'aient pas besoin
     * de connaître le partenaire réellement utilisé.
     */
    private function sendDigitwaceWalletTransaction(Request $request, User $user, Sender $sender)
    {
        $phone = $this->toInternationalPhone($request->get('receiver_phone'));
        if (!$phone) {
            return response()->json(['status' => 422, 'message' => 'receiver_phone is required'], 422);
        }
        $receivingCountry = $request->get('receiving_country');
        if (!$receivingCountry) {
            return response()->json(['status' => 422, 'message' => 'receiving_country is required'], 422);
        }
        $fromCurrency = $request->get('sendingCurrency') ?: $request->get('currency') ?: 'XAF';

        try {
            $refFields = $this->requireDigitwaceReferenceFields($request);
            $senderCode = $this->ensureDigitwaceSenderCode($sender, $user);
            $beneficiary = $this->createDigitwaceBeneficiary($request, $senderCode);
            $payer = $this->resolveDigitwacePayerCode($receivingCountry, $fromCurrency, $request->get('digitwace_service'), false);

            $dial = PeexCorridors::list()[strtoupper($receivingCountry)]['dial'] ?? null;
            // FIX (2026-08-20) : voir toLocalPhoneNumber() — même correctif que
            // check_account_status ci-dessus (retrait du '0' de tronc initial).
            $localNumber = $this->toLocalPhoneNumber($phone, $dial);

            $trackId = $request->get('track_id') ?: ($request->get('reference') ?: (string) Str::uuid()) . '-' . uniqid();

            $response = $this->digitwaceClient()->createWalletTransaction([
                'payerCode' => $payer['payerCode'],
                'amountToPaid' => floatval($request->get('amount')),
                'senderCode' => $senderCode,
                'beneficiaryCode' => $beneficiary['code'],
                'fromCurrency' => $fromCurrency,
                'mobileReceiveNumber' => $localNumber,
                // FIX (2026-08-22) : idem conversion déjà appliquée à 'country'/
                // 'nationality' dans ensureDigitwaceSenderCode() ci-dessus —
                // $sender->country est stocké en clair ("Congo"), jamais en ISO2,
                // alors que DigitWace l'exige ("The country must not be greater
                // than 2 characters."). Cohérence sur tous les endpoints DigitWace
                // qui prennent un 'fromCountry'.
                'fromCountry' => $this->countryNameToIso2($sender->country),
                'originFund' => $refFields['originFund'],
                'reason' => $refFields['reason'],
                'relation' => $refFields['relation'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 422, 'message' => $e->getMessage()], 422);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->digitwaceErrorResponse($e, 'send_transaction');
        } catch (\Exception $e) {
            Log::error('[DigitWace send_transaction] ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }

        $normalized = $this->normalizeDigitwaceTransactionResponse($response, $trackId);
        // AJOUT (2026-08-08) : voir confirmDigitwaceTransaction() — étape obligatoire,
        // sans quoi la transaction reste bloquée en "WAITING CONFIRMATION" chez DigitWace.
        $normalized = array_merge($normalized, $this->confirmDigitwaceTransaction($normalized['reference']));
        return response()->json($normalized);
    }

    /**
     * Orchestration DigitWace pour un virement bancaire (doc §X Bank) —
     * miroir de sendDigitwaceWalletTransaction ci-dessus, avec en plus la
     * résolution du bankId DigitWace (obligatoire, doc §X) : soit fourni
     * explicitement par l'appelant ('bank_id', recommandé — voir
     * get_digitwace_bank_list ci-dessous pour permettre à l'agent de choisir
     * la bonne banque dans l'UI), soit résolu au mieux par correspondance de
     * nom sur la liste DigitWace du pays si seul 'bank_name' est fourni.
     */
    private function sendDigitwaceBankTransaction(Request $request, User $user, Sender $sender)
    {
        $bankIban = $request->get('bank_iban') ?: $request->get('bankaccountno');
        $bankSwift = $request->get('bank_swift') ?: $request->get('sortcode');
        if (!$bankIban) {
            return response()->json(['status' => 422, 'message' => 'bank_iban is required'], 422);
        }
        $receivingCountry = $request->get('receiving_country') ?: $request->get('to_country');
        if (!$receivingCountry) {
            return response()->json(['status' => 422, 'message' => 'receiving_country is required'], 422);
        }
        $fromCurrency = $request->get('sendingCurrency') ?: $request->get('currency') ?: 'XAF';
        $bankName = $request->get('bank_name') ?: $request->get('bankname');

        try {
            $refFields = $this->requireDigitwaceReferenceFields($request);
            $senderCode = $this->ensureDigitwaceSenderCode($sender, $user);
            $beneficiary = $this->createDigitwaceBeneficiary($request, $senderCode);
            $payer = $this->resolveDigitwacePayerCode($receivingCountry, $fromCurrency, $request->get('digitwace_service'), true);

            $bankId = $request->get('bank_id');
            if (!$bankId && $bankName) {
                // FIX (2026-08-22) : voir commentaire de resolveDigitwacePayerCode()
                // ci-dessus — getBankList() attend un ISO2 ('$iso2' dans sa
                // signature, DigitwaceClient::getBankList), pas $receivingCountry
                // brut ("France"/"United States"/"China").
                $banks = $this->digitwaceClient()->getBankList($this->countryNameToIso2($receivingCountry), $payer['payerCode'])['data'] ?? [];
                foreach ($banks as $bank) {
                    if (stripos($bank['BankName'] ?? '', $bankName) !== false) {
                        $bankId = $bank['BankID'];
                        break;
                    }
                }
            }
            if (!$bankId) {
                return response()->json([
                    'status' => 422,
                    'message' => "bank_id est requis pour DigitWace (voir get_digitwace_bank_list?country=$receivingCountry&payer_code={$payer['payerCode']}).",
                ], 422);
            }

            $trackId = $request->get('track_id') ?: ($request->get('reference') ?: (string) Str::uuid()) . '-' . uniqid();

            // AJOUT (2026-08-24) : ni doc §X Bank ni le payload createBankTransaction
            // n'ont de champ dédié pour la "banque correspondante" (ex. BIC
            // CHASDEFX pour un virement EUR vers Revolut Bank UAB) ou pour
            // l'adresse de l'agence bénéficiaire — cas rencontré sur le test réel
            // France/EUR (Pierre Eyidi Priso) préparé le 2026-08-24. En l'absence
            // de confirmation WACEPAY sur le champ à utiliser, on les regroupe en
            // best-effort dans 'bankBranch' (même logique que digitwace_callback
            // plus bas dans ce fichier : best-effort documenté en attendant le
            // vrai schéma). Un 'bank_branch' explicite dans la requête reste
            // toujours prioritaire.
            $bankBranch = $request->get('bank_branch');
            if (!$bankBranch) {
                $correspondentBic = $request->get('correspondent_bank_bic');
                $bankAddress = $request->get('bank_address');
                $bankBranch = trim(implode(' - ', array_filter([
                    $bankAddress,
                    $correspondentBic ? "Correspondent BIC: $correspondentBic" : null,
                ])));
            }

            $response = $this->digitwaceClient()->createBankTransaction([
                'payerCode' => $payer['payerCode'],
                'amountToPaid' => floatval($request->get('amount')),
                'senderCode' => $senderCode,
                // FIX (2026-08-24) : confirmé par un test réel en production
                // (virement France/EUR, Pierre Eyidi Priso, transaction #101,
                // voir logs Railway 13:01:23) — WACEPAY /transaction/bank/create
                // rejette la requête en 422 "Error validation data" /
                // "sender_code_transaction: The sender code transaction field
                // must be present." si CE champ (en plus de 'senderCode', qui
                // reste documenté §X et toujours envoyé ci-dessus) est absent.
                // Champ totalement absent de la doc v2.0.0 (§X Bank) dans ses
                // deux versions (originale et republiée) — encore un écart
                // doc/API comme celui de PayoutServiceCode. On envoie donc la
                // même valeur sous les deux clés en attendant confirmation
                // WACEPAY sur la différence réelle entre les deux.
                'sender_code_transaction' => $senderCode,
                'fromCurrency' => $fromCurrency,
                'beneficiaryCode' => $beneficiary['code'],
                'bankAccount' => $bankIban,
                'bankName' => $bankName ?: 'N/A',
                'bankId' => (int) $bankId,
                'bankSwCode' => $bankSwift ?: 'N/A',
                'bankBranch' => $bankBranch,
                // FIX (2026-08-22) : idem conversion déjà appliquée à 'country'/
                // 'nationality' dans ensureDigitwaceSenderCode() ci-dessus —
                // $sender->country est stocké en clair ("Congo"), jamais en ISO2,
                // alors que DigitWace l'exige ("The country must not be greater
                // than 2 characters."). Cohérence sur tous les endpoints DigitWace
                // qui prennent un 'fromCountry'.
                'fromCountry' => $this->countryNameToIso2($sender->country),
                'comment' => $trackId,
                'originFund' => $refFields['originFund'],
                'reason' => $refFields['reason'],
                'relation' => $refFields['relation'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 422, 'message' => $e->getMessage()], 422);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->digitwaceErrorResponse($e, 'send_bank_transaction');
        } catch (\Exception $e) {
            Log::error('[DigitWace send_bank_transaction] ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }

        $normalized = $this->normalizeDigitwaceTransactionResponse($response, $trackId);
        $normalized = array_merge($normalized, $this->confirmDigitwaceTransaction($normalized['reference']));
        return response()->json($normalized);
    }

    /**
     * POST /transaction/cash/create — retrait en espèces (doc §IX CashPickup).
     * Capacité propre à DigitWace : Peex ne propose pas ce mode de livraison,
     * cet endpoint renvoie donc une erreur explicite si 'partner' != 'digitwace'
     * plutôt que de silencieusement tenter d'envoyer via Peex.
     * @return \Illuminate\Http\JsonResponse
     */
    public function send_cash_transaction(Request $request)
    {
        if ($this->resolvePartner($request) !== 'digitwace') {
            return response()->json([
                'status' => 422,
                'message' => 'Le retrait en espèces (cash pickup) n\'est disponible que via le partenaire DigitWace.',
            ], 422);
        }

        $user = User::find($request->get('user_id'));
        $sender = Sender::find($request->get('sender_id'));
        if (!$user || !$sender) {
            return response()->json(['status' => 422, 'message' => 'user or sender not found'], 422);
        }

        $phone = $this->toInternationalPhone($request->get('receiver_phone'));
        if (!$phone) {
            return response()->json(['status' => 422, 'message' => 'receiver_phone is required'], 422);
        }
        $receivingCountry = $request->get('receiving_country');
        $receivingCity = $request->get('receiver_city');
        $question = $request->get('security_question');
        $responseAnswer = $request->get('security_answer');
        if (!$receivingCountry || !$receivingCity || !$question || !$responseAnswer) {
            return response()->json([
                'status' => 422,
                'message' => 'receiving_country, receiver_city, security_question et security_answer sont requis pour un retrait en espèces DigitWace.',
            ], 422);
        }
        $fromCurrency = $request->get('sendingCurrency') ?: $request->get('currency') ?: 'XAF';
        $toCurrency = $request->get('receivingCurrency') ?: $fromCurrency;

        // AJOUT (2026-08-13, demande explicite) : doc §IX CashPickup / erreur
        // DigitWace 1008 — "Only P2P operation is supported for Cash PickUp
        // transaction type". On bloque donc explicitement tout Cash Pickup dès
        // que le sender ou le bénéficiaire est Business, plutôt que de laisser
        // DigitWace le rejeter en 422 après création sender/bénéficiaire.
        $businessType = $this->resolveDigitwaceBusinessType($sender->sender_type, $request->get('receiver_type'));
        if ($businessType !== 'p2p') {
            return response()->json([
                'status' => 422,
                'message' => 'Le retrait en espèces (Cash Pickup) DigitWace n\'est disponible qu\'en P2P (particulier à particulier) — ce transfert est de type ' . strtoupper($businessType) . '. Utilisez Wallet ou Bank pour ce corridor.',
            ], 422);
        }

        try {
            $refFields = $this->requireDigitwaceReferenceFields($request);
            $senderCode = $this->ensureDigitwaceSenderCode($sender, $user);
            $beneficiary = $this->createDigitwaceBeneficiary($request, $senderCode);

            $collection = $this->digitwaceClient()->getCollectionCode([
                'toCountry' => $receivingCountry,
                'fromCurrency' => $fromCurrency,
                'toCurrency' => $toCurrency,
                'payercode' => $request->get('digitwace_payer_code'),
            ]);
            $collectionCode = $request->get('payout_collection_code')
                ?: ($collection['messages'][0]['CollectionCode'] ?? null);
            if (!$collectionCode) {
                return response()->json([
                    'status' => 422,
                    'message' => 'Aucun point de retrait (collection code) DigitWace trouvé pour cette ville ; précisez payout_collection_code explicitement.',
                ], 422);
            }

            // FIX (2026-08-15) : 'service' est un champ obligatoire de
            // POST /transaction/cash/create (doc §IX CashPickup — "service or
            // wawllet to use refer in list to section services"), jamais envoyé
            // jusqu'ici (risque de rejet DigitWace 1001/1003 "Invalid
            // parameters"/"Data validation error" sur TOUT retrait en espèces).
            // Même logique de résolution que resolveDigitwacePayerCode() pour
            // wallet/bank : override explicite possible via 'digitwace_service',
            // sinon on interroge PayoutServiceCode (doc §XIV) et on prend le
            // premier service disponible pour ce pays/devise à défaut de mieux
            // — DigitWace ne documente pas de filtre "service_type=cash" dans la
            // réponse PayoutServiceCode pour distinguer précisément un service de
            // retrait espèces d'un autre mode.
            $cashService = $request->get('digitwace_service');
            if (!$cashService) {
                $serviceList = $this->digitwaceClient()->getPayoutServiceCode($receivingCountry, $toCurrency)['data'] ?? [];
                $cashService = $serviceList[0]['ServiceCode'] ?? null;
                if (!$cashService) {
                    return response()->json([
                        'status' => 422,
                        'message' => "Aucun service DigitWace disponible pour $receivingCountry/$toCurrency ; précisez digitwace_service explicitement.",
                    ], 422);
                }
            }

            $trackId = $request->get('track_id') ?: ($request->get('reference') ?: (string) Str::uuid()) . '-' . uniqid();

            $response = $this->digitwaceClient()->createCashTransaction([
                'payoutCollectionCode' => $collectionCode,
                'toCurrency' => $toCurrency,
                'toCity' => $receivingCity,
                'toCountry' => $receivingCountry,
                'amountToPaid' => floatval($request->get('amount')),
                'service' => $cashService,
                'senderCode' => $senderCode,
                'beneficiaryCode' => $beneficiary['code'],
                'fromCurrency' => $fromCurrency,
                'mobileTopup' => $phone,
                // FIX (2026-08-22) : idem conversion déjà appliquée à 'country'/
                // 'nationality' dans ensureDigitwaceSenderCode() ci-dessus —
                // $sender->country est stocké en clair ("Congo"), jamais en ISO2,
                // alors que DigitWace l'exige ("The country must not be greater
                // than 2 characters."). Cohérence sur tous les endpoints DigitWace
                // qui prennent un 'fromCountry'.
                'fromCountry' => $this->countryNameToIso2($sender->country),
                'originFund' => $refFields['originFund'],
                'reason' => $refFields['reason'],
                'question' => $question,
                'response' => $responseAnswer,
                'relation' => $refFields['relation'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 422, 'message' => $e->getMessage()], 422);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->digitwaceErrorResponse($e, 'send_cash_transaction');
        } catch (\Exception $e) {
            Log::error('[DigitWace send_cash_transaction] ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }

        $normalized = $this->normalizeDigitwaceTransactionResponse($response, $trackId);
        $normalized = array_merge($normalized, $this->confirmDigitwaceTransaction($normalized['reference']));
        return response()->json($normalized);
    }

    /**
     * Normalise une réponse DigitWace (wallet/bank/cash create, formats
     * "reference" légèrement différents selon l'endpoint — voir doc §VIII/
     * §IX/§X) dans la même enveloppe stable que Peex (status/track_id/
     * reference), consommée telle quelle par admin/mobile.
     */
    private function normalizeDigitwaceTransactionResponse(array $response, string $fallbackTrackId): array
    {
        $tx = $response['transaction'] ?? [];
        $reference = $tx['reference'] ?? $fallbackTrackId;
        $digitwaceStatus = $tx['status'] ?? ($response['stauts'] ?? ($response['status'] ?? null));
        $response['status'] = 200;
        $response['track_id'] = $reference;
        $response['reference'] = $reference;
        $response['digitwace_status'] = $digitwaceStatus;
        return $response;
    }

    /**
     * AJOUT (2026-08-08) : POST /transaction/confirm (doc §XII) — étape
     * OBLIGATOIRE après wallet/create, bank/create ou cash/create. Confirmée
     * par la doc elle-même : la réponse de getStatus() juste après une
     * création montre "Status": "WAITING CONFIRMATION" (pas "PAID" ni
     * "PROCESSING"), et le code succès 204 ("Confirm transaction") est décrit
     * comme "generated when the transaction not confirm by Partner. You may
     * go ahead and retry confirm API" — sans cet appel, une transaction créée
     * chez DigitWace reste bloquée indéfiniment en attente de confirmation et
     * n'est jamais réellement payée. Ni sendDigitwaceWalletTransaction, ni
     * sendDigitwaceBankTransaction, ni send_cash_transaction ne l'appelaient
     * jusqu'ici — corrigé en l'appelant systématiquement juste après un
     * create réussi, dans les 3 méthodes.
     *
     * On tente 1 essai supplémentaire en cas d'échec (la doc suggère
     * explicitement de "retry confirm API" sur le code 204), sans jamais faire
     * échouer la réponse globale : la transaction EXISTE déjà chez DigitWace à
     * ce stade (create a réussi) ; si confirm échoue malgré la retry, on le
     * signale clairement dans la réponse (confirm_status/confirm_message)
     * plutôt que de perdre l'info en silence — à surveiller côté admin/mobile,
     * qui peuvent relancer la confirmation via check_transaction_status +
     * un futur bouton "Confirmer" si ce cas se présente en pratique.
     *
     * FIX (2026-08-15) : cette méthode traitait jusqu'ici TOUT appel
     * /transaction/confirm qui répondait sans exception Guzzle (donc en HTTP
     * 2xx) comme "confirmed", sans jamais lire le champ status/stauts du corps
     * JSON. Or le code succès 204 décrit ci-dessus ("transaction not confirm
     * by Partner") arrive très vraisemblablement en HTTP 200 (pas en erreur),
     * exactement comme pour getStatus (doc §XI, exemple avec "status": 204 et
     * transaction.Status: "WAITING CONFIRMATION"). Avec l'ancien code, ce cas
     * était silencieusement compté comme un succès : l'admin pouvait afficher
     * "paiement effectué avec succès" alors que la transaction restait
     * bloquée en attente chez DigitWace.
     *
     * On considère désormais confirmée uniquement une réponse dont le
     * status/stauts est un code de succès "définitif" documenté (2000, 200,
     * 201 — "Data with reference ... is confirm successfully" dans l'exemple
     * officiel §XII utilise "status": 2000). Tout le reste (204 explicitement,
     * mais aussi tout code absent/inattendu — on ne prend pas le risque de
     * deviner) déclenche la retry déjà prévue, puis remonte 'uncertain' plutôt
     * que 'confirmed' pour que l'admin/mobile ne considèrent pas à tort la
     * transaction comme définitivement payée.
     */
    private const DIGITWACE_CONFIRM_SUCCESS_CODES = [2000, 200, 201, '2000', '200', '201'];

    private function confirmDigitwaceTransaction(string $reference): array
    {
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $result = $this->digitwaceClient()->confirm($reference);
                $message = $result['messages'] ?? $result['message'] ?? null;
                $code = $result['status'] ?? $result['stauts'] ?? null;

                if (in_array($code, self::DIGITWACE_CONFIRM_SUCCESS_CODES, true)) {
                    return ['confirm_status' => 'confirmed', 'confirm_message' => $message, 'confirm_code' => $code];
                }

                Log::warning('[DigitWace confirm] tentative ' . $attempt . ' pour ' . $reference
                    . ' : réponse sans exception mais code de statut non-confirmé (' . json_encode($code)
                    . ') — message : ' . $message);
                if ($attempt === 2) {
                    return [
                        'confirm_status' => 'uncertain',
                        'confirm_message' => $message ?: 'DigitWace n\'a pas confirmé la transaction après 2 tentatives (code : ' . json_encode($code) . '). Vérifier via getStatus avant de considérer la transaction comme payée.',
                        'confirm_code' => $code,
                    ];
                }
            } catch (\Exception $e) {
                Log::warning('[DigitWace confirm] tentative ' . $attempt . ' échouée pour ' . $reference . ' : ' . $e->getMessage());
                if ($attempt === 2) {
                    return ['confirm_status' => 'failed', 'confirm_message' => $e->getMessage()];
                }
            }
        }
        return ['confirm_status' => 'failed', 'confirm_message' => 'Échec inattendu'];
    }

    /**
     * Orchestration PawaPay pour un envoi mobile money via l'API Remittance
     * (POST /v2/remittances, doc "Initiate remittance"). Contrairement à
     * Peex/DigitWace, un seul appel suffit (pas de création préalable de
     * sender/beneficiary côté PawaPay) : tout le KYC expéditeur + destinataire
     * est envoyé dans la même requête.
     *
     * Champs requis côté appelant (en plus de receiver_phone/receiving_country/
     * amount déjà utilisés par Peex/DigitWace) :
     *   - pawapay_operator ('AIRTEL' ou 'MTN', voir PawapayCorridors)
     *   - pawapay_purpose_of_funds / pawapay_source_of_funds (enums stricts,
     *     voir requirePawapayReferenceFields)
     *   - receiver_first_name / receiver_last_name
     * receiving_country doit être un corridor PawapayCorridors supporté (CG
     * uniquement au moment de l'écriture de ce code).
     */
    private function sendPawapayRemittance(Request $request, User $user, Sender $sender)
    {
        $phone = $this->toInternationalPhone($request->get('receiver_phone'));
        if (!$phone) {
            return response()->json(['status' => 422, 'message' => 'receiver_phone is required'], 422);
        }

        $receivingCountry = strtoupper((string) $request->get('receiving_country'));
        if (!PawapayCorridors::isSupported($receivingCountry)) {
            return response()->json([
                'status' => 422,
                'message' => "Le pays $receivingCountry n'est pas un corridor mobile money supporté par PawaPay dans cette intégration.",
                'supported_corridors' => array_keys(PawapayCorridors::list()),
            ], 422);
        }

        $provider = PawapayCorridors::resolveProvider($receivingCountry, $request->get('pawapay_operator'));
        if (!$provider) {
            return response()->json([
                'status' => 422,
                'message' => "pawapay_operator est requis et doit être un des opérateurs supportés pour $receivingCountry : "
                    . implode(', ', array_keys(PawapayCorridors::list()[$receivingCountry]['operators'] ?? [])),
            ], 422);
        }

        $receiverFirstName = $request->get('receiver_first_name');
        $receiverLastName = $request->get('receiver_last_name');
        if (!$receiverFirstName || !$receiverLastName) {
            return response()->json(['status' => 422, 'message' => 'receiver_first_name et receiver_last_name sont requis pour PawaPay.'], 422);
        }

        try {
            $refFields = $this->requirePawapayReferenceFields($request);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['status' => 422, 'message' => $e->getMessage()], 422);
        }

        $currency = PawapayCorridors::currency($receivingCountry) ?: 'XAF';
        // NB (doc Providers) : XAF ne supporte pas les décimales chez PawaPay
        // pour AIRTEL_COG/MTN_MOMO_COG — on arrondit donc le montant à payer.
        $amount = (string) (int) round(floatval($request->get('amount')));

        $sendingCurrency = $request->get('sendingCurrency') ?: $request->get('currency') ?: $currency;
        $sendingAmount = $request->get('sendingAmount') ?: $amount;

        $trackId = $request->get('track_id') ?: ($request->get('reference') ?: (string) Str::uuid()) . '-' . uniqid();
        $remittanceId = (string) Str::uuid();

        $payload = [
            'remittanceId' => $remittanceId,
            'amount' => $amount,
            'currency' => $currency,
            'recipient' => [
                'type' => 'MMO',
                'accountDetails' => [
                    'phoneNumber' => ltrim($phone, '+'),
                    'provider' => $provider,
                ],
                'recipientDetails' => [
                    'firstName' => $receiverFirstName,
                    'lastName' => $receiverLastName,
                ],
            ],
            'sender' => [
                'transactionDetails' => [
                    'transactionReference' => $trackId,
                    'originalAmount' => (string) $sendingAmount,
                    'originalCurrency' => $sendingCurrency,
                    'buyFxRate' => (string) ($request->get('fxrate') ?: '1'),
                    'senderFees' => (string) ($request->get('fees') ?: '0'),
                    'purposeOfFunds' => $refFields['purposeOfFunds'],
                    'sourceOfFunds' => $refFields['sourceOfFunds'],
                ],
                'senderDetails' => [
                    'firstName' => $user->first_name,
                    'lastName' => $user->last_name,
                    // La doc PawaPay ne précise pas le format exact attendu pour
                    // "nationality" (alpha-2/alpha-3/nom complet) — on envoie le
                    // code pays local tel que stocké (Sender::country, alpha-2,
                    // ex: "CG"), à ajuster si PawaPay le rejette une fois le
                    // sandbox disponible (voir note en tête de classe).
                    'nationality' => $sender->country,
                    'phoneNumber' => ltrim((string) $this->toInternationalPhone($user->phone_number), '+'),
                    'address' => [
                        // Pas de champ "adresse expéditeur" dédié en base (même
                        // lacune que DigitWace, voir ensureDigitwaceSenderCode) —
                        // on retombe sur le code postal ou, à défaut, le pays.
                        'addressLine' => $sender->postal_code ?: ($sender->country ?: 'N/A'),
                        'postalCode' => $sender->postal_code ?: 'N/A',
                        // FIX (2026-08-21) : même bug que DigitWace/ensureDigitwaceSenderCode
                        // ci-dessus (nom de pays envoyé comme ville) — corrigé par cohérence
                        // via le même helper, même si le sandbox PawaPay n'est pas encore
                        // testé à ce jour (voir note en tête de classe).
                        'city' => $this->resolveSenderCity($sender),
                        'country' => PawapayCorridors::iso3($sender->country) ?: (string) $sender->country,
                    ],
                    'identification' => [
                        'type' => $this->resolvePawapayIdentificationType($sender->type_id),
                        'number' => $sender->cni_number,
                    ],
                ],
            ],
            'metadata' => [
                ['fieldName' => 'trackId', 'fieldValue' => $trackId],
            ],
        ];

        try {
            $response = $this->pawapayClient()->initiateRemittance($payload);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->pawapayErrorResponse($e, 'send_transaction');
        } catch (\Exception $e) {
            Log::error('[PawaPay send_transaction] ' . $e->getMessage());
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }

        return response()->json($this->normalizePawapayRemittanceResponse($response, $trackId));
    }

    /**
     * Normalise une réponse PawaPay (POST /v2/remittances) dans la même
     * enveloppe stable (status/track_id/reference) que Peex/DigitWace.
     *
     * IMPORTANT : "status" => 200 ici signifie uniquement que PawaPay a
     * ACCEPTÉ la demande (pawapay_status = ACCEPTED) — pas que l'argent est
     * déjà chez le bénéficiaire (traitement asynchrone, voir
     * check_transaction_status / pawapay_callback ci-dessous). Un statut
     * REJECTED est en revanche remonté comme une erreur 422 explicite plutôt
     * que comme un faux succès.
     */
    private function normalizePawapayRemittanceResponse(array $response, string $fallbackTrackId): array
    {
        $remittanceId = $response['remittanceId'] ?? null;
        $pawapayStatus = $response['status'] ?? null;

        if ($pawapayStatus === 'REJECTED') {
            return [
                'status' => 422,
                'message' => $response['failureReason']['failureMessage'] ?? 'Virement rejeté par PawaPay.',
                'pawapay_status' => $pawapayStatus,
                'track_id' => $fallbackTrackId,
                'reference' => $remittanceId ?: $fallbackTrackId,
            ];
        }

        $response['status'] = 200;
        $response['track_id'] = $fallbackTrackId;
        $response['reference'] = $remittanceId ?: $fallbackTrackId;
        $response['pawapay_status'] = $pawapayStatus;
        return $response;
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function pawapay_callback(Request $request)
    {
        // AJOUT (2026-08-20) : point d'entrée pour le callback webhook PawaPay
        // (doc "Remittance callback"), configuré côté Dashboard PawaPay ->
        // Callback URLs (une seule URL pour tous les types de notification,
        // voir doc). Contrairement à Peex/DigitWace (statut consulté
        // uniquement à la demande via check_transaction_status), PawaPay pousse
        // les changements de statut ici de façon asynchrone.
        //
        // NON IMPLÉMENTÉ pour l'instant : la vérification de signature
        // RFC-9421 (headers Signature/Signature-Input/Content-Digest, voir doc
        // "Remittance callback") — le corps JSON exact renvoyé par PawaPay
        // n'a pas pu être confirmé dans la doc publique consultée le
        // 2026-08-20 (voir note en tête de classe). À faire avant mise en
        // production : (1) confirmer le schéma exact du payload avec le
        // sandbox, (2) implémenter la vérification de signature si "Only
        // accept signed requests" est activé côté dashboard PawaPay, pour
        // s'assurer qu'un tiers ne peut pas falsifier un statut "COMPLETED".
        $payload = $request->all();
        Log::info('[PawaPay callback] payload reçu : ' . json_encode($payload));

        $remittanceId = $payload['remittanceId'] ?? ($payload['data']['remittanceId'] ?? null);
        $status = $payload['status'] ?? ($payload['data']['status'] ?? null);

        if ($remittanceId) {
            $transaction = Transaction::where('reference', $remittanceId)->first();
            if ($transaction) {
                $transaction->etat_transac = $status;
                $transaction->save();
            } else {
                Log::warning('[PawaPay callback] aucune transaction locale trouvée pour remittanceId=' . $remittanceId);
            }
        }

        return response()->json(['status' => 200]);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function digitwace_callback(Request $request)
    {
        // AJOUT (2026-08-20) : point d'entrée pour l'"API de Notification"
        // WACEPAY/DigitWace, demandée par le support DigitWace (email du
        // 2026-08-20 : "merci de nous transmettre l'URL... générée par votre
        // équipe technique").
        //
        // IMPORTANT — À CONFIRMER : le PDF transmis avec cette demande
        // ("WACEPAY INTEGRATION API SERVICE SPECIFICATION", v2.0.0) est
        // IDENTIQUE à celui déjà utilisé pour construire DigitwaceClient — son
        // sommaire va de I. Introduction à XIX. Balance et NE CONTIENT AUCUNE
        // section Notification/SMS, aucun schéma de payload, aucun mécanisme
        // de signature/authentification pour ce webhook. Ce n'est donc PAS le
        // "guide de configuration des APIs de Notification" annoncé par le
        // support — probablement une pièce jointe manquante ou erronée de leur
        // côté. Cette méthode fait donc du best-effort à partir des seuls noms
        // de champs confirmés ailleurs dans la doc (reference/Reference — voir
        // §XI getStatus, §XII confirm — et Status — voir §XI, valeurs
        // possibles : PENDING, WAITING CONFIRMATION, PROCESSING, LOCKED, PAID,
        // CANCEL) : elle journalise tout payload reçu tel quel (pour pouvoir
        // reconstituer le vrai schéma une fois un exemple réel reçu), puis met
        // à jour la transaction locale correspondante si elle reconnaît un
        // champ de référence. AUCUNE vérification d'authenticité n'est faite
        // (le PDF ne documente ni secret partagé ni signature pour ce
        // callback) : à durcir dès que WACEPAY confirme le mécanisme prévu,
        // avant mise en production.
        $payload = $request->all();
        Log::info('[DigitWace callback] payload reçu : ' . json_encode($payload));

        $reference = $payload['reference']
            ?? $payload['Reference']
            ?? $payload['transaction_reference']
            ?? ($payload['transaction']['reference'] ?? null)
            ?? ($payload['transaction']['Reference'] ?? null);
        $status = $payload['status']
            ?? $payload['Status']
            ?? $payload['stauts']
            ?? ($payload['transaction']['Status'] ?? null)
            ?? ($payload['transaction']['status'] ?? null);

        if ($reference) {
            $transaction = Transaction::where('reference', $reference)->first();
            if ($transaction) {
                $transaction->etat_transac = $status;
                $transaction->save();
            } else {
                Log::warning('[DigitWace callback] aucune transaction locale trouvée pour reference=' . $reference);
            }
        } else {
            Log::warning('[DigitWace callback] aucun champ de référence reconnu dans le payload — schéma non confirmé, voir commentaire ci-dessus.');
        }

        return response()->json(['status' => 200]);
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

        // GET /transaction/status/{reference} (doc §XI) — DigitWace determine le
        // partenaire d'origine via 'partner', ou via 'client_id'/corridor_id=2
        // envoyé par les appelants existants (admin checkStatusOfTransaction,
        // qui connaît déjà le partenaire réellement utilisé à l'envoi, stocké
        // sur transactions.corridor_id/nom_api).
        $clientId = $request->get('client_id');
        $partner = $this->resolvePartner($request);
        $isDigitwace = $partner === 'digitwace' || (string) $clientId === '2';
        if ($isDigitwace) {
            try {
                $response = $this->digitwaceClient()->getStatus($trackId);
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                return $this->digitwaceErrorResponse($e, 'check_transaction_status');
            }
            return response()->json($response);
        }

        // AJOUT (2026-08-20) : PawaPay — GET /v2/remittances/{remittanceId} (doc
        // "Check remittance status"). $trackId doit ici être le remittanceId
        // PawaPay renvoyé par sendPawapayRemittance (exposé comme 'reference'
        // dans sa réponse normalisée), pas le track_id interne — même logique
        // que DigitWace ci-dessus, qui utilise sa propre 'reference'.
        $isPawapay = $partner === 'pawapay' || (string) $clientId === '4';
        if ($isPawapay) {
            try {
                $response = $this->pawapayClient()->getRemittanceStatus($trackId);
            } catch (\GuzzleHttp\Exception\RequestException $e) {
                return $this->pawapayErrorResponse($e, 'check_transaction_status');
            }
            return response()->json($response);
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

    // ------------------------------------------------------------------
    // Référentiels DigitWace — utilisés par les sélecteurs admin/mobile
    // affichés uniquement quand le partenaire "DigitWace" est choisi (voir
    // requireDigitwaceReferenceFields ci-dessus : ces valeurs, contrairement
    // à Peex, ne sont pas du texte libre mais doivent venir de ces listes).
    // ------------------------------------------------------------------

    /**
     * GET /transaction/relation (doc §XVI) — liste des relations
     * sender/bénéficiaire acceptées par DigitWace.
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_digitwace_relations(Request $request)
    {
        try {
            $response = $this->digitwaceClient()->getRelation();
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->digitwaceErrorResponse($e, 'get_digitwace_relations');
        }
        return response()->json(['status' => 200, 'data' => $response]);
    }

    /**
     * GET /transaction/reason/{businessType} (doc §XVIII).
     *
     * FIX (2026-08-13, demande explicite) : 'business_type' n'est plus figé à
     * 'p2p'. L'appelant peut soit le préciser explicitement ('business_type'),
     * soit laisser ce endpoint le calculer à partir de 'sender_type' et
     * 'receiver_type' (P/B chacun — voir resolveDigitwaceBusinessType), avec
     * repli sur 'p2p' si rien n'est fourni (compat ascendante).
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_digitwace_reasons(Request $request)
    {
        $businessType = $request->get('business_type')
            ?: $this->resolveDigitwaceBusinessType($request->get('sender_type'), $request->get('receiver_type'));
        try {
            $response = $this->digitwaceClient()->getReason($businessType);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->digitwaceErrorResponse($e, 'get_digitwace_reasons');
        }
        return response()->json(['status' => 200, 'data' => $response]);
    }

    /**
     * GET /transaction/origin_fund/{businessType} (doc §XVII).
     * Même logique de résolution de 'business_type' que get_digitwace_reasons
     * ci-dessus (voir commentaire, FIX 2026-08-13).
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_digitwace_origin_funds(Request $request)
    {
        $businessType = $request->get('business_type')
            ?: $this->resolveDigitwaceBusinessType($request->get('sender_type'), $request->get('receiver_type'));
        try {
            $response = $this->digitwaceClient()->getOriginFund($businessType);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->digitwaceErrorResponse($e, 'get_digitwace_origin_funds');
        }
        return response()->json(['status' => 200, 'data' => $response]);
    }

    /**
     * POST /transaction/bank/list (doc §XIII) — nécessite d'abord un
     * payerCode, résolu automatiquement ici si non fourni (voir
     * resolveDigitwacePayerCode) pour permettre à l'UI de n'avoir à fournir
     * que le pays.
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_digitwace_bank_list(Request $request)
    {
        $country = $request->get('country');
        if (!$country) {
            return response()->json(['status' => 422, 'message' => 'country is required'], 422);
        }
        $currency = $request->get('currency') ?: 'XAF';
        $payerCode = $request->get('payer_code');

        try {
            if (!$payerCode) {
                $payerCode = $this->resolveDigitwacePayerCode($country, $currency, null, true)['payerCode'];
            }
            // FIX (2026-08-22) : voir resolveDigitwacePayerCode() — getBankList()
            // attend un ISO2, conversion idempotente si $country était déjà un ISO2.
            $response = $this->digitwaceClient()->getBankList($this->countryNameToIso2($country), $payerCode);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->digitwaceErrorResponse($e, 'get_digitwace_bank_list');
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'message' => $e->getMessage()], 500);
        }
        return response()->json(['status' => 200, 'payer_code' => $payerCode, 'data' => $response['data'] ?? []]);
    }

    /**
     * POST /transaction/payoutServiceCode (doc §XIV) — liste des opérateurs/
     * modes de livraison disponibles pour un pays/devise donnés, à afficher
     * en option côté admin/mobile pour surcharger la sélection automatique
     * (voir resolveDigitwacePayerCode) quand plusieurs opérateurs mobile
     * money coexistent pour le même pays.
     * @return \Illuminate\Http\JsonResponse
     */
    public function get_digitwace_services(Request $request)
    {
        $country = $request->get('country');
        $currency = $request->get('currency');
        if (!$country || !$currency) {
            return response()->json(['status' => 422, 'message' => 'country and currency are required'], 422);
        }
        try {
            $response = $this->digitwaceClient()->getPayoutServiceCode($country, $currency);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            return $this->digitwaceErrorResponse($e, 'get_digitwace_services');
        }
        return response()->json(['status' => 200, 'data' => $response['data'] ?? []]);
    }

    /**
     * RETIRÉ (2026-08-24) : cette classe contenait ici un endpoint de
     * diagnostic temporaire `digitwace_diag()` (ajouté le 2026-08-20, marqué
     * "À SUPPRIMER une fois le diagnostic terminé" dans son propre
     * commentaire), utilisé pour identifier depuis Railway — seul serveur
     * avec une IP whitelistée côté WACEPAY — le vrai chemin de l'endpoint
     * PayoutServiceCode. Celui-ci est désormais confirmé (POST
     * /transaction/payouts/services, voir DigitwaceClient::
     * getPayoutServiceCode) et vérifié par un test réel en sandbox le
     * 2026-08-24 ; l'endpoint de diagnostic et sa route ('digitwace/diag',
     * voir routes/api.php) ont donc été retirés.
     */
}
