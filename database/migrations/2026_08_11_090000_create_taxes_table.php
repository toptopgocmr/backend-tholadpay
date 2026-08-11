<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTaxesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('taxes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code')->unique();
            $table->string('libelle');
            $table->string('type');
            $table->float('valeur', 10, 4);
            $table->string('assiette')->nullable();
            $table->integer('zone_id')->unsigned()->index()->nullable();
            $table->foreign('zone_id')->references('id')->on('zones');
            $table->boolean('statut')->default(true);
            $table->integer('ordre')->default(0);
            $table->timestamps();
        });

        // Valeurs par defaut des 4 taxes gerees par l'application (CEMAC) :
        // TTF (Taxe sur le Transfert de Fonds), Commission COBAC, TVA et Timbre
        // electronique. L'admin pourra ensuite ajuster ces taux/valeurs sans
        // toucher au code via le CRUD taxes.
        DB::table('taxes')->insert([
            [
                'code' => 'TTF',
                'libelle' => 'TTF',
                'type' => 'pourcentage',
                'valeur' => 1.5,
                'assiette' => 'montant',
                'zone_id' => null,
                'statut' => true,
                'ordre' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'COBAC',
                'libelle' => 'Commission COBAC',
                'type' => 'pourcentage',
                'valeur' => 0.25,
                'assiette' => 'montant',
                'zone_id' => null,
                'statut' => true,
                'ordre' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'TVA',
                'libelle' => 'TVA',
                'type' => 'pourcentage',
                'valeur' => 18.9,
                'assiette' => 'frais',
                'zone_id' => null,
                'statut' => true,
                'ordre' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'TIMBRE',
                'libelle' => 'Timbre électronique',
                'type' => 'fixe',
                'valeur' => 50,
                'assiette' => null,
                'zone_id' => null,
                'statut' => true,
                'ordre' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('taxes');
    }
}
