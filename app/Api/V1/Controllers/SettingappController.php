<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
// use App\Settingapp;
use App\Services\AppAccessChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Carbon\Carbon;


class SettingappController extends Controller
{
    public function status()
    {
        $name = config('security.active_setting_name');
        // dd($name);
        if (!\Schema::hasTable('settingapp')) {
            return response()->json([
                'status' => 'error',
                'message' => "Erreur dans la table de configuration.",
            ], 500);
        }

        $setting = DB::table('settingapp')->where('name', $name)->first();

        if (!$setting) {
            return response()->json([
                'status' => 'error',
                'message' => "Aucune configuration trouvée pour '{$name}'.",
            ], 404);
        }

        $now = now()->timestamp;

        return response()->json([
            'app_name' => $name,
            'now' => $now,
            'start' => $setting->code1,
            'end' => $setting->code2,
            'active' => AppAccessChecker::isAppActive(),
            'message' => AppAccessChecker::isAppActive()
                ? 'Configuration active.'
                : 'Erreur système lors de la configuration.'
        ]);
    }



    public function convertToTimestamp(Request $request)
    {
        $date = $request->input('date');

        try {
            $timestamp = Carbon::parse($date)->timestamp;
            return response()->json(['timestamp' => $timestamp]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Date invalide.'], 400);
        }
    }


}
