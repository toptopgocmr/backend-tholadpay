<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\UserFundsRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\UserFunds;

/**
 * @group UserFunds
 * This class is intended to manage all actions related to UserFunds resource
 * Class UserFundsController
 * @package App\Api\V1\Controllers
 */
class UserFundsController extends Controller
{
    /**
     * Entry point where we list all UserFundss from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(UserFunds::class);
    }

    /**
     * Store a newly created UserFunds in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(UserFundsRequest $request)
    {
        return RestHelper::store(UserFunds::class, $request->all());
    }

    /**
     * Display the specified UserFunds.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(UserFunds::class,$id);
    }

    /**
     * Update the specified UserFunds in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UserFundsRequest $request,$id)
    {
        return RestHelper::update(UserFunds::class,$request->all(),$id);
    }

    /**
     * Remove the specified UserFunds from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(UserFunds::class,$id);
    }

}
