<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Requests\AddressRequest;
use App\Api\V1\Requests\NoteRequest;
use App\Helpers\RestHelper;
use App\Http\Controllers\Controller;
use App\Note;
use Auth;

class NoteController extends Controller
{
    /**
     * Handle the incoming request.
     *
     */
    public function index(){
        return RestHelper::get(Note::class);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(NoteRequest $request)
    {
        return RestHelper::store(Note::class,$request->all());
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return RestHelper::show(Note::class,$id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param NoteRequest $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(NoteRequest $request, $id)
    {
        return RestHelper::update(Note::class,$request->all(),$id);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        return RestHelper::destroy(Note::class,$id);
    }
}
