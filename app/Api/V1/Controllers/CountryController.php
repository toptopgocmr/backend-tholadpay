<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\CountryRequest;
use App\Country;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @group Country
 * This class intend to implement all actions around Country resource
 * Class CountryController
 * @package App\Api\V1\Controllers
 */
class CountryController extends Controller
{
    /**
     * Display a listing of countries.
     *
     * @return JsonResponse
     */
    public function index(){
        return RestHelper::get(Country::class);
    }

    /**
     * Store a newly created country in storage.
     *
     * @param  CountryRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CountryRequest $request)
    {
        return RestHelper::store(Country::class,$request->all());
    }

    /**
     * Display the specified country based on his id.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Country::class,$id);
    }

    /**
     * Update the specified country in storage based on his id.
     *
     * @param  CountryRequest $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CountryRequest $request, $id)
    {
        return RestHelper::update(Country::class,$request->all(),$id);
    }

    /**
     * Remove the specified country from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Country::class,$id);
    }
}
