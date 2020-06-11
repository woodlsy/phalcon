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
        try{
            $this->application->getDI()->setShared('params', function () use ($params) {
                return $params;
            });
            return $this->application->handle()->getContent();
        } catch (\Exception $e) {
            if (get_class($e) === $this->application->getDI()->get('config')->exception) {
                header('Content-type: application/json');
                echo Helper::jsonEncode(['code' => $e->getCode(), 'msg' => $e->getMessage()]);
            } else {
                echo '系统错误，请联系管理员';
                Log::write('system', $e->getMessage());
            }
        }
    }
}