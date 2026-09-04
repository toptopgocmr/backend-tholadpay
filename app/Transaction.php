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
        // Date de naissance bénéficiaire (doc DigitWace §VI : "dob", exigée par
        // certaines destinations même pour un compte Personnel) — voir migration
        // add_receiver_dob_to_transactions_table. Absente d'ici jusqu'alors, toute
        // valeur envoyée par le mobile était silencieusement perdue (mass-assignment).
        'receiver_dob',
        // Transferts internes (voir migration add_internal_transfer_fields) : code de
        // retrait aléatoire généré à la validation si le partenaire est 'internal'.
        'internal_pickup_code',
        // Horodatage du "check-in" bénéficiaire depuis l'écran public (voir migration
        // add_beneficiary_checkin_to_transactions_table) : permet à l'agent de voir une
        // liste des retraits déjà consultés par leur bénéficiaire.
        'beneficiary_checked_in_at',
        // Rejet d'un retrait interne par l'agent payeur (voir migration
        // add_rejection_fields_to_transactions_table / InternalTransferController::
        // reject_internal_transaction).
        'rejection_reason', 'rejection_note', 'rejected_by_agent_id', 'rejected_at',
        // Taxes calculees automatiquement a la creation de la transaction via
        // TaxCalculationService (voir migration add_taxes_to_transactions_table et
        // table taxes) : TTF, Commission COBAC, TVA, Timbre electronique, leur
        // somme (total_taxes) et le montant total du frais d'envoi TTC.
        'ttf', 'commission_cobac', 'tva', 'timbre_electronique', 'total_taxes', 'frais_envoi_ttc', 'partner_fee',
        // Bénéficiaire Personnel ('P', défaut) ou Business ('B'), et combinaison
        // sender/bénéficiaire (p2p/b2b/b2p/p2b) — voir migration
        // add_receiver_business_fields_to_transactions_table et doc DigitWace
        // §VI Create Beneficiary / §XVII-XVIII.
        'receiver_type', 'receiver_business_name', 'receiver_business_type', 'receiver_expire_date', 'business_type',
        // AJOUT (2026-08-26) : voir migration add_receiver_address_city_email_to_transactions_table
        // et OutboundController::createDigitwaceBeneficiary() -- address/city/email reels du
        // beneficiaire (doc DigitWace §VI), auparavant jamais collectes ni stockes.
        'receiver_address', 'receiver_city', 'receiver_email',
        // Saisie facultative, dès la création mobile, des champs propres à
        // PawaPay (opérateur + motif/origine des fonds) — voir migration
        // add_pawapay_fields_to_transactions_table. Repris automatiquement à
        // l'étape de validation si le partenaire PawaPay est sélectionné,
        // même principe que les champs receiver_* DigitWace ci-dessus.
        'pawapay_operator', 'pawapay_purpose_of_funds', 'pawapay_source_of_funds',
    ];

    protected $dates = ['created_at','updated_at','beneficiary_checked_in_at','rejected_at'];

    public function sender(){
        return $this->belongsTo((Sender::exists()) ? Sender::class : null);
    }

    public function user(){
        return $this->belongsTo((User::exists()) ? User::class : null);
    }

    /**
     * FIX (2026-09-04, "Maximum execution time of 30 seconds exceeded" sur
     * TransactionController@search cote admin) : la recherche par plage de
     * dates faisait 1 requete HTTP par transaction validee (GET users/{id})
     * pour recuperer le nom de l'agent/CSA qui a valide -- un appel PHP
     * synchrone Guzzle par transaction, en boucle, sans aucun timeout. Sur
     * une plage de quelques semaines avec beaucoup de transactions validees,
     * la somme de ces appels sequentiels depassait le max_execution_time de
     * 30s de PHP (FatalError, page blanche cote admin). Cette relation
     * permet de charger le validateur en UNE seule requete via
     * _includes=...,validator (voir TransactionController@search cote
     * admin), au lieu d'une requete HTTP separee par transaction.
     */
    public function validator(){
        return $this->belongsTo((User::exists()) ? User::class : null, 'valid_id');
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
