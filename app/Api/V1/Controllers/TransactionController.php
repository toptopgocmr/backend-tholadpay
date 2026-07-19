<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\TransactionRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Transaction;

/**
 * @group Transaction
 * This class is intended to manage all actions related to Transaction resource
 * Class TransactionController
 * @package App\Api\V1\Controllers
 */
class TransactionController extends Controller
{
    /**
     * Entry point where we list all Transactions from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Transaction::class);
    }

    /**
     * Store a newly created Transaction in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(TransactionRequest $request)
    {
        return RestHelper::store(Transaction::class, $request->all());
    }

    /**
     * Display the specified Transaction.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Transaction::class,$id);
    }

    /**
     * Update the specified Transaction in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(TransactionRequest $request,$id)
    {
        return RestHelper::update(Transaction::class,$request->all(),$id);
    }

    /**
     * Remove the specified Transaction from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Transaction::class,$id);
    }

}
