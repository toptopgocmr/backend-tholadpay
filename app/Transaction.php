<?php

namespace App;

use App\Note;
use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    //
    use RestTrait;

    protected $fillable = ['amount', 'aml_cft', 'fxrate', 'from_currency', 'to_currency', 'recipient_first_name', 'ranking',
        'recipient_last_name', 'recipient_phone', 'receiving_country', 'transaction_reference', 'transaction_reason', 'sender_id', 'user_id',
        'transaction_status', 'montant_beneficiaire', 'frais_envoi', 'validate', 'valid_id', 'validate_at', 'csa_id', 'agent_id', 'payer', 'nom_api', 'date_init', 'date_complete',
        'fees', 'receiving_country_code', 'description', 'reference', 'tarif_id', 'corridor_id', 'etat_transac', 'observations'
    ];

    protected $dates = ['created_at','updated_at'];

    public function sender(){
        return $this->belongsTo((Sender::exists()) ? Sender::class : null);
    }

    public function user(){
        return $this->belongsTo((User::exists()) ? User::class : null);
    }

    public function agent(){
        return $this->belongsTo((Agent::exists()) ? Agent::class : null);
    }

    public function tarification(){
        return $this->belongsTo((Tarification::exists()) ? Tarification::class : null);
    }

    public function outbound(){
        return $this->hasOne(Outbound::class);
    }

    public function inbound(){
        return $this->hasOne(Inbound::class);
    }

    public function cash(){
        return $this->hasOne(Cash::class);
    }
    /**
     * Get all of the post's comments.
     */
    public function notes()
    {
        return $this->morphMany(Note::class, 'verifiable');
    }
}
