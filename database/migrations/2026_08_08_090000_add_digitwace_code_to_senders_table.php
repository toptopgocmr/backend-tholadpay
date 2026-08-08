<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Integration DigitWace (WACEPAY) — voir App\Libraries\DigitwaceClient.
 *
 * DigitWace impose de créer un "sender" via POST /sender/create avant de
 * pouvoir envoyer la moindre transaction, et renvoie un "Code" unique en
 * réponse (voir doc §V Create Sender). Contrairement à Peex (qui accepte les
 * infos expéditeur en ligne dans chaque appel), ce Code doit être réutilisé
 * pour toutes les transactions suivantes du même expéditeur : sans mise en
 * cache, on recréerait un sender différent (et probablement rejeté en
 * doublon) à chaque validation. On le stocke donc une fois pour toutes sur
 * le Sender local.
 */
class AddDigitwaceCodeToSendersTable extends Migration
{
    public function up()
    {
        Schema::table('senders', function (Blueprint $table) {
            $table->string('digitwace_code')->nullable()->after('cni_number');
        });
    }

    public function down()
    {
        Schema::table('senders', function (Blueprint $table) {
            $table->dropColumn('digitwace_code');
        });
    }
}
