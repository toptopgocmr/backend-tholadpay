<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(){
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->default('John');
            $table->string('last_name')->default('Doe');
            $table->string('email')->unique()->nullable();
            $table->string('phone_number')->unique();
            $table->integer('failed_password_attemps')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('status')->default('is_inactive');
            $table->string('password');
            $table->string('picture')->nullable();
            $table->dateTime('last_login')->nullable();
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
}
