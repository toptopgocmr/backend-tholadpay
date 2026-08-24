<?php

use Dingo\Api\Routing\Router;
use Fruitcake\Cors\HandleCors;

/** @var Router $api */
$api = app(Router::class);

$api->version('v1', function (Router $api) {
    $api->group([
        'namespace' => 'App\Api\V1\Controllers',
        'middleware' => [\Fruitcake\Cors\HandleCors::class],
    ], function (Router $api) {

        $api->group(['prefix' => 'auth'], function (Router $api) {
            $api->post('login', 'Auth\AuthController@login');
            $api->post('signup', 'Auth\AuthController@postRegister');
            $api->post('fortgot_password', 'Auth\PasswordResetController@sendResetLinkEmail');
            $api->get('get_user_by_email', 'Auth\PasswordResetController@getUserByEmail');
            $api->post('change_password_user', 'Auth\PasswordResetController@changeValuePasswordUser');
            $api->post('send_code_sms', 'Auth\PasswordResetController@sendSmsCode');
            $api->post('send_sms_to_phone', 'Auth\PasswordResetController@sendSmsToPhoneNumber');
            $api->post('searchTarification', 'Auth\PasswordResetController@searchTarificationByZoneAndAmout');
            $api->post('searchAllTarificationZone', 'Auth\PasswordResetController@searchAllTarificationByZone');
            $api->get('get_ranking', 'Auth\PasswordResetController@getRanking');
            $api->post('updatepassword', 'Auth\AuthController@updatePassword');
            $api->post('get_user_by_phone', 'Auth\PasswordResetController@getUserbyPhoneNumber');
            $api->post('send-code', 'Auth\AuthController@sendPhoneVerificationCode');
            $api->post('send-code-reset-pin', 'Auth\AuthController@sendPhoneVerificationCode_toresetpincode');
            $api->post('verify-code', 'Auth\AuthController@verifyCode');
            $api->get('me', 'Auth\AuthController@getAuthenticatedUser');
            $api->get('refresToken', 'Auth\AuthController@refresToken');
        });

        // Routes publiques nécessaires à l'inscription et à la recherche d'utilisateurs
        $api->group(['prefix' => 'users'], function (Router $api) {
            $api->get('me', 'UserController@me');
            $api->post('updateMe', 'Auth\AuthController@updateMe');
            $api->post('set-pin-code', 'Auth\AuthController@setPinCode');
        });
        // Lectures publiques (référentiels, inscription)
        $api->resource("roles", 'RoleController');
        $api->resource("zones", 'ZoneController');
        $api->resource("tarifications", 'TarificationController');
        $api->resource("taxes", 'TaxController');
        $api->resource("addresses", 'AddressController');
        $api->resource("towns", 'TownController');
        $api->resource("verifications", 'VerificationController');
        $api->resource("countries", 'CountryController');
        $api->resource("currencies", 'CurrencyController');
        $api->resource("images", 'ImageController');
        // users et senders : POST/GET publics pour l'inscription mobile ; PUT/DELETE protégés ci-dessous
        $api->get("users", 'UserController@index');
        $api->post("users", 'UserController@store');
        $api->get("users/{id}", 'UserController@show');
        $api->get("users/{id}/mobile/connect", 'UserController@user_mobile');
        $api->get("senders", 'SenderController@index');
        $api->post("senders", 'SenderController@store');
        $api->get("senders/{id}", 'SenderController@show');
        $api->get("app_status", 'SettingappController@status');
        $api->post("convert_timestamp", 'SettingappController@convertToTimestamp');

        // Opérations Peex accessibles avant connexion (parcours invité depuis /welcome
        // -> "Nouvelle transaction" -> /country). Ce sont des lectures / vérifications,
        // pas d'envoi d'argent : send_transaction et send_bank_transaction restent
        // protégées par jwt.auth plus bas.
        // Doc: https://peex-api-docs.peexit.com/
        $api->get('get_corridors', 'OutboundController@get_corridors');
        $api->get('get_partner', 'OutboundController@get_partner');
        $api->get('get_peex_account', 'OutboundController@get_peex_account');
        $api->post('verify_phone_number', 'OutboundController@verify_phone_number');
        $api->post('check_account_status', 'OutboundController@check_account_status');
        $api->post('get_quotation', 'OutboundController@get_quotation');
        $api->post('check_transaction_status', 'OutboundController@check_transaction_status');
        // check_bank_account_status : conservé pour compat. mais Peex n'a pas
        // d'endpoint de vérification bancaire, voir OutboundController.
        $api->post('check_bank_account_status', 'OutboundController@check_bank_account_status');
        $api->post('get_bank_quotation', 'OutboundController@get_bank_quotation');

        // Référentiels DigitWace (lecture seule — voir "WACEPAY INTEGRATION API
        // SERVICE SPECIFICATION.pdf"), affichés côté admin/mobile uniquement
        // quand le partenaire "DigitWace" est sélectionné à la validation.
        $api->get('get_digitwace_relations', 'OutboundController@get_digitwace_relations');
        $api->get('get_digitwace_reasons', 'OutboundController@get_digitwace_reasons');
        $api->get('get_digitwace_origin_funds', 'OutboundController@get_digitwace_origin_funds');
        $api->get('get_digitwace_bank_list', 'OutboundController@get_digitwace_bank_list');
        $api->get('get_digitwace_services', 'OutboundController@get_digitwace_services');
        // AJOUT (2026-08-13, demande explicite) : solde réel du compte DigitWace
        // (doc §XIX Balance), même usage que get_peex_account ci-dessus — affiché
        // sur le dashboard admin (voir AdminController::index / home.blade.php).
        $api->get('get_digitwace_account', 'OutboundController@get_digitwace_account');
        // RETIRÉ (2026-08-24) : 'digitwace/diag' était un endpoint de diagnostic
        // temporaire ajouté le 2026-08-20 pour identifier le vrai chemin de
        // PayoutServiceCode depuis Railway (seul serveur avec IP whitelistée
        // WACEPAY). Le chemin est désormais confirmé (POST
        // /transaction/payouts/services, voir DigitwaceClient::
        // getPayoutServiceCode) et vérifié par un test réel en sandbox — la
        // route et OutboundController::digitwace_diag ont été supprimées comme
        // prévu par leur propre commentaire "À SUPPRIMER une fois le
        // diagnostic terminé".

        // AJOUT (2026-08-20) : callback webhook PawaPay (doc "Remittance
        // callback"), à configurer dans le Dashboard PawaPay -> Callback URLs
        // une fois le sandbox créé. URL réelle (voir prefix 'api' appliqué par
        // Dingo, pas de segment /v1/ dans le chemin — cf.
        // mobile-tholadpay/src/app/services/host/host.service.ts::buildHost()) :
        // https://backend-tholadpay-production.up.railway.app/api/pawapay/callback
        // Volontairement public (hors jwt.auth) : PawaPay ne s'authentifie pas
        // en JWT tholadpay — voir OutboundController::pawapay_callback pour la
        // limite connue (signature RFC-9421 non vérifiée pour l'instant).
        $api->post('pawapay/callback', 'OutboundController@pawapay_callback');

        // AJOUT (2026-08-20) : callback webhook DigitWace/WACEPAY (« API de
        // Notification », demandée par le support WACEPAY le 2026-08-20). URL
        // réelle à transmettre à WACEPAY (même convention que pawapay/callback
        // ci-dessus) :
        // https://backend-tholadpay-production.up.railway.app/api/digitwace/callback
        // IMPORTANT : au moment où ce code est écrit, le PDF transmis par
        // WACEPAY ("WACEPAY_INTEGRATION_API_SERVICE_SPECIFICATION.pdf") ne
        // contient AUCUNE section Notification/SMS (sommaire I à XIX, de
        // Introduction à Balance) — c'est le même document déjà utilisé pour
        // DigitwaceClient, pas le "guide de configuration des APIs de
        // Notification" annoncé par le support. Voir
        // OutboundController::digitwace_callback pour le détail de ce qui est
        // donc une best-effort en attendant le vrai schéma.
        $api->post('digitwace/callback', 'OutboundController@digitwace_callback');

        // AJOUT (2026-08-08) : consultation publique (sans connexion) d'un
        // transfert interne par code de retrait — le bénéficiaire n'a pas
        // forcément de compte tholadpay ; le code lui-même sert de secret
        // (même principe qu'un code de retrait bancaire classique). Volontairement
        // en dehors du groupe jwt.auth ci-dessous, CONTRAIREMENT à
        // send_internal_transaction/payout_internal_transaction qui déplacent/
        // valident réellement de l'argent et doivent rester réservés aux agents
        // connectés. Voir App\Api\V1\Controllers\InternalTransferController.
        $api->post('lookup_internal_transaction', 'InternalTransferController@lookup_internal_transaction');

        $api->group(['middleware' => 'jwt.auth'], function (Router $api) {
            // Gestion des rôles et permissions
            $api->get("role_users", 'RoleUserController@index');
            $api->get("role_users/{role_id}/{user_id}", 'RoleUserController@show');
            $api->post("role_users", 'RoleUserController@store');
            $api->delete("role_users/{role_id}/{user_id}", 'RoleUserController@destroy');
            $api->resource("permissions", 'PermissionController');
            $api->resource("permission_roles", 'PermissionRoleController');
            $api->resource("permission_users", 'PermissionUserController');
            $api->resource("role_users", 'RoleUserController');

            // Ressources financières sensibles — toutes protégées par JWT
            $api->resource("transactions", 'TransactionController');
            $api->resource("outbounds", 'OutboundController');
            $api->resource("inbounds", 'InboundController');
            $api->resource("banks", 'BankController');
            $api->resource("mobiles", 'MobileController');
            $api->resource("prefundings", 'PrefundingController');
            $api->resource("agents", 'AgentController');
            $api->resource("notes", 'NoteController');
            $api->resource("retail_outlets", 'RetailOuletController');
            $api->resource("user_funds", 'UserFundsController');
            $api->resource("withdraws", 'WithdrawController');
            $api->resource("cashes", 'CashController');
            $api->resource("beneficiaries", 'BeneficiaryController');
            $api->resource("operator_fees", 'OperatorFeesController');

            // Mise à jour / suppression users et senders (protégées)
            $api->put("users/{id}", 'UserController@update');
            $api->delete("users/{id}", 'UserController@destroy');
            $api->put("senders/{id}", 'SenderController@update');
            $api->delete("senders/{id}", 'SenderController@destroy');

            // Limit funds transfer
            $api->resource("limit_funds", 'LimitFundController');
            $api->resource("country_funds", 'CountryFundsController');

            // Envoi effectif d'argent vers Peex — reste protégé par JWT même si le
            // reste du parcours (get_partner, get_corridors, quotation...) est public.
            $api->post('send_transaction', 'OutboundController@send_transaction');
            $api->post('send_bank_transaction', 'OutboundController@send_bank_transaction');
            // Retrait en espèces — DigitWace uniquement (voir OutboundController::send_cash_transaction).
            $api->post('send_cash_transaction', 'OutboundController@send_cash_transaction');
            $api->get("limit_funds_spec/{from}/{to}", 'LimitFundController@fundSpec');
            $api->get("country_limit_funds_spec/{code}", 'CountryFundsController@fundSpec');

            // Transferts internes tholadpay (sans Peex/DigitWace) — voir
            // App\Api\V1\Controllers\InternalTransferController. lookup_internal_transaction
            // est enregistrée plus haut, HORS jwt.auth (voir commentaire associé).
            $api->post('send_internal_transaction', 'InternalTransferController@send_internal_transaction');
            $api->post('payout_internal_transaction', 'InternalTransferController@payout_internal_transaction');
            // AJOUT (2026-08-08) : rejet d'un retrait interne par l'agent payeur
            // (bénéficiaire non conforme, pièce d'identité invalide, ou autre motif) —
            // voir InternalTransferController::reject_internal_transaction.
            $api->post('reject_internal_transaction', 'InternalTransferController@reject_internal_transaction');
        });
        // $api->get('/clear-cache', function() {
        //     $exitCode = Artisan::call('cache:clear');
        //     $exitCode = Artisan::call('config:cache');
        //     return 'DONE'; //Return anything
        // });
    });
});
