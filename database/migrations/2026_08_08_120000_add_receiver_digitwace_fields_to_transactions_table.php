<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Intégration DigitWace (WACEPAY) — saisie anticipée côté mobile.
 *
 * Le formulaire mobile de CRÉATION de transaction (transaction.page.ts) permet
 * désormais de saisir dès l'envoi les 3 champs propres à DigitWace (pièce
 * d'identité du bénéficiaire + relation avec l'expéditeur), en FACULTATIF —
 * voir demande utilisateur du 2026-08-08 : plusieurs partenaires (Peex/
 * DigitWace) coexistent, le partenaire n'est choisi qu'à la validation (admin),
 * donc rien ne doit être obligatoire à la création. Ces colonnes permettent de
 * conserver la saisie si l'agent l'a renseignée, pour la pré-remplir
 * automatiquement à l'étape de validation plutôt que de la redemander.
 */
class AddReceiverDigitwaceFieldsToTransactionsTable extends Migration
{
    public function up()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('receiver_id_number')->nullable()->after('transaction_reason');
            $table->string('receiver_id_type')->nullable()->after('receiver_id_number');
            $table->string('receiver_relation')->nullable()->after('receiver_id_type');
        });
    }

    public function down()
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['receiver_id_number', 'receiver_id_type', 'receiver_relation']);
        });
    }
}
