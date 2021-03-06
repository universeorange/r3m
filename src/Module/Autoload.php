<?php
/**
 * @author          Remco van der Velde
 * @since           04-01-2019
 * @copyright       (c) Remco van der Velde
 * @license         MIT
 * @version         1.0
 * @changeLog
 *  -    all
 */
namespace R3m\Io\Module;

use stdClass;
use Exception;
use R3m\Io\App;
use R3m\Io\Config;

class Autoload {
    const DIR = __DIR__;
    const FILE = 'Autoload.json';
    const TEMP = 'Temp';
    const NAME = 'Autoload';
    const EXT_PHP = 'php';
    const EXT_TPL = 'tpl';
    const EXT_JSON = 'json';
    const EXT_CLASS_PHP = 'class.php';
    const EXT_TRAIT_PHP = 'trait.php';

    protected $expose;
    protected $read;
    protected $fileList;
    protected $cache_dir;

    public $prefixList = array();
    public $environment = 'production';

    public static function configure(App $object){
        $config = $object->data(App::CONFIG);
        $autoload = new Autoload();
        $autoload->addPrefix('Host',  $config->data(Config::DATA_PROJECT_DIR_HOST));        
        $autoload->addPrefix('Source',  $config->data(Config::DATA_PROJECT_DIR_SOURCE));
        $cache_dir =
            $config->data(Config::DATA_FRAMEWORK_DIR_CACHE) .
            Autoload::NAME .
            $config->data(Config::DS)
        ;
        $autoload->cache_dir($cache_dir);
        $autoload->register();
        $autoload->environment($config->data('framework.environment'));
        $object->data(App::AUTOLOAD_R3M, $autoload);        
    }

    public function register($method='load', $prepend=false){
        $functions = spl_autoload_functions();
        if(is_array($functions)){
            foreach($functions as $function){
                $object = reset($function);
                if(is_object($object) && get_class($object) == get_class($this)){
                    return true; //register once...
                }
            }
        }
        return spl_autoload_register(array($this, $method), true, $prepend);
    }

    public function unregister($method='load'){
        return spl_autoload_unregister(array($this, $method));
    }

    public function priority(){
        $functions = spl_autoload_functions();
        foreach($functions as $nr => $function){
            $object = reset($function);
            if(is_object($object) && get_class($object) == get_class($this) && $nr > 0){
                spl_autoload_unregister($function);
                spl_autoload_register($function, null, true); //prepend (prioritize)
            }
        }
    }

    private function setEnvironment($environment='production'){
        $this->environment = $environment;
    }

    private function getEnvironment(){
        return $this->environment;
    }

    public function environment($environment=null){
        if($environment !== null){
            $this->setEnvironment($environment);
        }
        return $this->getEnvironment();
    }

    public function addPrefix($prefix='', $directory='', $extension=''){
        $prefix = trim($prefix, '\\\/'); //.'\\';
        $directory = str_replace('\\\/', DIRECTORY_SEPARATOR, rtrim($directory,'\\\/')) . DIRECTORY_SEPARATOR; //see File::dir()
        $list = $this->getPrefixList();
        if(empty($list)){
            $list = [];
        }
        if(empty($extension)){
            $found = false;
            foreach($list as $record){
                if(
                    $record['prefix'] == $prefix &&
                    $record['directory'] == $directory
                ){
                    $found = true;
                    break;
                }
            }
            if(!$found){
                $list[]  = array(
                    'prefix' => $prefix,
                    'directory' => $directory
                );
            }
        } else {
            $found = false;
            foreach($list as $record){
                if(
                    $record['prefix'] == $prefix &&
                    $record['directory'] == $directory &&
                    !empty($record['extension']) &&
                    $record['extension'] == $extension
                ){
                    $found = true;
                    break;
                }
            }
            if(!$found){
                $list[]  = array(
                    'prefix' => $prefix,
                    'directory' => $directory,
                    'extension' => $extension
                );
            }
        }
        $this->setPrefixList($list);
    }

    private function setPrefixList($list = array()){
        $this->prefixList = $list;
    }

    private function getPrefixList(){
        return $this->prefixList;
    }

    /**
     * @throws Exception
     */
    public function load($load): bool
    {
        $file = $this->locate($load);
        if (!empty($file)) {
            require_once $file;
            return true;
        }
        return false;
    }

