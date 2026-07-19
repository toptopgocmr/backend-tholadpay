<?php
/**
 * Created by PhpStorm.
 * User: Ets Simon
 * Date: 06/12/2016
 * Time: 16:03
 */

namespace App\Helpers;


class RuleHelper
{
    public static function get_rules($method,$rules,$ignoreid=[],$put_required=[]){

        switch ($method) {
            case 'GET': {
                return $rules;
            }
            case 'DELETE': {
                return [];
            }
            case 'POST': {

                return $rules;
            }
            case 'PUT': {

                $prules=[];
                foreach ($rules as $key=>$rule) {
                    /*$tmp= str_replace('required|','',$rule);
                    $tmp= str_replace('|required','',$tmp);
                    $tmp= str_replace('required','',$tmp);*/
                    $tmp=$rule;
                    if(!in_array($key,$put_required)){
                        $tmp = preg_replace('/\brequired\|\b/', '', $rule);
                        $tmp = preg_replace('/\b\|required\b/', '', $tmp);
                        $tmp = preg_replace('/\brequired\b/', '', $tmp);
                    }

                    if (strpos($tmp, 'unique') !== false&&count($ignoreid)>0) {
                        //commence par | (\ pour echaper) ensuite nimporte koi apres la suite unique ensuite nimporte koi fini
                        //par |
                        /*$tmp = preg_replace('/\|(.*)unique(.*)\|/','|unique:'.$ignoreid[$key].'|',$tmp);
                        $tmp = preg_replace('/(.*[^\|])unique(.*)\|/','unique:'.$ignoreid[$key].'|',$tmp);
                        $tmp = preg_replace('/\|(.*)unique(.*[^\|])/','|unique:'.$ignoreid[$key],$tmp);*/
                        if(preg_match('/\|(.*)unique(.*[^\|])/', $tmp)){
                            $tab = explode('|',$tmp);
                            $tab[count($tab)-1]='|unique:'.$ignoreid[$key];
                            $tmp = implode('|',$tab);
                        }elseif(preg_match('/(.*[^\|])unique(.*)\|/', $tmp)){
                            $tab = explode('|',$tmp);
                            $tab[0]='unique:'.$ignoreid[$key].'|';
                            $tmp = implode('|',$tab);
                        }else{
                            $tmp = preg_replace('/\|(.*)unique(.*)\|/','|unique:'.$ignoreid[$key].'|',$tmp);
                        }

                    }
                    $prules[$key]= $tmp;
                }

                return $prules;
            }
            case 'PATCH': {
                return [
                ];
            }
            default:
                return [

                ];
        }

    }

}