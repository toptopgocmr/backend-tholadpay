<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBanksTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('banks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('bank_account_no');
            $table->string('short_code')->nullable();
            $table->text('organisation');
            $table->integer('outbound_id')->unsigned()->index()->nullable();
            $table->foreign('outbound_id')->references('id')->on('outbounds')->onDelete('cascade');
            $table->integer('inbound_id')->unsigned()->index()->nullable();
            $table->foreign('inbound_id')->references('id')->on('inbounds')->onDelete('cascade');
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
        Schema::dropIfExists('banks');
    }
}
