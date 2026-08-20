<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Intégration PawaPay (3e partenaire payeur) — mêmes principes que la
 * migration 2026_08_08_120000_add_receiver_digitwace_fields_to_transactions_table :
 * saisie FACULTATIVE, dès la création mobile, des champs propres à PawaPay,
 * pour pouvoir pré-remplir l'étape de validation admin quand l'agent choisit
 * PawaPay comme partenaire (le partenaire n'est choisi qu'à la validation,
 * voir OutboundController::resolvePartner — rien ne doit être obligatoire à
 * la création).
 *
 * Contrairement à DigitWace, PawaPay ne nécessite ni sender_code ni
 * beneficiary_code persistants (l'API Remittance PawaPay est un appel
 * unique et stateless, voir App\Libraries\PawapayClient) : le remittanceId
 * PawaPay et son statut sont stockés dans les colonnes déjà génériques
 * transactions.reference / transactions.etat_transac (voir
 * OutboundController::normalizePawapayRemittanceResponse / pawapay_callback),
 * pas besoin de colonnes dédiées pour ça.
 */
class AddPawapayFieldsToTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            // 'AIRTEL' ou 'MTN' (nom court, voir PawapayCorridors::resolveProvider) —
            // pas le code provider complet ('AIRTEL_COG'), qui dépend du pays et est
            // recalculé à l'envoi.
            $table->string('pawapay_operator')->nullable()->after('receiver_relation');
            $table->string('pawapay_purpose_of_funds')->nullable()->after('pawapay_operator');
            $table->string('pawapay_source_of_funds')->nullable()->after('pawapay_purpose_of_funds');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['pawapay_operator', 'pawapay_purpose_of_funds', 'pawapay_source_of_funds']);
        });
    }
}
