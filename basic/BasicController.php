<?php

namespace woodlsy\phalcon\basic;

use Exception;
use Phalcon\Di;
use Phalcon\Filter;
use Phalcon\Mvc\Controller;
use Phalcon\Http\ResponseInterface;
use woodlsy\phalcon\library\Redis;

/**
 * Class BasicController
 *
 * @author yls
 * @package Basic
 */
class BasicController extends Controller
{
    /**
     * @var array 不检测CSRF和登录的路由规则
     */
    protected $allowAllRouter = [
    ];

    /**
     * @var array 不检测CSRF路由规则
     */
    protected $allowCSRFRouter = [

    ];

    protected $moduleName     = null;
    protected $controllerName = null;
    protected $actionName     = null;
    protected $config;

    /**
     * 加载控制器前执行
     *
     * @author yls
     * @throws Exception
     */
    public function beforeExecuteRoute()
    {
        $this->moduleName     = $this->router->getModuleName();
        $this->controllerName = $this->router->getControllerName();
        $this->actionName     = $this->router->getActionName();

        $this->config = Di::getDefault()->getConfig();

        if ($this->request->isPost() && true === (bool) $this->config->limit_request) {
            session_start();
            $key = session_id() . '_controller_lock_' . $this->moduleName . '_' . $this->controllerName . '_' . $this->actionName;
            if (Redis::getInstance()->exists($key)) {
                throw new Exception('请勿频繁请求');
            } else {
                Redis::getInstance()->setex($key, 5, 1);
            }
        }
    }

    /**
     * 执行完控制器后执行
     *
     * @author yls
     */
    public function afterExecuteRoute()
    {
        if ($this->request->isPost()) {
            $key = session_id() . '_controller_lock_' . $this->moduleName . '_' . $this->controllerName . '_' . $this->actionName;
            Redis::getInstance()->del($key);
        }
    }

    /**
     * 初始化
     *
     * @author yls
     * @throws Exception
     */
    public function initialize()
    {
        $this->checkCSRF();
    }

    /**
     * 检测CSRF
     *
     * @author yls
     * @throws Exception
     */
    private function checkCSRF() : void
    {
        if (true !== (bool) $this->config->csrf) {
            return;
        }
        $this->allowCSRFRouter = array_merge($this->allowCSRFRouter, $this->allowAllRouter);
        if (!empty($this->allowCSRFRouter)) {
            foreach ($this->allowCSRFRouter as $moduleName => $controller) {
                if ('*' === $controller && $moduleName === $this->moduleName) {
                    return;
                }
                if (is_array($controller) && !empty($controller)) {
                    foreach ($controller as $controllerName => $actionName) {
                        if ('*' === $actionName && $controllerName === $this->controllerName) {
                            return;
                        }
                        if (is_array($actionName) && in_array($this->actionName, $actionName)) {
                            return;
                        }
                    }
                }
            }
        }
        if ($this->request->isPost()) {
            $tokenKey   = $this->request->getHeader($this->config->csrf_key_name);
            $tokenValue = $this->request->getHeader($this->config->csrf_key_value);
            if (!$this->security->checkToken($tokenKey, $tokenValue)) {
                throw new Exception('非法请求', 403);
            }
        }
    }

    /**
     * Ajax方式返回数据到客户端
     *
     * @author yls
     * @param int        $code 状态码
     * @param string     $msg 提示语
     * @param null       $data 要返回的数据
     * @param array|null $other 额外数据，和code同一级
     * @param string     $type AJAX返回数据格式
     * @return ResponseInterface
     */
    final protected function ajaxReturn(int $code, string $msg, $data = null, array $other = null, $type = 'json') : ResponseInterface
    {
        $returnMsg = ['code' => $code, 'msg' => $msg];
        if (null !== $data) $returnMsg['data'] = $data;

        if ($other && is_array($other)) {
            foreach ($other as $key => $val) {
                $returnMsg[$key] = $val;
            }
        }
        switch (strtoupper($type)) {
            case 'JSON' :
                return $this->response->setJsonContent($returnMsg);
            default:
                return $this->response->setJsonContent($returnMsg);
        }
    }

    /**
     * 获取get参数
     *
     * @author yls
     * @param string|null     $name get key
     * @param string|null     $filters 过滤方法
     * @param string|int|null $defaultValue 默认值
     * @return mixed
     */
    final protected function get(string $name = null, string $filters = null, $defaultValue = null)
    {
        $filters = $filters ? : null;
        return Di::getDefault()->get('request')->getQuery($name, $filters, $defaultValue);
    }

    /**
     * 获取POST参数
     *
     * @author yls
     * @param string|null     $name
     * @param string|null     $filters
     * @param string|int|null $defaultValue
     * @return mixed
     */
    final protected function post(string $name = null, string $filters = null, $defaultValue = null)
    {
        $filters = $filters ? : null;
        return Di::getDefault()->get('request')->getPost($name, $filters, $defaultValue);
    }

    /**
     * 获取post参数 php://input 方式
     *
     * @author yls
     * @param string|null     $name post key
     * @param string|null     $filters 过滤方法
     * @param string|int|null $defaultValue 默认值
     * @return mixed|null
     */
    final protected function json(string $name = null, string $filters = null, $defaultValue = null)
    {
        $postData = $this->request->getJsonRawBody(true);
        if (null === $name) {
            return $postData;
        }
        if (!isset($postData[$name])) {
            return null !== $defaultValue ? $defaultValue : null;
        }
        $param = $postData[$name];
        if (null !== $filters) {
            $filter = new Filter();
            return $filter->sanitize($param, $filters);
        }

        return $param;
    }

    /**
     * 获取头部信息
     *
     * @author yls
     * @param string|null     $name header key
     * @param null|string|int $defaultValue 默认值
     * @return mixed
     */
    final protected function getHeader(string $name = null, $defaultValue = null)
    {
        $value = $this->request->getHeader($name);
        if (null === $value && null !== $defaultValue) {
            return $defaultValue;
        }
        return $value;
    }

}