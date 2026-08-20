<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Intégration DigitWace (WACEPAY) — la date de naissance du bénéficiaire
 * ("dob", doc §VI Create Beneficiary : "optional required for personnal
 * account (depend destination)") n'avait jusqu'ici NULLE PART où être
 * persistée : ni colonne sur `transactions`, ni entrée dans
 * Transaction::$fillable. Le formulaire admin (resources/views/transactions/
 * update.blade.php, champ receiver_dob) et OutboundController::
 * createDigitwaceBeneficiary() (qui lit `$request->get('receiver_dob')`)
 * fonctionnent malgré tout QUAND l'admin saisit la valeur au même moment que
 * l'envoi final (formulaire à une seule page/un seul submit), mais toute
 * valeur envoyée plus tôt (ex: depuis l'app mobile client à la création de
 * la transaction, voir transaction.page.ts) était silencieusement perdue
 * (mass-assignment Laravel ignore un attribut absent de $fillable) — ce qui
 * empêchait de pré-remplir automatiquement ce champ à la validation, et donc
 * de l'avoir disponible sans ressaisie quand DigitWace l'exige pour la
 * destination du transfert (voir incident transaction #81/#90 déjà traités
 * pour idType/expire_date).
 *
 * On l'ajoute ici en miroir exact de receiver_expire_date (voir migration
 * add_receiver_business_fields_to_transactions_table) : date nullable, pour
 * ne rien rendre obligatoire.
 */
class AddReceiverDobToTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->date('receiver_dob')->nullable()->after('receiver_id_type');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['receiver_dob']);
        });
    }
}
