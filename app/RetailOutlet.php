<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class RetailOutlet extends Model
{    
    //
    use RestTrait;

    protected $fillable = ['name', 'description', 'town_id', 'rue', 'status'];

    protected $dates = ['created_at','updated_at'];

    protected $appends=['all_status'];

    public static $Status = ['new', 'done', 'cancel'];

    public function getAllStatusAttribute(){
        return self::$Status;
    }

    public function getLabel(){
        return $this->status ;
    }

    public function town(){
        return $this->belongsTo(Town::class);
    }
}
