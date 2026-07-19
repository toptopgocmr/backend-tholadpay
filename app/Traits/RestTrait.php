<?php
/**
 * Created by PhpStorm.
 * User: Ets Simon
 * Date: 12/06/2017
 * Time: 11:57
 */

namespace App\Traits;

trait RestTrait{

    protected $foreign = [];
    protected $files = [];

    protected  $append_relateds=[];



    public function getForeign()
    {
        return $this->foreign;
    }


    public function getFiles()
    {
        return $this->files;
    }

    public function getLabel()
    {
        return $this->id ;
    }
    public function getAppends()
    {
        return $this->appends ;
    }
    public function setAppends(Array $apps)
    {

        $this->appends=$apps;
    }


    /**
     * @return mixed
     */
    public function getAppendRelateds()
    {
        return $this->append_relateds;
    }

    /**
     * @param mixed $append_relateds
     */
    public function setAppendRelateds($append_relateds): void
    {
        $this->append_relateds = $append_relateds;
    }

    public function toArray(){
        $array = parent::toArray();
        foreach ($this->append_relateds as $relation){
            $array[$relation]=$this->$relation();
        }
        return $array;
    }

}