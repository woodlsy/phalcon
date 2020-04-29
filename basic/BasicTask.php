<?php

namespace woodlsy\phalcon\basic;

use Phalcon\CLI\Task;
use woodlsy\phalcon\library\Helper;
use woodlsy\phalcon\library\Redis;

class BasicTask extends Task
{

    private $_lockKey = '';

    /**
     * 脚本执行前执行
     *
     * @author yls
     */
    public function beforeExecuteRoute()
    {
        $taskName       = $this->di->get('router')->getTaskName();
        $actionName     = $this->di->get('router')->getActionName() ? $this->di->get('router')->getActionName() : 'main';
        $this->_lockKey = APP_NAME.'_cli_' . $taskName . '_' . $actionName;

        echo Helper::now() . "\t{$taskName} {$actionName} 被执行\n";
    }

    /**
     * task防止重复执行锁
     *
     * @author yls
     * @param int $time
     */
    protected function _taskLock($time = 1800)
    {
        if (Redis::getInstance()->exists($this->_lockKey)) {
            echo Helper::now() . "\t程序已被锁住" . PHP_EOL;
            exit;
        }
        //redis锁
        Redis::getInstance()->setex($this->_lockKey, $time, 'lock');
    }


    /**
     * 脚本执行后执行
     *
     * @author yls
     */
    public function afterExecuteRoute()
    {
        //删除锁
        Redis::getInstance()->del($this->_lockKey);
    }

}