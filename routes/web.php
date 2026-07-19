<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;

Route::get('/test-view', function () {
    $b=App\Bill::first();
//    return view('pdfs.bill',['bill' => $b,'user'=>$b->offer->user]);
    dispatch(new \App\Jobs\GenerateBill($b));

    $file = Storage::get($b->pdf);
    $ftype=Storage::mimeType($b->pdf);
    $response = Response::make($file, 200);
    $response->header("Content-Type", $ftype);
    return $response;


});

Route::get('reset_password/{token}', ['as' => 'password.reset', function($token)
{
    // implement your reset password route here!
}]);

Route::get('/', function () {
    return view('welcome');
});


Route::get('/img/{model}/{image}', function ($model, $image) {

    return \App\Helpers\RestHelper::getFile('img',$model,$image);
    //return Storage::get("stock-images/".$type."/".$image); //will ensure a jpg is always returned
})->where('image', '.*');
Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');
Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
