<?php

namespace App\Api\V1\Controllers;

use App\Http\Controllers\Controller;
use Auth;

/**
 * @group Auth
 * Class RefreshController
 * @package App\Api\V1\Controllers
 */
class RefreshController extends Controller
{
    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        $token = Auth::guard()->refresh();

        return response()->json([
            'status' => 'ok',
            'token' => $token,
            'expires_in' => Auth::guard()->factory()->getTTL() * 60
        ]);
    }
}
