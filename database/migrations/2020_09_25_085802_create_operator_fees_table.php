<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOperatorFeesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('operator_fees', function (Blueprint $table) {
            $table->increments('id');
            $table->string('country');
            $table->string('operator_code');
            $table->string('operator_name');
            $table->string('type');
            $table->float('min', 15, 2);
            $table->float('max', 15, 2);
            $table->float('fees', 15, 2);
            $table->boolean('status')->default(true);
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
        Schema::dropIfExists('operator_fees');
    }
}
