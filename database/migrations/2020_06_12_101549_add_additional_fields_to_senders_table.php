<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAdditionalFieldsToSendersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('senders', function (Blueprint $table) {
            $table->date('birth_date')->nullable();
            $table->string('title')->nullable();
            $table->string('postal_code')->nullable();
            $table->date('issuer_date')->nullable();
            $table->string('issuer_country')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('senders', function (Blueprint $table) {
            $table->dropColumn('birth_date');            
            $table->dropColumn('title');            
            $table->dropColumn('postal_code');            
            $table->dropColumn('issuer_date');            
            $table->dropColumn('issuer_country');            
        });
    }
}
