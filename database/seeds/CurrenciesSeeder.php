<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;

class CurrenciesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $path = base_path("database/seeds/json/currencies.json");
        $currencies = json_decode(file_get_contents($path), true);
        $cs=\App\Country::whereIn('currency_code', $currencies)->get();
        foreach($cs as $c){$c->is_covered=true;$c->save();}
    }
}
