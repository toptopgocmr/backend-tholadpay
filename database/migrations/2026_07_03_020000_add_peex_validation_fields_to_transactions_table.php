<?php

use Illuminate\Database\Migrations\Migration;

/**
 * ANNULÉE / NO-OP : approche abandonnée en cours de session.
 *
 * L'admin (app-admin-prod-tholadpay) a en réalité DÉJÀ tout le workflow de
 * validation manuelle -> envoi Peex (TransactionController::update ->
 * getquotation -> sendtransaction, routes transaction_valid/transaction_quote/
 * transaction_transac). Il se basait déjà sur outbound.bank / outbound.mobile
 * (déjà correctement créés par le mobile via addOutboundBank/addOutboundMobile)
 * plutôt que sur de nouvelles colonnes sur "transactions". Ce fichier est
 * conservé (plutôt que supprimé, non permis dans cet environnement) mais ne
 * fait plus rien, pour ne pas ajouter de colonnes inutiles.
 */
class AddPeexValidationFieldsToTransactionsTable extends Migration
{
    public function up()
    {
        // Intentionnellement vide — voir commentaire ci-dessus.
    }

    public function down()
    {
        // Intentionnellement vide.
    }
}
