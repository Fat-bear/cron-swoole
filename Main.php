<?php
/**
 * Created by PhpStorm.
 * User: tantan
 * Date: 16/9/18
 * Time: 17:20
 */
namespace cli;
class Main{
    private static $_count   = 0;
    private static $_works   = [];
    private static $_baseDir = '';
    private static $_pidFile = '';
    private static $_runFile = '';
    private static $_runLog  = '';
    private static $_daemon  = '';

    /**
     * Main constructor.
     * @param int $count
     */
    public function __construct()
    {
        self::$_baseDir = __DIR__ . '/';
        require_once self::$_baseDir . 'task/TaskBase.php';
    }

    /**
     * @param array $argv 执行cli命令
     */
    public function command($argv)
    {
        self::$_runFile = $argv[0];
        self::$_pidFile = self::$_baseDir.md5($argv[0]).'.pid';
        self::$_runLog  = self::$_baseDir.md5($argv[0]).'.log';
        self::$_daemon  = (isset($argv[2]) && $argv[2] == '-d');
        php_sapi_name() !== 'cli' && exit('must run in cli...' . PHP_EOL);
        if(isset($argv[1]) && method_exists($this,'_'.$argv[1]))
        {
            call_user_func(__NAMESPACE__.'\Main::'.'_'.$argv[1]);
        } else {
            exit('Usage: php yourFile.php {start|stop|reload} [-d]' . PHP_EOL);
        }
    }

    /**
     * 处理信号量
     * @param int $signum 信号量
     */
    public static function _sigHandler($signum)
    {
        switch($signum)
        {
            case SIGINT:
                foreach(self::$_works as $k => $v)
                {
                    \swoole_process::kill($k);
                }
                self::log('monster [pid:'.getmypid().'] stop success ...');
                @unlink(self::$_pidFile);
                exit(0);
                break;
            case SIGCHLD:
                while($ret =  \swoole_process::wait(false))
                {
                    if(isset(self::$_works[$ret['pid']]))
                    {
                        $data = self::$_works[$ret['pid']];
                        unset(self::$_works[$ret['pid']]);
                        self::_forkOneProcess($data['file'],$data['time']);
                    }
                }
                break;
            case SIGUSR1:
                foreach(self::$_works as $k => $v)
                {
                    \swoole_process::kill($k);
                }
                self::log('monster [pid:'.getmypid().'] reload success ...');
                break;
        }
    }

    /**
     * 启动计划任务
     * @throws \Exception
     */
    private static function _start()
    {
        self::_initDaem();
        self::_initWorks();
        self::_initSignal();
        self::_initMasterPid();
        self::_monitor();
    }

    /**
     * 停止计划任务
     */
    private static function _stop()
    {
        $pid = file_get_contents(self::$_pidFile);
        $masterIsAlive = trim(`ps -e|awk '{print $1}'|grep $pid`);
        if($pid && $masterIsAlive == $pid)
        {
            self::log("monster [pid:$pid] is stoping ...");
            \swoole_process::kill($pid,SIGINT);
        } else {
            self::log("monster [pid:$pid] don't alive...");
            self::_clearChildren();
        }
    }

    /**
     * 热重启计划任务
     * @throws \Exception
     */
    private static function _reload()
    {
        $pid = file_get_contents(self::$_pidFile);
        $masterIsAlive = trim(`ps -e|awk '{print $1}'|grep $pid`);
        if($pid && $masterIsAlive == $pid)
        {
            self::log("monster [pid:$pid] is reloading ...");
            \swoole_process::kill($pid,SIGUSR1);
        } else {
            self::log("monster [pid:$pid] don't alive...");
            self::_clearChildren();
            self::_start();
        }
    }

    /**
     * 清理子进程
     */
    private static function _clearChildren()
    {
        $file = self::$_runFile;
        exec("ps -e|grep $file|grep -v grep|xargs kill -9");
        @unlink(self::$_pidFile);
    }

    /**
     * 监控进程
     */
    private static function _monitor()
    {
        \swoole_timer_tick(5000,function(){
            $files = glob(self::$_baseDir . 'task/*.php');
            if(count($files) != self::$_count)
            {
                foreach(self::$_works as $k => $v)
                {
                    $key = array_search($v['file'],$files);
                    if($key !== false){
                        unset($files[$key]);
                    } else {
                        self::$_count--;
                        unset(self::$_works[$k]);
                        \swoole_process::kill($k);
                    }
                }
                if(!empty($files))
                {
                    foreach($files as $v){
                        $filename = basename($v);
                        list($secends,) = explode('_',$filename);
                        if(intval($secends))
                        {
                            self::$_count++;
                            self::_forkOneProcess($v,intval($secends));
                        }else{
                            continue;
                        }
                    }
                }
            }
        });
    }

    /**
     * 创建一个子进程
     * @param string $file 计划任务
     */
    private static function _forkOneProcess($file,$time)
    {
        $childProcess = new \swoole_process(function (\swoole_process $worker) use ($file,$time) {
            $app = require_once $file;
            \swoole_timer_tick($time,function() use ($app){
                try{
                    $app->run();
                }catch(\Exception $e)
                {
                    self::log($e->getMessage());
                }
            });
            $worker->signal(SIGTERM,function(){
                exit(0);
            });
        });
        $pid = $childProcess->start();
        self::$_works[$pid]['file']  = $file;
        self::$_works[$pid]['time'] = $time;
    }

    /**
     * @param string $msg 日志信息
     */
    private static function log($msg)
    {
        if(!self::$_daemon){
            echo $msg . PHP_EOL;
        }
        file_put_contents(self::$_runLog,date('Y-m-d H:i:s').' [pid:'.getmypid().']'.$msg.PHP_EOL,FILE_APPEND | LOCK_EX);
    }

    /**
     * 是否进程守护化
     */
    private static function _initDaem()
    {
        self::$_daemon && \swoole_process::daemon();
    }

    /**
     * 初始化信号处理函数
     */
    private static function _initSignal()
    {
        \swoole_process::signal(SIGINT,__NAMESPACE__.'\Main::_sigHandler');
        \swoole_process::signal(SIGCHLD,__NAMESPACE__.'\Main::_sigHandler');
        \swoole_process::signal(SIGUSR1,__NAMESPACE__.'\Main::_sigHandler');
    }

    /**
     * 保存主进程id
     * @throws \Exception
     */
    private static function _initMasterPid()
    {
        if(file_put_contents(self::$_pidFile,getmypid()) === false)
        {
            throw new \Exception('can not save pid to '.self::$_pidFile);
        }
    }

    /**
     * 初始化子进程
     */
    private static function _initWorks()
    {
        $files = glob(self::$_baseDir . 'task/*.php');
        self::$_count = count($files);
        if(empty($files)) {
            exit('there is not have task...' . PHP_EOL);
        } else {
            foreach($files as $v){
                $filename = basename($v);
                list($secends,) = explode('_',$filename);
                if(intval($secends))
                {
                    self::_forkOneProcess($v,intval($secends));
                }else{
                    continue;
                }
            }
        }
    }
}
$Main = new Main();
$Main->command($argv);


