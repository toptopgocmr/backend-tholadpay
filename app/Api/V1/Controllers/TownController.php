<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\TownRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Town;
use Hash;

/**
 * @group Town
 * This class is intended to manage all actions related to Town resource
 * Class TownController
 * @package App\Api\V1\Controllers
 */
class TownController extends Controller
{
    /**
     * Entry point where we list all towns from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        return RestHelper::get(Town::class);
    }

    /**
     * Store a newly created town in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(TownRequest $request)
    {
        return RestHelper::store(Town::class, $request->all());
    }

    /**
     * Display the specified town.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Town::class, $id);
    }

    /**
     * Update the specified town in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(TownRequest $request, $id)
    {
        return RestHelper::update(Town::class, $request->all(), $id);
    }

    /**
     * Remove the specified town from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Town::class, $id);
    }
}
