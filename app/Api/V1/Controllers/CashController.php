<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\CashRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Cash;
use Illuminate\Support\Facades\DB;

/**
 * @group Cash
 * This class is intended to manage all actions related to Cash resource
 * Class CashController
 * @package App\Api\V1\Controllers
 */
class CashController extends Controller
{
    /**
     * Entry point where we list all Cashs from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Cash::class);
    }

    /**
     * Store a newly created Cash in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CashRequest $request)
    {
        return RestHelper::store(Cash::class, $request->all());
    }

    /**
     * Display the specified Cash.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Cash::class,$id);
    }

    /**
     * Update the specified Cash in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CashRequest $request,$id)
    {
        return RestHelper::update(Cash::class,$request->all(),$id);
    }

    /**
     * Remove the specified Cash from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Cash::class,$id);
    }

    public function fundSpec($code) {
        $limitFund = DB::table('country_funds')
            ->join('countries as c1', 'c1.id', '=', 'country_funds.country_id')
            ->where('c1.iso_3166_2', $code)
            ->get();
        return response()->success(compact('limitFund'));
    }

}
