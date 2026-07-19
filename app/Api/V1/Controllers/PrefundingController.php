<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\PrefundingRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Services\AppAccessChecker;
use App\Prefunding;

/**
 * @group Prefunding
 * This class is intended to manage all actions related to Prefunding resource
 * Class PrefundingController
 * @package App\Api\V1\Controllers
 */
class PrefundingController extends Controller
{
    /**
     * Entry point where we list all Prefundings from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Prefunding::class);
    }

    /**
     * Store a newly created Prefunding in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(PrefundingRequest $request)
    {

        if (!AppAccessChecker::isAppActive()) {
            return response()->error('Erreur lors du prefunding', 403);
        }
        return RestHelper::store(Prefunding::class, $request->all());
    }

    /**
     * Display the specified Prefunding.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Prefunding::class,$id);
    }

    /**
     * Update the specified Prefunding in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(PrefundingRequest $request,$id)
    {
        return RestHelper::update(Prefunding::class,$request->all(),$id);
    }

    /**
     * Remove the specified Prefunding from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Prefunding::class,$id);
    }

}
