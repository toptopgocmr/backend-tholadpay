<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\TaxRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Tax;

/**
 * @group Tax
 * This class is intended to manage all actions related to Tax resource
 * Class TaxController
 * @package App\Api\V1\Controllers
 */
class TaxController extends Controller
{
    /**
     * Entry point where we list all Taxes from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Tax::class);
    }

    /**
     * Store a newly created Tax in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(TaxRequest $request)
    {
        return RestHelper::store(Tax::class, $request->all());
    }

    /**
     * Display the specified Tax.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Tax::class,$id);
    }

    /**
     * Update the specified Tax in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(TaxRequest $request,$id)
    {
        return RestHelper::update(Tax::class,$request->all(),$id);
    }

    /**
     * Remove the specified Tax from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Tax::class,$id);
    }

}
