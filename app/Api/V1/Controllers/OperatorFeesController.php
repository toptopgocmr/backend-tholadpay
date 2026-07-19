<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\OperatorFeesRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\OperatorFees;
use Illuminate\Support\Facades\DB;

/**
 * @group OperatorFees
 * This class is intended to manage all actions related to OperatorFees resource
 * Class OperatorFeesController
 * @package App\Api\V1\Controllers
 */
class OperatorFeesController extends Controller
{
    /**
     * Entry point where we list all OperatorFeess from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(OperatorFees::class);
    }

    /**
     * Store a newly created OperatorFees in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(OperatorFeesRequest $request)
    {
        return RestHelper::store(OperatorFees::class, $request->all());
    }

    /**
     * Display the specified OperatorFees.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(OperatorFees::class,$id);
    }

    /**
     * Update the specified OperatorFees in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(OperatorFeesRequest $request,$id)
    {
        return RestHelper::update(OperatorFees::class,$request->all(),$id);
    }

    /**
     * Remove the specified OperatorFees from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(OperatorFees::class,$id);
    }

    public function fundSpec($code) {
        $limitFund = DB::table('country_funds')
            ->join('countries as c1', 'c1.id', '=', 'country_funds.country_id')
            ->where('c1.iso_3166_2', $code)
            ->get();
        return response()->success(compact('limitFund'));
    }

}
