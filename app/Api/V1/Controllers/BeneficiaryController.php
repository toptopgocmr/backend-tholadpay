<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\BeneficiaryRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Beneficiary;
use Illuminate\Support\Facades\DB;

/**
 * @group Beneficiary
 * This class is intended to manage all actions related to Beneficiary resource
 * Class BeneficiaryController
 * @package App\Api\V1\Controllers
 */
class BeneficiaryController extends Controller
{
    /**
     * Entry point where we list all Beneficiarys from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Beneficiary::class);
    }

    /**
     * Store a newly created Beneficiary in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(BeneficiaryRequest $request)
    {
        return RestHelper::store(Beneficiary::class, $request->all());
    }

    /**
     * Display the specified Beneficiary.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Beneficiary::class,$id);
    }

    /**
     * Update the specified Beneficiary in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(BeneficiaryRequest $request,$id)
    {
        return RestHelper::update(Beneficiary::class,$request->all(),$id);
    }

    /**
     * Remove the specified Beneficiary from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Beneficiary::class,$id);
    }

    public function fundSpec($code) {
        $limitFund = DB::table('country_funds')
            ->join('countries as c1', 'c1.id', '=', 'country_funds.country_id')
            ->where('c1.iso_3166_2', $code)
            ->get();
        return response()->success(compact('limitFund'));
    }

}
