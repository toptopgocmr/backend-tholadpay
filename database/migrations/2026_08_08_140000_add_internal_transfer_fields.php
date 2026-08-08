<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Transferts internes tholadpay (sans Peex ni DigitWace) — voir
 * OutboundController::resolvePartner (partenaire 'internal', corridor_id=3)
 * et le nouveau InternalTransferController (lookup + payout par code).
 *
 * Principe : l'agent expéditeur envoie normalement (même formulaire, même
 * workflow de validation) ; à la validation, si le partenaire choisi est
 * "Interne", aucun appel externe n'est fait — un code de retrait aléatoire
 * est généré et communiqué au client. Le bénéficiaire peut ensuite retirer
 * en espèces chez N'IMPORTE QUEL agent tholadpay du pays destinataire en
 * présentant ce code + une pièce d'identité. L'agent payeur est crédité du
 * montant qu'il vient de décaisser (à régler avec le siège hors application,
 * comme convenu) ; aucun montant supplémentaire n'est débité à l'agent
 * expéditeur (déjà débité normalement à l'envoi).
 */
class AddInternalTransferFields extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Distinct de `ranking` (prévisible : KL-AAAA-MM-JJ-NNN, donc impropre à
            // servir de code secret de retrait) — généré aléatoirement, uniquement
            // pour les transferts internes.
            $table->string('internal_pickup_code')->nullable()->unique()->after('receiver_relation');
        });

        Schema::table('agents', function (Blueprint $table) {
            // Permet de savoir dans quel pays un agent opère — utilisé pour avertir
            // (sans bloquer, agents existants non renseignés) si un agent tente de
            // payer un retrait destiné à un autre pays que le sien.
            $table->integer('country_id')->unsigned()->index()->nullable()->after('user_id');
            $table->foreign('country_id')->references('id')->on('countries')->onDelete('set null');
        });

        Schema::table('inbounds', function (Blueprint $table) {
            // Qui a payé le bénéficiaire, quand, et avec quelle pièce d'identité
            // vérifiée — l'équivalent, côté réception, de agent_id/valid_id côté
            // outbound/transactions.
            $table->integer('paying_agent_id')->unsigned()->index()->nullable()->after('transaction_id');
            $table->foreign('paying_agent_id')->references('id')->on('agents')->onDelete('set null');
            $table->timestamp('paid_at')->nullable()->after('paying_agent_id');
            $table->string('payout_id_number')->nullable()->after('paid_at');
            $table->string('payout_id_type')->nullable()->after('payout_id_number');
        });
    }

    public function down()
    {
        Schema::table('inbounds', function (Blueprint $table) {
            $table->dropForeign(['paying_agent_id']);
            $table->dropColumn(['paying_agent_id', 'paid_at', 'payout_id_number', 'payout_id_type']);
        });
        Schema::table('agents', function (Blueprint $table) {
            $table->dropForeign(['country_id']);
            $table->dropColumn('country_id');
        });
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('internal_pickup_code');
        });
    }
}
