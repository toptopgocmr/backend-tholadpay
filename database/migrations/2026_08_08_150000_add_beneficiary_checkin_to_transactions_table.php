<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Ecran public "Vérifier un retrait" (SuiviRetraitPage, InternalTransferController::
 * lookup_internal_transaction) : quand un BENEFICIAIRE (pas l'agent) consulte son
 * transfert avec son code, on horodate ce "check-in" ici. Cela permet à l'écran
 * "Retrait interne" côté agent d'afficher une LISTE cliquable des transferts déjà
 * consultés par leur bénéficiaire (donc probablement en route vers l'agence), au
 * lieu d'obliger l'agent à ressaisir le code à la main.
 */
class AddBeneficiaryCheckinToTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->timestamp('beneficiary_checked_in_at')->nullable()->after('internal_pickup_code');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('beneficiary_checked_in_at');
        });
    }
}
