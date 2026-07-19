<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFeesReceivingCountryToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('fees', 15, 2)->nullable();
            $table->string('receiving_country_code')->nullable();
            $table->string('description')->nullable();
            $table->string('reference')->nullable();
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
            $table->dropColumn('fees');
            $table->dropColumn('receiving_country_code');
            $table->dropColumn('description');
            $table->dropColumn('reference');
        });
    }
}
