<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\CountryFundsRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\CountryFunds;
use Illuminate\Support\Facades\DB;

/**
 * @group CountryFunds
 * This class is intended to manage all actions related to CountryFunds resource
 * Class CountryFundsController
 * @package App\Api\V1\Controllers
 */
class CountryFundsController extends Controller
{
    /**
     * Entry point where we list all CountryFundss from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(CountryFunds::class);
    }

    /**
     * Store a newly created CountryFunds in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CountryFundsRequest $request)
    {
        return RestHelper::store(CountryFunds::class, $request->all());
    }

    /**
     * Display the specified CountryFunds.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(CountryFunds::class,$id);
    }

    /**
     * Update the specified CountryFunds in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CountryFundsRequest $request,$id)
    {
        return RestHelper::update(CountryFunds::class,$request->all(),$id);
    }

    /**
     * Remove the specified CountryFunds from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(CountryFunds::class,$id);
    }

    public function fundSpec($code) {
        $limitFund = DB::table('country_funds')
            ->join('countries as c1', 'c1.id', '=', 'country_funds.country_id')
            ->where('c1.iso_3166_2', $code)
            ->get();
        return response()->success(compact('limitFund'));
    }

}
