<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\AddressRequest;
use App\Api\V1\Requests\RetailOuletRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\RetailOutlet;
use Auth;

class RetailOuletController extends Controller
{
    /**
     * Handle the incoming request.
     *
     */
    public function index(){
        return RestHelper::get(RetailOutlet::class);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(RetailOuletRequest $request)
    {
        return RestHelper::store(RetailOutlet::class,$request->all());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(RetailOutlet::class,$id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param RetailOuletRequest $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(RetailOuletRequest $request, $id)
    {
        return RestHelper::update(RetailOutlet::class,$request->all(),$id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(RetailOutlet::class,$id);
    }
}