    public function fileList($item=array(), $url=''){
        if(empty($item)){
            return array();
        }
        if(empty($this->read)){
            $this->read = $this->read($url);
        }
        $data = array();
        $caller = get_called_class();
        if(
            isset($this->read->autoload) &&
            isset($this->read->autoload->{$caller}) &&
            isset($this->read->autoload->{$caller}->{$item['load']})
            ){
                $data[] = $this->read->autoload->{$caller}->{$item['load']};
            }
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . 'Class' . DIRECTORY_SEPARATOR . $item['file'] . '.' . Autoload::EXT_CLASS_PHP;
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . 'Class' . DIRECTORY_SEPARATOR . $item['file'] . '.' . Autoload::EXT_PHP;
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . 'Trait' . DIRECTORY_SEPARATOR . $item['file'] . '.' . Autoload::EXT_TRAIT_PHP;
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . 'Trait' . DIRECTORY_SEPARATOR . $item['file'] . '.' . Autoload::EXT_PHP;
            $data[] = '[---]';
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . 'Class' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_CLASS_PHP;
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . 'Class' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_PHP;
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . 'Trait' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_TRAIT_PHP;
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . 'Trait' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_PHP;
            $data[] = '[---]';
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . $item['file'] . '.' . Autoload::EXT_CLASS_PHP;
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . $item['file'] . '.' . Autoload::EXT_TRAIT_PHP;
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . $item['file'] . '.' . Autoload::EXT_PHP;
            $data[] = '[---]';
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_CLASS_PHP;
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_TRAIT_PHP;
            $data[] = $item['directory'] . $item['file'] . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_PHP;
            $data[] = '[---]';
            if(empty($item['dirName'])){
                $data[] = $item['directory'] . 'Class' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_CLASS_PHP;
                $data[] = $item['directory'] . 'Trait' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_TRAIT_PHP;
                $data[] = $item['directory'] . 'Class' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_PHP;
                $data[] = $item['directory'] . 'Trait' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_PHP;
                $data[] =  '[---]';
            } else {
                $data[] = $item['directory'] . $item['dirName'] . DIRECTORY_SEPARATOR . 'Class' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_CLASS_PHP;
                $data[] = $item['directory'] . $item['dirName'] . DIRECTORY_SEPARATOR . 'Trait' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_TRAIT_PHP;
                $data[] = $item['directory'] . $item['dirName'] . DIRECTORY_SEPARATOR . 'Class' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_PHP;
                $data[] = $item['directory'] . $item['dirName'] . DIRECTORY_SEPARATOR . 'Trait' . DIRECTORY_SEPARATOR . $item['baseName'] . '.' . Autoload::EXT_PHP;
                $data[] =  '[---]';
            }
            $data[] = $item['directory'] . $item['file'] . '.' . Autoload::EXT_CLASS_PHP;
            $data[] = $item['directory'] . $item['file'] . '.' . Autoload::EXT_TRAIT_PHP;
            $data[] = $item['directory'] . $item['file'] . '.' . Autoload::EXT_PHP;
            $data[] = '[---]';
            $data[] = $item['directory'] . $item['baseName'] . '.' . Autoload::EXT_CLASS_PHP;
            $data[] = $item['directory'] . $item['baseName'] . '.' . Autoload::EXT_TRAIT_PHP;
            $data[] = $item['directory'] . $item['baseName'] . '.' . Autoload::EXT_PHP;
            $data[] = '[---]';
            $this->fileList[$item['baseName']][] = $data;
            $result = array();
            foreach($data as $nr => $file){
                if($file === '[---]'){
                    $file = $file . $nr;
                }
                $result[$file] = $file;
            }
            return $result;
    }

