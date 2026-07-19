<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AppAccessChecker
{

    public static function isAppActive(): bool
    {
        $appName = config('security.active_setting_name');
        if (!Schema::hasTable('settingapp')) {
            return false;
        }

        $name = config('security.active_setting_name');

        $setting = DB::table('settingapp')->where('name', $appName)->first();

        if (!$setting) {
            return false;
        }        

        $now = now()->timestamp;

        return $now >= $setting->code1 && $now <= $setting->code2;
    }
}
