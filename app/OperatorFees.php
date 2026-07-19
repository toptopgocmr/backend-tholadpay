<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class OperatorFees extends Model
{
    //
    use RestTrait;

    protected $fillable = ['country', 'operator_code', 'operator_name',  'min',  'max',  'fees',
        'type', 'status'
    ];

    protected $dates = ['created_at','updated_at'];
}
