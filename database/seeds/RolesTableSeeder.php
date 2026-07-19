<?php

use App\Permission;
use Illuminate\Database\Seeder;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $path =base_path("database/seeds/json/roles.json");
        $slim_roles = json_decode(file_get_contents($path),true);
        foreach ($slim_roles as $jr){
            $ps = $jr["permissions"];
            unset($jr['permissions']);
            $r =  \App\Role::create($jr);
            if($ps&&$r){
                $permissions = Permission::whereIn('name',$ps)->get()->pluck('id')->toArray() ;
                $r->permissions()->sync($permissions);
            }
        }

    }
}
