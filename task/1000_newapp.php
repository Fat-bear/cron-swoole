<?php
/**
 * Created by PhpStorm.
 * User: tantan
 * Date: 16/9/21
 * Time: 16:36
 */

class newapp extends \TaskBase
{
    private static $_key = [
        'diy',
        'data',
        'forum'
    ];
    public function run()
    {
        $process = new \swoole_process(function(){
            echo 'TANTAN',time(),PHP_EOL;
            sleep(5);
            echo 'TANTAN1',time(),PHP_EOL;
            exit(0);
        });
        $process->start();
    }
}

return new newapp();