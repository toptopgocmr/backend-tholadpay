<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMobilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mobiles', function (Blueprint $table) {
            $table->increments('id');
            $table->string('mobile_phone_credit');
            $table->text('mobile_phone_debit');
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
        Schema::dropIfExists('mobiles');
    }
}
