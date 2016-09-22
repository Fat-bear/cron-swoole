<?php
/**
 * Created by PhpStorm.
 * User: tantan
 * Date: 16/9/19
 * Time: 19:23
 */
abstract class TaskBase
{
    private static $_SplDir = [
        'library'
    ];
    public function __construct()
    {
        $this->_initSig();
        $this->_initAuth();
        $this->_initRedis();
    }
    public function init(){

    }
    private function _initAuth()
    {
        spl_autoload_register(__NAMESPACE__.'\TaskBase::auth');
    }
    private function _initSig()
    {
        \swoole_process::signal(SIGCHLD,function(){
            while(\swoole_process::wait(false))
            {
            }
        });
    }
    private function _initRedis()
    {
        
    }
    private function _initDb()
    {

    }
    public static function auth($class)
    {
        foreach(self::$_SplDir as $v) {
            $file = realpath(__DIR__.'/../../') . '/' . $v . '/' . str_replace('\\','/',$class) . '.php';
            if(file_exists($file)) {
                require_once $file;
            }
        }
    }
    //启动方法
    abstract public function run();
}