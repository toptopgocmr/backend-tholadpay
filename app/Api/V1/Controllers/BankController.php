<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\BankRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Bank;

/**
 * @group Bank
 * This class is intended to manage all actions related to Bank resource
 * Class BankController
 * @package App\Api\V1\Controllers
 */
class BankController extends Controller
{
    /**
     * Entry point where we list all Banks from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Bank::class);
    }

    /**
     * Store a newly created Bank in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(BankRequest $request)
    {
        return RestHelper::store(Bank::class, $request->all());
    }

    /**
     * Display the specified Bank.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Bank::class,$id);
    }

    /**
     * Update the specified Bank in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(BankRequest $request,$id)
    {
        return RestHelper::update(Bank::class,$request->all(),$id);
    }

    /**
     * Remove the specified Bank from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Bank::class,$id);
    }

}
