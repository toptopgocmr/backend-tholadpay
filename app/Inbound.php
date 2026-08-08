<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Réception/paiement d'une transaction — pendant symétrique d'Outbound.
 * Jusqu'ici du code mort (aucun appelant). Réutilisé pour les transferts
 * internes (voir migration add_internal_transfer_fields et
 * InternalTransferController::payout) : enregistre QUI a payé le
 * bénéficiaire (paying_agent_id), quand (paid_at), et avec quelle pièce
 * d'identité vérifiée (payout_id_number/payout_id_type).
 */
class Inbound extends Model
{
    //
    use RestTrait;

    protected $fillable = [
        'remitance_purpose', 'description', 'transaction_id',
        'paying_agent_id', 'paid_at', 'payout_id_number', 'payout_id_type',
    ];

    protected $dates = ['created_at','updated_at', 'paid_at'];

    public function transaction(){
        return $this->belongsTo(Transaction::class);
    }

    public function paying_agent(){
        return $this->belongsTo((Agent::exists()) ? Agent::class : null, 'paying_agent_id');
    }

    public static function boot()
    {
        parent::boot();
    }
}
