<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;

class PermissionRole extends Model
{
    //
    use RestTrait;

    protected $table = 'permission_role';
    public $timestamps = false;
    protected $fillable = ['permission_id','role_id'];

    public function getLabel()
    {
        return $this->role_id.'-'.$this->permission_id ;
    }

    public function permission(){
        return $this->belongsTo(Permission::class);
    }

    public function role(){
        return $this->belongsTo(Role::class);
    }
}
