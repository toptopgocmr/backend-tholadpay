<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\CountryRequest;
use App\Api\V1\Requests\LimitFundsRequest;
use App\Country;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\LimitFunds;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @group Country
 * This class intend to implement all actions around Country resource
 * Class CountryController
 * @package App\Api\V1\Controllers
 */
class LimitFundController extends Controller
{
    /**
     * Entry point where we list all LimitFunds from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(LimitFunds::class);
    }

    /**
     * Store a newly created LimitFunds in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(LimitFundsRequest $request)
    {
        return RestHelper::store(LimitFunds::class, $request->all());
    }

    /**
     * Display the specified LimitFunds.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(LimitFunds::class,$id);
    }

    /**
     * Update the specified LimitFunds in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(LimitFundsRequest $request,$id)
    {
        return RestHelper::update(LimitFunds::class,$request->all(),$id);
    }

    /**
     * Remove the specified LimitFunds from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(LimitFunds::class,$id);
    }

    public function fundSpec($from, $to) {
        $limitFund = DB::table('limit_funds')
            ->join('countries as c', 'c.id', '=', 'limit_funds.country_id')
            ->join('zones as z', 'z.id', '=', 'limit_funds.zone_id')
            ->where('c.iso_3166_2', $from)
            ->where('z.name', $to)
            ->get();
        return response()->success(compact('limitFund'));
    }
}
