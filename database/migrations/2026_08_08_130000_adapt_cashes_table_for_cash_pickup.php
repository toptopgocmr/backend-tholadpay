<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Intégration DigitWace (WACEPAY) — livraison "Cash Pickup" (retrait en
 * espèces), 3e mode de livraison aux côtés de Bank/Mobile.
 *
 * La table `cashes` existait déjà (migration 2020_08_02_171447_create_cashes_table),
 * mais pour un usage totalement différent : liée directement à `transactions`
 * (transaction_id), avec des champs (remitance_purpose/code) qui ne
 * correspondent à rien côté DigitWace, et SANS AUCUN appelant (ni mobile, ni
 * admin, ni aucune autre route ne la référence) — code mort depuis 2020.
 *
 * On la réutilise plutôt que de créer une table redondante, en l'alignant sur
 * le même schéma que `banks`/`mobiles` (liée à `outbounds` via outbound_id,
 * remplie par transaction.page.ts au moment de l'envoi, exactement comme
 * addOutboundBank()/addOutboundMobile()) :
 *   - transaction_id, remitance_purpose, code, description : rendus nullable
 *     (plus jamais renseignés pour les nouvelles lignes, conservés tels quels
 *     pour ne rien casser si d'anciennes lignes existent déjà en base).
 *   - mobile_phone : réutilisé tel quel pour le "mobileTopup" DigitWace
 *     (numéro éventuellement notifié par SMS) — même rôle sémantique.
 *   - outbound_id/inbound_id, receiver_city, security_question,
 *     security_answer : nouveaux champs requis par DigitWace
 *     (transaction/cash/create : toCity, question, response).
 */
class AdaptCashesTableForCashPickup extends Migration
{
    public function up()
    {
        Schema::table('cashes', function (Blueprint $table) {
            $table->string('remitance_purpose')->nullable()->change();
            $table->string('mobile_phone')->nullable()->change();
            $table->string('code')->nullable()->change();
            $table->text('description')->nullable()->change();
            $table->integer('transaction_id')->unsigned()->nullable()->change();

            $table->string('receiver_city')->nullable()->after('description');
            $table->string('security_question')->nullable()->after('receiver_city');
            $table->string('security_answer')->nullable()->after('security_question');
            $table->integer('outbound_id')->unsigned()->index()->nullable()->after('security_answer');
            $table->foreign('outbound_id')->references('id')->on('outbounds')->onDelete('cascade');
            $table->integer('inbound_id')->unsigned()->index()->nullable()->after('outbound_id');
            $table->foreign('inbound_id')->references('id')->on('inbounds')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::table('cashes', function (Blueprint $table) {
            $table->dropForeign(['outbound_id']);
            $table->dropForeign(['inbound_id']);
            $table->dropColumn(['receiver_city', 'security_question', 'security_answer', 'outbound_id', 'inbound_id']);
        });
    }
}