    /**
     * @throws Exception
     */
    public function locate($load=null, $is_data=false){
        $dir = $this->cache_dir();
        $url = $dir . Autoload::FILE;
        $load = ltrim($load, '\\');
        $prefixList = $this->getPrefixList();
        $fileList = [];
        if(!empty($prefixList)){
            foreach($prefixList as $item){
                if(empty($item['prefix'])){
                    continue;
                }
                if(empty($item['directory'])){
                    continue;
                }
                $item['file'] = false;
                if (strpos($load, $item['prefix']) === 0) {
                    $item['file'] =
                    trim(substr($load, strlen($item['prefix'])),'\\');
                    $item['file'] =
                    str_replace('\\', DIRECTORY_SEPARATOR, $item['file']);
                } elseif($is_data === false) {
                    $tmp = explode('.', $load);
                    if(count($tmp) >= 2){
                        array_pop($tmp);
                    }
                    $item['file'] = implode('.',$tmp);
                } else {
                    continue;
                }
                if(empty($item['file'])){
                    $item['file'] = $load;
                }
                if(!empty($item['file'])){
                    $item['load'] = $load;
                    $item['file'] = str_replace('\\', DIRECTORY_SEPARATOR, $item['file']);
                    $item['file'] = str_replace('.'  . DIRECTORY_SEPARATOR , DIRECTORY_SEPARATOR, $item['file']);
                    $item['baseName'] = basename(
                        $this->removeExtension($item['file'],
                            array(
                                Autoload::EXT_PHP,
                                Autoload::EXT_TPL
                            )
                    ));
                    $item['baseName'] = explode(DIRECTORY_SEPARATOR, $item['baseName'], 2);
                    $item['baseName'] = end($item['baseName']);
                    $item['dirName'] = dirname($item['file']);
                    if($item['dirName'] == '.'){
                        unset($item['dirName']);
                    }
                    $fileList = $this->fileList($item, $url);
                    if(is_array($fileList) && empty($this->expose())){
                        foreach($fileList as $file){
                            if(substr($file, 0, 5) == '[---]'){
                                continue;
                            }
                            if(file_exists($file)){
                                $this->cache($file, $load);
                                return $file;
                            }
                        }
                    }
                }
            }
        }
        if($is_data === true){
            if($this->environment() == 'development'){
                d($fileList);
            }
            throw new Exception('Could not find data file');
        }
        //$this->environment('development'); //needed, should be gone @ home
        if($this->environment() == 'development' || !empty($this->expose())){
            if(empty($this->expose())){
                throw new Exception('Autoload error, cannot load (' . $load .') class.');
            }
            $object = new stdClass();
            $object->load = $load;
            $debug = debug_backtrace(true);
            $output = [];
            for($i=0; $i < 5; $i++){
                if(!isset($debug[$i])){
                    continue;
                }
                $output[$i] = $debug[$i];
            }
            $attribute = 'R3m\Io\Exception\LocateException';
            if(!empty($this->expose())){
                $attribute = $load;
            }
            if(
                isset($item) &&
                isset($item['baseName']) &&
                isset($this->fileList[$item['baseName']])
            ){
                $object->{$attribute} = $this->fileList[$item['baseName']];
            }
            $object->debug = $output;
            if(ob_get_level() !== 0){
                ob_flush();
            }
            if(empty($this->expose())){
                echo '<pre>';
                echo json_encode($object, JSON_PRETTY_PRINT);
                echo '</pre>';
                die;

            } else {
                echo json_encode($object, JSON_PRETTY_PRINT);
            }
        }
        return false;
    }

    public function __destruct(){
        if(!empty($this->read)){
            $dir = $this->cache_dir();
            $url = $dir . Autoload::FILE;
            $this->write($url, $this->read);
        }
    }

    public function cache_dir($directory=null){
        if($directory !== null){
            $this->cache_dir = $directory;
        }
        return $this->cache_dir;
    }

    private function cache($file='', $class=''){
        if(empty($this->read)){
            $dir = $this->cache_dir();
            $url = $dir . Autoload::FILE;
            $this->read = $this->read($url);
        }
        if(empty($this->read->autoload)){
            $this->read->autoload = new stdClass();
        }
        $caller = get_called_class();
        if(empty($this->read->autoload->{$caller})){
            $this->read->autoload->{$caller}= new stdClass();
        }
        $this->read->autoload->{$caller}->{$class} = (string) $file;
    }

    protected function write($url='', $data=''){
        if(posix_geteuid() === 0){
            return false;
        }
        $data = (string) json_encode($data, JSON_PRETTY_PRINT);
        if(empty($data)){
            return false;
        }
        $fwrite = 0;
        $dir = dirname($url);
        if(is_dir($dir) === false){
            try {
                @mkdir($dir, 0777, true);
            } catch(Exception $exception){
                return false;
            }

        }
        if(is_dir($dir) === false){
            return false;
        }
        $resource = fopen($url, 'w');
        if($resource === false){
            return $resource;
        }
        flock($resource, LOCK_EX);
        fseek($resource, 0);
        for ($written = 0; $written < strlen($data); $written += $fwrite) {
            $fwrite = fwrite($resource, substr($data, $written));
            if ($fwrite === false) {
                break;
            }
        }
        if(!empty($resource)){
            flock($resource, LOCK_UN);
        }
        fclose($resource);
        if($written != strlen($data)){
            return false;
        } else {
            return $fwrite;
        }
    }

    private function read($url=''){
        if(file_exists($url) === false){
            $this->read = new stdClass();
            return $this->read;
        }
        $this->read =  json_decode(implode('', file($url)));
        if(empty($this->read)){
            $this->read = new stdClass();
        }
        return $this->read;
    }

    private function removeExtension($filename='', $extension=array()){
        foreach($extension as $ext){
            $ext = '.' . ltrim($ext, '.');
            $filename = explode($ext, $filename, 2);
            if(count($filename) > 1 && empty(end($filename))){
                array_pop($filename);
            }
            $filename = implode($ext, $filename);
        }
        return $filename;
    }

    public function expose($expose=null){
        if(!empty($expose) || $expose === false){
            $this->expose = (bool) $expose;
        }
        return $this->expose;
    }
}