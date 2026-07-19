<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\InboundRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Inbound;

/**
 * @group Inbound
 * This class is intended to manage all actions related to Inbound resource
 * Class InboundController
 * @package App\Api\V1\Controllers
 */
class InboundController extends Controller
{
    /**
     * Entry point where we list all Inbounds from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Inbound::class);
    }

    /**
     * Store a newly created Inbound in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(InboundRequest $request)
    {
        return RestHelper::store(Inbound::class, $request->all());
    }

    /**
     * Display the specified Inbound.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Inbound::class,$id);
    }

    /**
     * Update the specified Inbound in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(InboundRequest $request,$id)
    {
        return RestHelper::update(Inbound::class,$request->all(),$id);
    }

    /**
     * Remove the specified Inbound from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Inbound::class,$id);
    }

}
