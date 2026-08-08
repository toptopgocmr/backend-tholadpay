<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Retrait en espèces (Cash Pickup) — 3e mode de livraison, propre à DigitWace
 * (Peex ne le propose pas). Table réutilisée (voir migration
 * adapt_cashes_table_for_cash_pickup) : mêmes rôle et pattern que Bank/Mobile
 * vis-à-vis d'Outbound (outbound_id, rempli par transaction.page.ts à
 * l'envoi), repris à la validation par OutboundController::send_cash_transaction
 * si le partenaire choisi est DigitWace. Les champs remitance_purpose/code/
 * transaction_id (usage historique 2020, sans appelant) restent nullable pour
 * compat descendante mais ne sont plus utilisés par le nouveau flux.
 */
class Cash extends Model
{
    //
    use RestTrait;

    protected $fillable = [
        'remitance_purpose', 'mobile_phone', 'code', 'description', 'transaction_id',
        'receiver_city', 'security_question', 'security_answer', 'outbound_id', 'inbound_id',
    ];

    protected $dates = ['created_at','updated_at'];

    public function transaction(){
        return $this->belongsTo(Transaction::class);
    }

    public function outbound(){
        return $this->belongsTo(Outbound::class);
    }

    public function inbound(){
        return $this->belongsTo(Inbound::class);
    }
}
