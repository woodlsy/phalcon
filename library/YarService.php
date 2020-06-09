<?php
declare(strict_types = 1);

namespace woodlsy\phalcon\library;

class YarService
{
    private $application;

    function __construct($application)
    {
        $this->application = $application;
    }

    /**
     * yar client 入口
     * 控制器中获取参数方法：$params = $this->getDI()->get('params');
     *
     * @author yls
     * @param array $params
     * @return mixed
     */
    public function run($params = array())
    {
        $this->application->getDI()->setShared('params', function () use ($params) {
            return $params;
        });
        return $this->application->handle()->getContent();
    }
}