<?php

namespace App;

use App\Traits\RestTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class RoleUser extends Model
{
    //
    use RestTrait;

    protected $table = 'role_user';
    public $timestamps = false;
    protected $fillable = ['user_id', 'role_id', 'user_type'];
    protected $primaryKey = ['user_id', 'role_id'];
    public $incrementing = false;

    public function getLabel()
    {
        return $this->user_id . '-' . $this->role_id;
    }

    public function user()
    {
        return $this->belongsTo((User::exists()) ? User::class : null);
    }

    public function role()
    {
        return $this->belongsTo((Role::exists()) ? Role::class : null);
    }

    protected function setKeysForSaveQuery($query)
    {
        $query
            ->where('user_id', '=', $this->getAttribute('user_id'))
            ->where('role_id', '=', $this->getAttribute('role_id'));
        return $query;
    }
}
