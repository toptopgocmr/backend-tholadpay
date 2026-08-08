<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Rejet d'un retrait interne par l'agent payeur (voir InternalTransferController::
 * reject_internal_transaction) — demande utilisateur du 2026-08-08 : "donner la
 * possibilité d'annuler ou rejeter [un retrait interne] en donnant la raison du
 * rejet (nom pas conforme, identité, ou autre raison valable)".
 *
 * NB (décision par défaut, à confirmer si besoin) : le rejet marque juste la
 * transaction comme 'Rejected' avec son motif — AUCUN montant n'est déplacé
 * automatiquement dans l'application, cohérent avec le reste du règlement entre
 * agences pour les transferts internes (voir migration add_internal_transfer_fields :
 * "réglé hors application").
 */
class AddRejectionFieldsToTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('rejection_reason')->nullable()->after('beneficiary_checked_in_at');
            $table->text('rejection_note')->nullable()->after('rejection_reason');
            $table->integer('rejected_by_agent_id')->unsigned()->index()->nullable()->after('rejection_note');
            $table->foreign('rejected_by_agent_id')->references('id')->on('agents')->onDelete('set null');
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_agent_id');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['rejected_by_agent_id']);
            $table->dropColumn(['rejection_reason', 'rejection_note', 'rejected_by_agent_id', 'rejected_at']);
        });
    }
}
