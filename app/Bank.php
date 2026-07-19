<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    //
    use RestTrait;

    protected $fillable = ['bank_account_no', 'short_code', 'organisation', 'bank_address', 'outbound_id', 'inbound_id'];

    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'bank_account_no' => 'string',
        'short_code' => 'string',
    ];

    public function outbound()
    {
        return $this->belongsTo(Outbound::class);
    }

    public function inbound()
    {
        return $this->belongsTo(Inbound::class);
    }

    public static function boot()
    {
        parent::boot();
    }
}
