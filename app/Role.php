<?php

namespace App;

use App\Traits\RestTrait;
use Laratrust\Models\LaratrustRole;

class Role extends LaratrustRole
{
    //
    use RestTrait;

    protected $fillable = ['name','display_name','description'];

    protected $dates = ['created_at','updated_at'];

    public function getLabel()
    {
        return $this->name ;
    }

    public function users(){
        return $this->belongsToMany(User::class)
            ->withPivot('id','user_id','role_id');
    }
}
