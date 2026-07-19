<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePrefundingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('prefundings', function (Blueprint $table) {
            $table->increments('id');
            $table->float('amount', 15, 2);
            $table->string('paiement_type');
            $table->string('status');
            $table->dateTime('date_paiement');
            $table->boolean('valid');
            $table->text('description')->nullable();
            $table->text('prove')->nullable();
            $table->integer('agent_id')->unsigned()->index();
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
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
        Schema::dropIfExists('prefundings');
    }
}
