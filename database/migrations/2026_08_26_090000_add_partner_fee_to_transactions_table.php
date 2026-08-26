<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * AJOUT (2026-08-26, demande explicite : "essaie de voir si c'est possible de
 * prendre les commissions sur une api") : la colonne 'fees' existante sur
 * transactions est NOTRE frais interne (barème tarifaire Send-Paz, voir
 * TaxCalculationService / tarifications) -- ce n'est PAS la commission
 * reellement facturee par le partenaire (DigitWace/WACEPAY ou Peex) pour
 * traiter la transaction. Constat terrain : pour la transaction WPPX133328099158366,
 * le dashboard sandbox WACEPAY affiche "Total Fees: 675.00 XAF" alors que
 * notre export affichait 6600 XAF (= notre 'fees' interne) dans une colonne
 * etiquetee a tort "commission Partenaire".
 *
 * Verification sur la doc officielle WACEPAY_parthners_API Service
 * specifications : DigitWace expose bien ce montant, sous un nom de champ
 * incoherent selon l'endpoint :
 *   - POST /transaction/wallet/create (Sec. VIII) -> transaction.feeds (nombre, ex: 350)
 *   - POST /transaction/bank/create   (Sec. X)    -> transaction.feeds (nombre, ex: 7.5)
 *   - GET  /transaction/status/{ref}  (Sec. XI)   -> transaction.fees  (chaine, ex: "350.000")
 * ("feeds" est une faute de frappe cote DigitWace sur les 2 endpoints de
 * creation ; "fees" -- orthographe correcte -- sur getStatus. Les deux
 * designent la meme chose : la commission reellement retenue par DigitWace/
 * WACEPAY sur cette transaction precise.)
 *
 * Peex : aucun champ equivalent trouve dans le code d'integration existant
 * (OutboundController.php) ni dans rapport_integration_peex.md -- la doc
 * Peex documentee (6 endpoints, https://peex-api-docs.peexit.com/) n'expose
 * pas de commission par transaction a ce jour. La colonne reste donc NULL
 * pour les transactions Peex tant que Peex ne fournit pas cette info (a
 * confirmer aupres du support Peex si besoin).
 *
 * 'partner_fee' est donc une colonne DISTINCTE de 'fees' : capturee a la
 * creation (OutboundController::normalizeDigitwaceTransactionResponse) et
 * backfillee via check_transaction_status/getStatus (TransactionController::
 * index()/checkStatusOfTransaction() cote admin) pour les transactions deja
 * envoyees. Nullable : reste vide tant que le partenaire n'a pas repondu ou
 * ne fournit pas l'info (Peex).
 */
class AddPartnerFeeToTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->float('partner_fee', 15, 2)->nullable()->default(null)
                ->comment('Commission reelle facturee par le partenaire (DigitWace/WACEPAY: feeds|fees ; Peex: non expose actuellement), distincte de fees (notre frais interne).');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('partner_fee');
        });
    }
}
