<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTaxesToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('ttf', 15, 2)->default(0);
            $table->float('commission_cobac', 15, 2)->default(0);
            $table->float('tva', 15, 2)->default(0);
            $table->float('timbre_electronique', 15, 2)->default(0);
            $table->float('total_taxes', 15, 2)->default(0);
            $table->float('frais_envoi_ttc', 15, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('ttf');
            $table->dropColumn('commission_cobac');
            $table->dropColumn('tva');
            $table->dropColumn('timbre_electronique');
            $table->dropColumn('total_taxes');
            $table->dropColumn('frais_envoi_ttc');
        });
    }
}
