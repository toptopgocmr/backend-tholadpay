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
        'fees', 'receiving_country_code', 'description', 'reference', 'tarif_id', 'corridor_id', 'etat_transac', 'observations',
        // Saisie facultative, dès la création mobile, des champs propres à DigitWace
        // (pièce d'identité + relation du bénéficiaire) — voir migration
        // add_receiver_digitwace_fields_to_transactions_table. Repris automatiquement
        // à l'étape de validation si le partenaire DigitWace est sélectionné.
        'receiver_id_number', 'receiver_id_type', 'receiver_relation',
        // Transferts internes (voir migration add_internal_transfer_fields) : code de
        // retrait aléatoire généré à la validation si le partenaire est 'internal'.
        'internal_pickup_code',
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
