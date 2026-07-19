<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\ZoneRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Zone;

/**
 * @group Zone
 * This class is intended to manage all actions related to Zone resource
 * Class ZoneController
 * @package App\Api\V1\Controllers
 */
class ZoneController extends Controller
{
    /**
     * Entry point where we list all Zones from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Zone::class);
    }

    /**
     * Store a newly created Zone in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(ZoneRequest $request)
    {
        return RestHelper::store(Zone::class, $request->all());
    }

    /**
     * Display the specified Zone.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Zone::class,$id);
    }

    /**
     * Update the specified Zone in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ZoneRequest $request,$id)
    {
        return RestHelper::update(Zone::class,$request->all(),$id);
    }

    /**
     * Remove the specified Zone from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Zone::class,$id);
    }

}