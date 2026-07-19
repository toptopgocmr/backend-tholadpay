<?php

use Illuminate\Database\Seeder;

class PermissionsTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $namespace = 'App\\';
        $path =base_path("database/seeds/json/permissions.json");
        $slim_permissions = json_decode(file_get_contents($path),true);
        foreach ($slim_permissions as $p){
            \App\Permission::create($p);
        }
    }
}
