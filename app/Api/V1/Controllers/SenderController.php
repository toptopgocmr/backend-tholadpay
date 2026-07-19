<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\SenderRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Sender;
use Illuminate\Http\JsonResponse;

/**
 * @group Sender
 * This class is intended to manage all actions related to Sender resource
 * Class SenderController
 * @package App\Api\V1\Controllers
 */
class SenderController extends Controller
{
    /**
     * Entry point where we list all Senders from the database
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(){
        return RestHelper::get(Sender::class);
    }

    /**
     * Store a newly created Sender in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(SenderRequest $request)
    {
        return RestHelper::store(Sender::class, $request->all());
    }

    /**
     * Display the specified Sender.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Sender::class,$id);
    }

    /**
     * Update the specified Sender in storage.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(SenderRequest $request,$id)
    {
        return RestHelper::update(Sender::class,$request->all(),$id);
    }

    /**
     * Remove the specified Sender from storage.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Sender::class,$id);
    }

}
