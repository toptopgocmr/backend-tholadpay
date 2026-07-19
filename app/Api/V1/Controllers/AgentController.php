<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\AgentRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Agent;

/**
 * @group Agent
 * This class is intended to manage all actions related to Agent resource
 * Class AgentController
 * @package App\Api\V1\Controllers
 */
class AgentController extends Controller
{
    /**
     * Entry point where we list all Agents from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Agent::class);
    }

    /**
     * Store a newly created Agent in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(AgentRequest $request)
    {
        return RestHelper::store(Agent::class, $request->all());
    }

    /**
     * Display the specified Agent.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Agent::class,$id);
    }

    /**
     * Update the specified Agent in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(AgentRequest $request,$id)
    {
        return RestHelper::update(Agent::class,$request->all(),$id);
    }

    /**
     * Remove the specified Agent from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Agent::class,$id);
    }

}
