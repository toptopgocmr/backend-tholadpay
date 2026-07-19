<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInfosValidatePaymentToTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('agent_id')->unsigned()->index()->nullable();
            $table->foreign('agent_id')->references('id')->on('agents');
            $table->boolean('validate')->default(false);
            $table->integer('valid_id')->unsigned()->index()->nullable();
            $table->foreign('valid_id')->references('id')->on('users');
            $table->timestamp('validate_at')->nullable();
            $table->boolean('payer')->default(false);
            $table->string('nom_api')->nullable();
            $table->date('date_init')->nullable();
            $table->date('date_complete')->nullable();
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
            $table->dropColumn('agent_id');
            $table->dropColumn('validate');
            $table->dropColumn('valid_id');
            $table->dropColumn('validate_at');
            $table->dropColumn('payer');
            $table->dropColumn('nom_api');
            $table->dropColumn('date_init');
            $table->dropColumn('date_complete');
        });
    }
}
