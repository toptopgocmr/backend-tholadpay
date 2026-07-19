<?php

use Illuminate\Database\Seeder;

class CountriesSeeder extends Seeder {

    /**
     * Run the database seeds.
     *
     * @return  void
     */
    public function run()
    {
        //Empty the countries table
//        DB::table(\Config::get('countries.table_name'))->delete();

        //Get all of the countries
//        $countries = Countries::getList();
        $path = base_path("database/seeds/json/countries.json");
        $countries = json_decode(file_get_contents($path), true);
        foreach ($countries as $c) {
            $r = \App\Country::create($c);
        }
    }
}
