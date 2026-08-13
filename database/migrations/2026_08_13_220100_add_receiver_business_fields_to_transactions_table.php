<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Intégration DigitWace (WACEPAY) — pendant côté bénéficiaire de la migration
 * add_business_fields_to_senders_table (doc §VI Create Beneficiary : "type" P
 * ou B). OutboundController::createDigitwaceBeneficiary envoyait toujours
 * "type":"P" en dur.
 *
 * Ajoute :
 *   - receiver_type            : 'P' (défaut) ou 'B'
 *   - receiver_business_name    (doc: businessName, obligatoire si B)
 *   - receiver_business_type    (doc: businessType, obligatoire si B)
 *   - receiver_expire_date      (doc: expire_date, obligatoire si B — date
 *                                d'expiration de la validation de l'entreprise)
 *   - business_type             combinaison calculée (p2p/b2b/b2p/p2b) à partir
 *                                de sender_type + receiver_type, utilisée pour
 *                                interroger /transaction/reason/{businessType} et
 *                                /transaction/origin_fund/{businessType} (doc
 *                                §XVII/§XVIII) — stockée pour traçabilité/debug,
 *                                recalculée à chaque appel plutôt que fiée en dur.
 */
class AddReceiverBusinessFieldsToTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('receiver_type', 1)->default('P')->after('receiver_relation');
            $table->string('receiver_business_name')->nullable()->after('receiver_type');
            $table->string('receiver_business_type')->nullable()->after('receiver_business_name');
            $table->date('receiver_expire_date')->nullable()->after('receiver_business_type');
            $table->string('business_type', 3)->nullable()->after('receiver_expire_date');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['receiver_type', 'receiver_business_name', 'receiver_business_type', 'receiver_expire_date', 'business_type']);
        });
    }
}
