<?php
/**
 * Created by PhpStorm.
 * User: Ets Simon
 * Date: 17/10/2016
 * Time: 14:51
 */

namespace App\Helpers;


use Faker\Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

class FactoryHelper
{

    public static function getOrCreate($M, $new = false,$data=[])
    {

        $ms = $M::get();
        $lenms = count($ms);
        if ($lenms == 0 || $new) {
            $m = factory($M)->create($data);
        } else {
            return $ms->random();

            $m = $ms[rand(0, $lenms - 1)];

        }
        return $m;
    }

    public static function syncMany($M, $M2,$relation)
    {

        $ms = $M::get();
        foreach ($ms as $m){
            $ms = $M2::pluck('id')->all();
            shuffle($ms);
            $m->$relation()->sync(array_slice($ms,count($ms)/3));
        }

    }


    public static function fakeFile(Generator $faker, $src)
    {
        /*$dst = "storage/app/img/" . $src;
        if (!is_dir($dst))
            mkdir($dst, 0777, true);*/

        $dst = 'img/'.strtolower($src). "/" ;
        $src=self::random_pic("public/seeds/" . $src);
        $res = explode('/',$src);
        $dst= $dst . uniqid() . '_' . $res[count($res)-1];
        Storage::put($dst,File::get($src));
//        $path = $faker->file("public/seeds/" . $src . "/", $dst);
        $path = $dst;
        return $path;
    }

    private static function random_pic($dir)
    {
        $files = glob($dir . '/*.*');
        $file = array_rand($files);
        return $files[$file];
    }

    public static function NewGuid($ext)
    {
        $s = strtoupper(md5(uniqid(rand(), true)));
        $guidText =
            substr($s, 0, 8) . '-' .
            substr($s, 8, 4) . '-' .
            substr($s, 12, 4) . '-' .
            substr($s, 16, 4) . '-' .
            substr($s, 20);
        return $guidText . '.' . $ext;
    }

    public static function getOrCreateMorph($array)
    {
        return self::getOrCreate($array[rand(0,count($array)-1)]);
    }

    public static function clear($disk="app")
    {
        if (env("APP_ENV") == "production")
            return;

        $directory = Config::get("filesystems");
//        $directory = $directory["disks"][env("DEFAULT_STORAGE")]["root"];
        $directory = "storage/$disk/img";
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $tableNames = Schema::getConnection()->getDoctrineSchemaManager()->listTableNames();
        foreach ($tableNames as $name) {
            //if you don't want to truncate migrations
            if ($name == 'migrations') {
                continue;
            }
//            DB::table($name)->delete();
            DB::table($name)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        self::recursiveRemoveDirectory($directory);


    }

    private static function recursiveRemoveDirectory($directory)
    {
        foreach (glob("{$directory}/*") as $file) {
            if (is_dir($file)) {
                self::recursiveRemoveDirectory($file);
            } else {
                unlink($file);
            }
        }
//        rmdir($directory);
    }

    public static function recurse_copy($src,$dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    self::recurse_copy($src . '/' . $file,$dst . '/' . $file);
                }
                else {
//                    copy($src . '/' . $file,$dst . '/' . $file);
                    Storage::put($dst . '/' . $file,File::get($src . '/' . $file));
                }
            }
        }
        closedir($dir);
    }

    public static function force_seed($M,$len,$data=[])
    {
        //
        $attemp=0;

        for($i=0;$i<$len;$i++) {
            $attemp=0;
            repeat:
            $attemp++;
            try {
                factory($M)->create($data);
            } catch (\Illuminate\Database\QueryException $e) {
                //look for integrity violation exception (23000)
                if($e->errorInfo[0]==23000){
                    //echo $attemp."\t".$i."\r\n";
                    if($attemp<=$len*2)
                        goto repeat;
                }

            }
        }

    }

}



/*foreach (range(1, 20) as $index) {
    repeat:
    $tableAId = $faker->randomElement($tableAIds);
    $tableBId = $faker->randomElement($tableBIds);
    try {
        DB::table("table_a_table_b_pivot")->insert([
            "table_a_id" => $tableAId,
            "table_b_id" => $tableBId,
        ]);
    } catch (\Illuminate\Database\QueryException $e) {
        //look for integrity violation exception (23000)
        if($e->errorInfo[0]==23000)
            goto repeat;
    }
}*/