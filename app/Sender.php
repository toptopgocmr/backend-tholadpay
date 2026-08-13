<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Sender extends Model
{
    //
    use RestTrait;

    // 'first_name','last_name', 'email', 'mobile_phone',
    protected $fillable = ['country', 'cni_number', 'cni_picture', 'justif_picture', 'sex', 'date_exp_id', 'type_id', 'user_id', 'valid', 'status',
                            'birth_date', 'title', 'postal_code', 'issuer_country', 'issuer_date', 'digitwace_code',
                            // Compte Personnel ('P', défaut) ou Business ('B') — voir migration
                            // add_business_fields_to_senders_table et doc DigitWace §V Create Sender.
                            'sender_type', 'business_name', 'business_type', 'business_register_date', 'business_comment', 'email',
                            ];

    protected $dates = ['created_at','updated_at'];

    public function transactions(){
        return $this->hasMany(Transaction::class);
    }

    public function images(){
        return $this->hasMany(Image::class);
    }

    public function user(){
        return $this->belongsTo((User::exists()) ? User::class : null);
    }
}
