<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Intégration DigitWace (WACEPAY) — support des comptes Business (doc §V Create
 * Sender : "type" P (Personal) ou B (Business)). Jusqu'ici OutboundController::
 * ensureDigitwaceSenderCode envoyait toujours "type":"P" en dur (aucun champ
 * business en base) — impossible de faire des transferts B2B/B2P.
 *
 * Ajoute les champs exigés par la doc pour un sender business :
 *   - sender_type        : 'P' (défaut) ou 'B'
 *   - business_name       (doc: businessName, obligatoire si B)
 *   - business_type       (doc: businessType, ex SARL/SARLU/SAS/SA — obligatoire si B)
 *   - business_register_date (doc: regiterBusinessDate, obligatoire si B)
 *   - business_comment    (doc: comment, obligatoire si B — description de l'activité)
 *   - email               (doc: email, obligatoire si B — le User.email généré
 *                          automatiquement, phone@send-paz.com, n'est pas une
 *                          vraie adresse ; on permet donc au sender d'en saisir
 *                          une réelle, utilisée en priorité côté DigitWace)
 *
 * Rappel doc : si sender_type = 'B', idType doit être "RCCM" (erreur DigitWace
 * 3001 sinon) — appliqué côté OutboundController, pas de colonne dédiée requise
 * (on réutilise idNumber = cni_number comme numéro d'immatriculation business).
 */
class AddBusinessFieldsToSendersTable extends Migration
{
    public function up()
    {
        Schema::table('senders', function (Blueprint $table) {
            $table->string('sender_type', 1)->default('P')->after('digitwace_code');
            $table->string('business_name')->nullable()->after('sender_type');
            $table->string('business_type')->nullable()->after('business_name');
            $table->date('business_register_date')->nullable()->after('business_type');
            $table->string('business_comment')->nullable()->after('business_register_date');
            $table->string('email')->nullable()->after('business_comment');
        });
    }

    public function down()
    {
        Schema::table('senders', function (Blueprint $table) {
            $table->dropColumn(['sender_type', 'business_name', 'business_type', 'business_register_date', 'business_comment', 'email']);
        });
    }
}
