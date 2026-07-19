<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\WithdrawRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Withdraw;
use Illuminate\Support\Facades\DB;

/**
 * @group Withdraw
 * This class is intended to manage all actions related to Withdraw resource
 * Class WithdrawController
 * @package App\Api\V1\Controllers
 */
class WithdrawController extends Controller
{
    /**
     * Entry point where we list all Withdraws from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Withdraw::class);
    }

    /**
     * Store a newly created Withdraw in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(WithdrawRequest $request)
    {
        return RestHelper::store(Withdraw::class, $request->all());
    }

    /**
     * Display the specified Withdraw.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Withdraw::class,$id);
    }

    /**
     * Update the specified Withdraw in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(WithdrawRequest $request,$id)
    {
        return RestHelper::update(Withdraw::class,$request->all(),$id);
    }

    /**
     * Remove the specified Withdraw from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Withdraw::class,$id);
    }

    public function fundSpec($code) {
        $limitFund = DB::table('country_funds')
            ->join('countries as c1', 'c1.id', '=', 'country_funds.country_id')
            ->where('c1.iso_3166_2', $code)
            ->get();
        return response()->success(compact('limitFund'));
    }

}
