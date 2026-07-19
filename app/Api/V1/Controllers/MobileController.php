<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\MobileRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Mobile;

/**
 * @group Mobile
 * This class is intended to manage all actions related to Mobile resource
 * Class MobileController
 * @package App\Api\V1\Controllers
 */
class MobileController extends Controller
{
    /**
     * Entry point where we list all Mobiles from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Mobile::class);
    }

    /**
     * Store a newly created Mobile in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(MobileRequest $request)
    {
        return RestHelper::store(Mobile::class, $request->all());
    }

    /**
     * Display the specified Mobile.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Mobile::class,$id);
    }

    /**
     * Update the specified Mobile in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(MobileRequest $request,$id)
    {
        return RestHelper::update(Mobile::class,$request->all(),$id);
    }

    /**
     * Remove the specified Mobile from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Mobile::class,$id);
    }

}
