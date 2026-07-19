<?php

/**
 * Created by PhpStorm.
 * User: Foris
 * Date: 20/10/2016
 * Time: 23:13
 */

namespace App\Helpers;


use App\User;
use Auth;
use BadMethodCallException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request as Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class RestHelper
{

    public static function get($Model)
    {
        $ms = new  $Model;
        $ord = $ms->timestamps;
        $data = Input::input();
        $field = $ms->getFillable();
        $dates = $ms->getDates();

        $format = Input::has('_format') ? Input::get('_format') : 'json';
        $agg = Input::has('_agg') ? explode('|', Input::get('_agg')) : ['get'];
        $excel_ext = ['xls', 'csv', 'xlxs', 'pdf'];
        $page = Input::has('page') ? Input::get('page') : 1;
        $per_page = Input::has('per_page') ? Input::get('per_page') : 10;
        $paginate = !(Input::has('should_paginate') && Input::get('should_paginate') == 'false');
        $order_d = Input::get('_sortDir') == 'desc' ? Input::get('_sortDir') : 'asc';
        $sort = Input::has('_sort')  ? Input::get('_sort') : 'updated_at';
        $withTrashed  = Input::has('_withTrashed')  ? Input::get('_withTrashed') : false;
        $onlyTrashed  = Input::has('_onlyTrashed')  ? Input::get('_onlyTrashed') : false;

        //        $appends = Input::has('_appends') ? explode(',', Input::get('_appends')) : $ms->getAppends();
        $append_relateds = Input::has('_append_relateds') ? explode(',', Input::get('_append_relateds')) : [];
        $ms->setAppendRelateds($append_relateds);
        $fo = Input::has('_includes') ? explode(',', Input::get('_includes')) : $ms->getForeign();
        $co = Input::has('_counts') ? explode(',', Input::get('_counts')) : [];
        $wantFields = Input::has('_fields') ? explode(',', Input::get('_fields')) : false;
        $withIncluded = Input::get('_includes') == '';

        //        dd($wantFields);

        foreach ($data as $key => $value) {
            $tkey = explode('-', $key);
            $op = "=";
            if (count($tkey) == 2 && $tkey[1] != 'fk' && (in_array($tkey[0], $field) || in_array($tkey[0], $dates))) {
                $opres = self::get_operator($tkey[1], $value);
                $op = $opres[0];
                $key = $tkey[0];
                $value = $opres[1];


                if ($tkey[1] == 'in') {
                    $ms = $ms->whereIn($key, $value);
                } elseif ($tkey[1] == 'not_in') {
                    $ms = $ms->whereNotIn($key, $value);
                } elseif ($tkey[1] == 'bt') {
                    $ms = $ms->whereBetween($key, $value);
                } elseif ($tkey[1] == 'not_bt') {
                    $ms = $ms->whereNotBetween($key, $value);
                } elseif ($tkey[1] == 'not_nl') {
                    $ms = $ms->whereNotNull($key);
                } elseif ($tkey[1] == 'nl') {
                    $ms = $ms->whereNull($key);
                } elseif ($tkey[1] == 'gb') {
                    //$has_groupby = true;
                    $ms = $ms->groupBy($key)
                        ->selectRaw("*,$value");
                } else {
                    if (in_array($key, $dates)) {
                        //dd($dates);
                        $ms = $ms->whereDate($key, $op, $value);
                    } else {
                        $ms = $ms->where($key, $op, $value);
                    }
                }
            } elseif (count($tkey) == 2 && $tkey[1] == 'fk') {
                foreach (explode('|', $value) as $fkey) {
                    $ttm = explode('=', $fkey);
                    if (count($ttm) == 2) {
                        $ftkey = explode('-', $ttm[0]);
                        $op = '=';
                        if (count($ftkey) == 2) {

                            $ms = $ms->whereHas($tkey[0], function ($query) use ($ftkey, $ttm) {
                                $opkey = $ftkey[1];
                                $opres = self::get_operator($opkey, $ttm[1]);
                                $op = $opres[0];
                                $key = $ftkey[0];
                                $value = $opres[1];
                                if ($opkey == 'in') {
                                    $query->whereIn($key, $value);
                                } elseif ($opkey == 'not_in') {
                                    $query->whereNotIn($key, $value);
                                } elseif ($opkey == 'bt') {
                                    $query->whereBetween($key, $value);
                                } elseif ($opkey == 'not_bt') {
                                    $query->whereNotBetween($key, $value);
                                } elseif ($opkey == 'not_nl') {
                                    $query->whereNotNull($key);
                                } elseif ($opkey == 'nl') {
                                    $query->whereNull($key);
                                } elseif ($opkey == 'gb') {
                                    //$has_groupby = true;
                                    $query->groupBy($key)
                                        ->selectRaw("*,$value");
                                } else {
                                    /*if(in_array($key, $dates)){
                                        $ms = $ms->whereDate($key, $op, $value);
                                    } else {
                                        $ms = $ms->where($key, $op, $value);
                                    }*/
                                    $query->where($key, $op, $value);
                                }
                            });
                        } elseif (count($ftkey) == 1) {
                            //                            dd($tkey);
                            $ms = $ms->whereHas($tkey[0], function ($query) use ($ftkey, $ttm) {
                                $query->where($ftkey[0], '=', $ttm[1]);
                            });
                        }
                    }
                }
            } else if (in_array($key, $field) || in_array($key, $dates)) {
                if (in_array($key, $dates)) {
                    $ms = $ms->whereDate($key, $op, $value);
                } else {

                    $ms = $ms->where($key, $op, $value);
                }
            }
        }
        try {
            $sort_collect = false;
            if ($ord) {
                if (in_array($sort, $field) || in_array($sort, $dates)) {
                    $ms = $ms->orderBy($sort, $order_d);
                } else {
                    $sort_collect = true;
                }
            }
            if ($withTrashed) {
                $ms = $ms->withTrashed();
            } elseif ($onlyTrashed) {
                $ms = $ms->onlyTrashed();
            }

            //            $ms->setAppends($appends);
            if (!$withIncluded)
                $ms = $ms->with($fo);
            if (count($co) > 0)
                $ms = $ms->withCount($co);
            if (is_array($wantFields)) {
                $ms = $ms->select($wantFields);
            }
            if ($paginate) {
                if ($sort_collect) {
                    $ms = $ms->get();
                    $total = $ms->count();
                    if (strtolower($order_d) == 'desc') {
                        $ms = $ms->sortByDesc($sort);
                    } else {
                        $ms = $ms->sortBy($sort);
                    }
                    //$ms = $ms->all();
                    $ms = $ms->forPage($page, $per_page);
                    $ms = $ms->values()->all();
                    $to = $per_page * $page < $total ? $per_page * $page : $total;
                    $ms = [
                        "total" => $total,
                        "per_page" => $per_page,
                        "current_page" => $page,
                        "last_page" => ceil($total / $per_page),
                        "next_page_url" => null,
                        "prev_page_url" => null,
                        "from" => $per_page * ($page - 1) + 1,
                        "to" => $to,
                        "data" => $ms
                    ];
                } else {
                    $ms = $ms->paginate($per_page);
                }
            } else {
                if ($agg[0] == 'get') {
                    if ($sort_collect) {
                        $ms = $ms->get();
                        $ms = $ms->sortBy($sort);
                    } else {
                        $ms = $ms->get();
                    }
                } elseif ($agg[0] == 'sum') {
                    $ms = $ms->sum($agg[1]);
                } elseif ($agg[0] == 'count') {
                    $ms = $ms->count();
                } elseif ($agg[0] == 'max') {
                    $ms = $ms->max($agg[1]);
                } elseif ($agg[0] == 'min') {
                    $ms = $ms->min($agg[1]);
                } elseif ($agg[0] == 'avg') {
                    $ms = $ms->avg($agg[1]);
                }
            }

            if (in_array($format, $excel_ext)) {
                return Excel::create('itsolutionstuff_example', function ($excel) use ($ms) {
                    $excel->sheet('mySheet', function ($sheet) use ($ms) {
                        $sheet->fromArray($ms);
                    });
                })->download($format);
            }

            return Response::json($ms, 200, []); // , JSON_NUMERIC_CHECK

        } catch (BadMethodCallException $e) {
            return Response::json([
                'error' => 'includes methods does not exists',
                'reason' => $e->getMessage()
            ], 400, [], JSON_NUMERIC_CHECK);
        }
    }

    private static function get_operator($op, $value)
    {
        switch ($op) {
                // group by
            case 'gb': {
                    return ["gb", $value];
                }
                // greater than
            case 'gt': {
                    return [">", $value];
                }
                // greater or equal to
            case 'get': {
                    return [">=", $value];
                }
                // less than
            case 'lt': {

                    return ["<", $value];
                }
                // less or equal to
            case 'let': {

                    return ["<=", $value];
                }
                // like
            case 'lk': {

                    return ["like", '%' . $value . '%'];
                }
                // not like
            case 'not_lk': {

                    return ["not like", '%' . $value . '%'];
                }
                // not equal
            case 'ne': {

                    return ["!=", $value];
                }
                // null
            case 'nl': {

                    return ["=", $value];
                }
                // not null
            case 'not_nl': {

                    return ["=", $value];
                }
                // in
            case 'in': {

                    return ["in", explode(',', $value)];
                }
                // not in
            case 'not_in': {

                    return ["not in", explode(',', $value)];
                }
                // between
            case 'bt': {
                    return ["bt", explode(',', $value)];
                }
                // not between
            case 'not_bt': {

                    return ["not_bt", explode(',', $value)];
                }

            default:
                return ["=", $value];
        }
    }

    public static function store($Model, $data)
    {

        $m = self::pre_store($Model, $data);

        $m->save();

        return self::post_store($Model, $m);
    }

    public static function pre_store($Model, $data)
    {
        $m = new $Model;
        $name = explode("App\\", $Model)[1];
        $field = $m->getFillable();
        foreach ($data as $key => $value) {
            if (in_array($key, $field)) {
                if (in_array($key, $m->getFiles())) {
                    $image = $value;
                    $fpath = "img/" . strtolower($name) . "/" . uniqid() . '_' . $image->getClientOriginalName();
                    $m->$key = $fpath;
                    Storage::put($fpath, File::get($image), 'public');
                } else {
                    $m->$key = $value;
                }
            }
        }

        return $m;
    }


    public static function post_store($Model, $m)
    {
        $name = explode("App\\", $Model)[1];
        $m = $Model::with($m->getForeign())->find($m->id);
        Log::info('the ' . $name . '  ' . $m->getLabel() . ' has been created by '
            . self::request_author());
        return Response::json($m, 200, [], JSON_NUMERIC_CHECK);
    }

    public static function show($Model, $id)
    {
        $m = new $Model;
        $name = explode("App\\", $Model)[1];
        //        $appends = Input::has('_appends') ? explode(',', Input::get('_appends')) : $m->getAppends();
        $fo = Input::has('_includes') ? explode(',', Input::get('_includes')) : $m->getForeign();

        try {

            //            $m->appends=$appends;
            $m = $Model::with($fo)->find($id);
        } catch (BadMethodCallException $e) {
            return Response::json([
                'error' => 'includes methods does not exists',
                'reason' => $e->getMessage()
            ], 400, [], JSON_NUMERIC_CHECK);
        }
        if ($m) {
            return Response::json($m, 200, []); // , JSON_NUMERIC_CHECK // formate les chiffres long en hexadeximal
        } else {
            return Response::json(array("erreur" => "the " . $name . " you are looking for does not exist"), 422);
        }
    }

    public static function update($Model, $data, $id)
    {


        $m = self::pre_update($Model, $data, $id);
        $name = explode("App\\", $Model)[1];

        if ($m == null)
            return Response::json(array("erreur" => "the " . $name . " you are looking for does not exist"), 422);

        $m->save();
        return self::post_update($Model, $m);
    }

    public static function pre_update($Model, $data, $id)
    {
        $m = $Model::find($id);
        if ($m) {
            $field = $m->getFillable();
            $name = explode("App\\", $Model)[1];
            foreach ($data as $key => $value) {
                if (in_array($key, $field)) {
                    if (in_array($key, $m->getFiles())) {
                        $fop = $m->getOriginal()[$key];
                        if (Storage::has($fop))
                            Storage::delete($fop);
                        $image = $value;
                        $fpath = "img/" . strtolower($name) . "/" . uniqid() . '_' . $image->getClientOriginalName();
                        $m->$key = $fpath;
                        Storage::put($fpath, File::get($image));
                    } else {
                        $m->$key = $value;
                    }
                }
            }
            return $m;
        } else {
            return null;
        }
    }

    public static function post_update($Model, $m)
    {

        $name = explode("App\\", $Model)[1];
        $m = $Model::with($m->getForeign())->find($m->id);
        Log::info('the ' . $name . ' ' . $m->getLabel() . ' has been updated by '
            . self::request_author());
        return Response::json($m, 200, [], JSON_NUMERIC_CHECK);
    }

    public static function destroy($Model, $id, $force = false)
    {
        $m = $Model::find($id);
        $name = explode("App\\", $Model)[1];
        if ($m) {
            foreach ($m->getFiles() as $img) {
                if (Storage::has($img))
                    Storage::delete($img);
            }

            if ($force) {
                $m->forceDelete();
            } else {
                $m->delete();
            }
            Log::critical('the ' . $name . ' ' . $m->getLabel() . ' has been deleted by'
                . self::request_author());
            return Response::json($m, 200, [], JSON_NUMERIC_CHECK);
        } else {
            return Response::json(array("erreur" => "the " . $name . " you are looking for does not exist"), 422);
        }
    }

    public static function getFile($type, $model, $file)
    {
        $filename = $type . "/" . $model . "/" . $file;

        /*        $path = Storage::url($filename);;
                $path = storage_path('app/') . $filename;
                        $type ='image/png';
                        $type = File::mimeType($path);*/

        if (!Storage::has($filename)) abort(404);

        $file = Storage::get($filename);
        $ftype = Storage::mimeType($filename);
        $response = Response::make($file, 200);
        $response->header("Content-Type", $ftype);
        return $response;
    }

    public static function getAuthenticatedUser()
    {
        try {

            if (!$user = JWTAuth::parseToken()->authenticate()) {
                return response()->json(['the user does not exist'], 404);
            }
        } catch (TokenExpiredException $e) {

            return true;
        } catch (TokenInvalidException $e) {

            return false;
        } catch (JWTException $e) {

            return false;
        }

        return User::with($user->getForeign())->find($user->id);
    }

    private static function request_author()
    {
        if (Auth::check()) {
            $author = Auth::user();
            return 'by user of id: ' . $author->id . ' with email : ' . $author->email . ' name: ' . $author->full_name;
        } else {
            return 'guest user';
        }
    }

    public static function loge($msg, $type = 'info')
    {
        switch ($type) {
            case 'info': {
                    Log::info($msg . '. done by: ' . self::request_author());
                    break;
                }
            case 'critical': {
                    Log::critical($msg . '. done by: ' . self::request_author());
                    break;
                }
            default:
                Log::info($msg . '. done by: ' . self::request_author());
        }
    }
}
