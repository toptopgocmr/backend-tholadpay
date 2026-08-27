<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * DigitWace (WACEPAY) — champ "Civil status" (doc §V Create Sender), confirmé
 * via l'admin sandbox WACEPAY (Sender Personal Informations > Civil status,
 * captures fournies par l'utilisateur le 2026-08-27) : seules deux valeurs
 * existent côté DigitWace, 'Single' et 'Married'. Jusqu'ici OutboundController::
 * ensureDigitwaceSenderCode() envoyait 'Single' EN DUR pour tout sender, faute
 * de colonne dédiée en base (voir commentaire historique déjà présent dans ce
 * fichier). Ajoute une colonne nullable ; NULL/valeur inconnue retombe toujours
 * sur 'Single' côté OutboundController (comportement identique à avant pour les
 * senders existants, jusqu'à ce qu'ils renseignent le nouveau champ).
 */
class AddCivilStatusToSendersTable extends Migration
{
    public function up()
    {
        Schema::table('senders', function (Blueprint $table) {
            $table->string('civil_status', 20)->nullable()->after('sex');
        });
    }

    public function down()
    {
        Schema::table('senders', function (Blueprint $table) {
            $table->dropColumn('civil_status');
        });
    }
}
