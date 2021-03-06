<?php

use R3m\Io\Module\Core;
use R3m\Io\Module\Data;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;
use R3m\Io\Module\Parse;

use Exception;
use R3m\Io\Exception\FileMoveException;
use R3m\Io\Exception\FileWriteException;
use R3m\Io\Exception\ObjectException;

function function_public_create(Parse $parse, Data $data, $public_html=''){
    $object = $parse->object();
    if(empty($public_html)){
        $public_html = $object->config('project.dir.public');
    }
    $url = $object->config('project.dir.data') . 'Config.json';
    $read = $object->data_read($url);
    if(empty($read)){
        $read = new Data();
    }
    $source = $read->data('server.public');
    if(strstr($public_html, '/') === false){
        $public_html = $object->config('project.dir.root') . $public_html . $object->config('ds');
    }
    if(!empty($source)){
        try {
            $destination = $public_html;
            if(
                $source != $destination &&
                File::exist($source)
            )
            {
                File::move($source, $destination);
            }
            if(!File::exist($destination)){
                Dir::create($destination);
            }
            $destination = $public_html . '.htaccess';
            if(!File::exist($destination)){
                $source = $object->config('controller.dir.data') . '.htaccess';
                File::copy($source, $destination);
            }
            $destination = $public_html . 'index.php';
            if(!File::exist($destination)){
                $source = $object->config('controller.dir.data') . 'index.php';
                File::copy($source, $destination);
            }
        } catch (Exception | FileMoveException $exception){
            return $exception->getMessage() . PHP_EOL;
        }
    } else {
        Dir::create($public_html);
        $source = $object->config('controller.dir.data') . '.htaccess';
        $destination = $public_html . '.htaccess';
        File::copy($source, $destination);
        $source = $object->config('controller.dir.data') . 'index.php';
        $destination = $public_html . 'index.php';
        File::copy($source, $destination);
    }
    $read->data('server.public', $public_html);
    $write = '';
    try {
        $write = File::write($url, Core::object($read->data(), Core::OBJECT_JSON));
    } catch (Exception | FileWriteException | ObjectException $exception){
        return $exception->getMessage() . PHP_EOL;
    }
    return 'Bytes written: ' . $write . PHP_EOL;
}

