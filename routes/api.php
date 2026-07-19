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
            $api->get("limit_funds_spec/{from}/{to}", 'LimitFundController@fundSpec');
            $api->get("country_limit_funds_spec/{code}", 'CountryFundsController@fundSpec');
        });
        // $api->get('/clear-cache', function() {
        //     $exitCode = Artisan::call('cache:clear');
        //     $exitCode = Artisan::call('config:cache');
        //     return 'DONE'; //Return anything
        // });
    });
});
