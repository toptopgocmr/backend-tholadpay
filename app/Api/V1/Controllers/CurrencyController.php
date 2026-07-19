<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\CurrencyRequest;
use App\Currency;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * @group Currency
 * This class intend to implement all actions around Currency resource
 * Class CurrencyController
 * @package App\Api\V1\Controllers
 */
class CurrencyController extends Controller
{
    /**
     * Display a listing of currencies.
     *
     * @return JsonResponse
     */
    public function index()
    {
        return RestHelper::get(Currency::class);
    }

    /**
     * Store a newly created currency in storage.
     *
     * @param  CurrencyRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(CurrencyRequest $request)
    {
        return RestHelper::store(Currency::class, $request->all());
    }

    /**
     * Display the specified currency based on his id.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Currency::class, $id);
    }

    /**
     * Update the specified currency in storage based on his id.
     *
     * @param CurrencyRequest $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(CurrencyRequest $request, $id)
    {
        return RestHelper::update(Currency::class, $request->all(), $id);
    }

    /**
     * Remove the specified currency from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Currency::class, $id);
    }
}
