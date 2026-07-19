<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute l'adresse bancaire, requise par Peex (champ bank_address) pour
 * send_bank_transaction / /clients/request_bank_payment, mais absente
 * jusqu'ici de la table banks.
 */
class AddBankAddressToBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->text('bank_address')->nullable()->after('organisation');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('banks', function (Blueprint $table) {
            $table->dropColumn('bank_address');
        });
    }
}
