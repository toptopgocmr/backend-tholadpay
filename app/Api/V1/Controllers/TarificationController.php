<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\TarificationRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Tarification;

/**
 * @group Tarification
 * This class is intended to manage all actions related to Tarification resource
 * Class TarificationController
 * @package App\Api\V1\Controllers
 */
class TarificationController extends Controller
{
    /**
     * Entry point where we list all Tarifications from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Tarification::class);
    }

    /**
     * Store a newly created Tarification in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(TarificationRequest $request)
    {
        return RestHelper::store(Tarification::class, $request->all());
    }

    /**
     * Display the specified Tarification.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Tarification::class,$id);
    }

    /**
     * Update the specified Tarification in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(TarificationRequest $request,$id)
    {
        return RestHelper::update(Tarification::class,$request->all(),$id);
    }

    /**
     * Remove the specified Tarification from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Tarification::class,$id);
    }

}
