<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('recipient_first_name');
            $table->string('recipient_last_name');
            $table->string('receiving_country');
            $table->string('transaction_reference');
            $table->string('transaction_reason')->nullable();
            $table->string('recipient_phone');
            $table->string('ranking');
            $table->float('amount', 15, 2);
            $table->float('fxrate', 15, 10)->nullable();
            $table->float('aml_cft', 15, 2)->nullable();
            $table->string('from_currency')->default('XAF');
            $table->string('to_currency')->default('XAF');
            $table->string('transaction_status')->default('waiting');
            $table->integer('sender_id')->unsigned()->index()->nullable();
            $table->foreign('sender_id')->references('id')->on('senders');
            $table->integer('user_id')->unsigned()->index()->nullable();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
}
