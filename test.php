<?php

/*require 'yaml/Yaml.php';
require 'yaml/Parser.php';
require 'yaml/Inline.php';
require 'yaml/Dumper.php';
require 'yaml/Escaper.php';
require 'yaml/Exception/ExceptionInterface.php';
require 'yaml/Exception/RuntimeException.php';
require 'yaml/Exception/ParseException.php';*/
require 'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

class YamlComment
{
    const SQL_FILE = 'auth.sql';
    const MAPPINGS_PATH = '/usr/local/nginx16/html/TM_CMS/api/application/models/testmm';
    const GEN_YAML_PATH = 'MappingsWithComment';

    public static function appendYamlOptions($entitys, $comment='')
    {

        # 读取对应表的DDL
        $tables = ParseSql::readSql(self::SQL_FILE);

        $res = [];
        foreach ($entitys as $entity){

            $entityname = key($entity);
            $value = current($entity);
//            unset($entitys);

            # 驼峰转下划线
            $tablename = strtolower(preg_replace('/((?<=[a-z])(?=[A-Z]))/', '_', $entityname));
            if( !isset($tables[$tablename]) ){ continue; }

            # 给每个field加注释
            foreach ($value['fields'] as $field=>&$f){
                $field = isset($f['column']) ? $f['column'] : $field;
                if($tables[$tablename][$field]['default'] !== 'null'){
                    $f['options']['default'] = $tables[$tablename][$field]['default'];
                }
                if($tables[$tablename][$field]['comment'] !== 'null'){
                    $f['options']['comment'] = $tables[$tablename][$field]['comment'];
                }
            }
            $res[] = [$entityname=>$value];
        }
        return $res;
    }
    public static function add()
    {
        # 获取Mappings下yml文件
        $list = self::getFile(self::MAPPINGS_PATH);

        $entitys = [];
        foreach($list as $file){
            $entitys[] = Yaml::parse(file_get_contents($file));
        }

        $data = self::appendYamlOptions($entitys);

        # 生成带注释的yml文件
        if(self::genYamlWithComment($data)){
            echo '生成yml成功';
        }
    }

    public static function genYamlWithComment($data)
    {
        foreach($data as $item){
            $yaml = Yaml::dump($item, 5);
            $entityname = key($item);
            file_put_contents(self::GEN_YAML_PATH. '/' .$entityname . '.dcm.yml', $yaml);
        }
        return TRUE;
    }

    //获取文件列表
    public static function getFile($dir) {
        $fileArray[]=NULL;
        if (false != ($handle = opendir ( $dir ))) {
            $i=0;
            while ( false !== ($file = readdir ( $handle )) ) {
                //去掉"“.”、“..”以及带“.xxx”后缀的文件
                if ($file != "." && $file != ".."&&strpos($file,".")) {
                    $fileArray[$i]=$dir. '/' .$file;
                    if($i==100){
                        break;
                    }
                    $i++;
                }
            }
            //关闭句柄
            closedir ( $handle );
        }
        return $fileArray;
    }
}


class ParseSql
{
    public static function readSql($filename)
    {
        $tables = [];
        $fp_in = fopen($filename, "r");
        while (!feof($fp_in)) {
            $line = fgets($fp_in);
            $res = self::parseFieldnameAndComment($line);
            if( $res === 'nextLine' ){
                continue;
            }

            $tables[$res['tablename']] = $res['tableinfo'];
        }
        return $tables;
    }

    public static function parseFieldnameAndComment($txt)
    {
        $oneTable = []; //[表名=> [字段名=>注释] ]

        # 解析表名，当前表开始解析
        if(strpos($txt, 'CREATE TABLE')===0 or strpos($txt, 'create table')===0){
            preg_match('/`([^`]*)`\s\(/', $txt, $tablename);
            $_SESSION['aaoo']['tablename'] = $tablename[1];
            return 'nextLine';
        }

        # 当前表解析结束
        if(strpos($txt, 'ENGINE')!==FALSE){
            $oneTable = $_SESSION['aaoo'];
            $_SESSION['aaoo'] = [];
            return $oneTable;
        }

        # 解析字段名和注释
        preg_match('/^\s*`(.*)`/', $txt, $fieldname);
        if( empty($fieldname) ){
            return 'nextLine';
        }
        preg_match('/DEFAULT\s\'([^\']*)\'/', $txt, $default);
        preg_match('/COMMENT\s\'([^\']*)\'/', $txt, $comment);

        $_SESSION['aaoo']['tableinfo'][$fieldname[1]]['default'] = $default ? $default[1] : 'null';
        $_SESSION['aaoo']['tableinfo'][$fieldname[1]]['comment'] = $comment ? $comment[1] : 'null';
        return 'nextLine';
    }
}

YamlComment::add();
