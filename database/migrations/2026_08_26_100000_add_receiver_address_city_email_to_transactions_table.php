<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * AJOUT (2026-08-26, demande explicite : "verifier les validations sur le
 * mobile ... et le formulaire beneficiaire avec tous les champs manquants") :
 * revue de OutboundController::createDigitwaceBeneficiary() (doc DigitWace
 * SS VI Create Beneficiary) face a ce qui est reellement collecte cote mobile/
 * admin. Constat : le backend lit deja $request->get('receiver_address'),
 * ('receiver_city') et ('receiver_email') pour construire le payload
 * /beneficiary/create, MAIS aucun des deux frontends (mobile, admin) ne les
 * envoie jamais -- ces 3 champs n'existaient sur AUCUN formulaire et
 * n'avaient donc AUCUNE colonne de stockage :
 *   - 'address' retombe systematiquement sur receiving_country (ex: "France"
 *     envoye comme adresse -- jamais une vraie adresse).
 *   - 'city' retombe sur la chaine litterale "Any City" pour tout transfert
 *     Bancaire/Mobile (seul le Cash Pickup a une "ville de retrait", stockee
 *     a part sur la table 'cashes' -- voir migration
 *     2026_08_08_130000_adapt_cashes_table_for_cash_pickup -- semantiquement
 *     differente : la ville de retrait n'est pas forcement la ville de
 *     residence du beneficiaire).
 *   - 'email' n'est jamais transmis (toujours vide).
 * Comme pour receiver_id_number/receiver_dob (voir
 * add_receiver_digitwace_fields_to_transactions_table et
 * add_receiver_dob_to_transactions_table), ces 3 champs sont desormais
 * collectes des la creation mobile, stockes ici, puis repris/editables sur le
 * formulaire beneficiaire admin (transactions/update.blade.php) avant envoi a
 * DigitWace. Nullable : les anciens fallbacks ("Any City", pays comme adresse)
 * restent le filet de securite si l'agent laisse ces champs vides ou pour une
 * transaction Peex (qui ne les utilise pas).
 */
class AddReceiverAddressCityEmailToTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('receiver_address')->nullable()->default(null)
                ->comment('Adresse reelle du beneficiaire (doc DigitWace SS VI, champ address) -- distinct du pays de reception utilise comme repli.');
            $table->string('receiver_city')->nullable()->default(null)
                ->comment('Ville de residence du beneficiaire (doc DigitWace SS VI, champ city) pour un transfert Bancaire/Mobile -- distinct de cashes.receiver_city (ville de retrait Cash Pickup).');
            $table->string('receiver_email')->nullable()->default(null)
                ->comment('Email du beneficiaire (doc DigitWace SS VI, champ email, facultatif).');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['receiver_address', 'receiver_city', 'receiver_email']);
        });
    }
}
