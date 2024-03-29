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
    protected $ip             = null;

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
        $this->ip             = $this->request->getClientAddress(true);

        $this->config = Di::getDefault()->getConfig();

        if ($this->request->isPost() && true === (bool) $this->config->limit_request) {
            if (!session_id()) {
                session_start();
            }
            $key = session_id() . '_controller_lock_' . $this->moduleName . '_' . $this->controllerName . '_' . $this->actionName;
            if (Redis::getInstance()->exists($key)) {
                throw new Exception('请勿频繁请求');
            } else {
                Redis::getInstance()->setex($key, 5, 1);
            }
        }

        // 限制IP请求
        $this->_limitIp();
    }

    /**
     * 执行完控制器后执行
     *
     * @author yls
     */
    public function afterExecuteRoute()
    {
        $key = session_id() . '_controller_lock_' . $this->moduleName . '_' . $this->controllerName . '_' . $this->actionName;
        if (Redis::getInstance()->exists($key)) {
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
        if (null !== $data)
            $returnMsg['data'] = $data;

        if ($other && is_array($other)) {
            foreach ($other as $key => $val) {
                $returnMsg[$key] = $val;
            }
        }
        switch (strtoupper($type)) {
            case 'JSON':
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

    protected function autoGenerateModels(){
        $db = $this->get('db');
        $path = $this->get('path');
        if (!empty($db) && !empty($path)) {
             $this->_autoGenerateModels();
            return;
        }


        $databasesHtml = "";
        if (!empty($this->config->db)) {
            foreach ($this->config->db as $k => $v) {
                $databases .= "<option value='{$k}'>{$k}</option>";
            }
        }
        echo <<<EOT
        
        <div style="margin-top: 300px;text-align: center">
        <form method="get" >
        <div style="margin-bottom: 10px">数  据  库：<select name="db" id="db" style="width:200px">
        <option value="">请选择数据库</option>
       $databases
</select></div>
        <div style="margin-bottom: 10px">Model路径：<input type="text" style="width:200px" name="path" id="path"></div>
        <div style="margin-bottom: 10px">继承Model：<input type="text" style="width:200px" name="extend" id="extend"></div>
        <button type="submit" id="submit" style="padding: 5px 10px">生成</button>
        </form>
        </div>
        <script src="https://apps.bdimg.com/libs/jquery/2.1.4/jquery.min.js"></script>
        <script type="text/javascript">
        $(function (){
            $('#submit').click(function (){
                if($('#db').val() === "") {
                    alert("请先选择数据库");
                    return false
                }
                if($('#path').val() === "") {
                    alert("请填写model文件夹路径");
                    return false
                }
                // $('form').submit()
            })
        })
        
        </script>
EOT;
        exit;
    }

    private function _autoGenerateModels()
    {
        $db = $this->get('db');
        $path = $this->get('path');
        $extend = $this->get('extend');

        $filePath = '../'.str_replace('\\', '/', $path).'/';
        $path = str_replace('/', '\\', $path);

        if (!empty($extend)) {
            $extend = str_replace('/', '\\', $extend);
            $extend = 'extends \\'.$extend;
        }

        $connection = \Phalcon\DI::getDefault()->get($db);
        $sql = 'show tables';
        $tables      = $connection->fetchAll($sql);
        if (empty($tables)) {
            echo '找不到表';
            return;
        }
        foreach ($tables as $table) {
            $tableName = $table['Tables_in_zcb'];
            if (!empty($this->config->db->{$db}->prefix)) {
                $tableName = substr($tableName, strlen($this->config->db->{$db}->prefix));
            }
            $fileName = '';
            foreach (explode('_', $tableName) as $tableNameWord) {
                $fileName .= ucfirst($tableNameWord);
            }
            $fileFullPath = $filePath.$fileName.'.php';
            if (file_exists($fileFullPath)) {
                echo $fileFullPath.' <span style="color: gray">已存在跳过</span><br>';
                continue;
            }

            $content = $this->_getTableAttribute($db,$table['Tables_in_zcb'] );
            $fileContent = <<<EOT
<?php
declare(strict_types = 1);

namespace $path;

class $fileName $extend
{
$content
}
EOT;
            $res = file_put_contents($fileFullPath, $fileContent);
            if ($res === false) {
                echo $fileFullPath.' <span style="color: red">创建失败</span><br>';
            }else {
                echo $fileFullPath.' <span style="color: green">创建成功</span><br>';
            }
        }

    }


    /**
     * tmp
     * 表属性
     *
     * @author yls
     * @param string $database
     * @param string $table
     * @return string
     */
    protected function getTableAttribute(string $database, string $table) : string
    {
        $data = $this->_getTableAttribute($database, $table);
        return '<pre>' . $data . '</pre>';
    }

    private function _getTableAttribute(string $database, string $table){
        $newTableName = $table;
        if (!empty($this->config->db->{$database}->prefix)) {
            $newTableName = substr($newTableName, strlen($this->config->db->{$database}->prefix));
        }

        $sql = 'SHOW FULL COLUMNS FROM ' . $table;

        $connection = \Phalcon\DI::getDefault()->get($database);
        $table      = $connection->fetchAll($sql);
        $attribute  = [];
        foreach ($table as $value) {
            $attribute[$value['Field']] = $value['Comment'] ? : $value['Field'];
        }
        $attr =  var_export($attribute, true);

        $newTableNameValue = '{{'.$newTableName.'}}';
        $_targetDbValue = '$_targetDb';
        $_targetTableValue = '$_targetTable';
        $data = <<<EOT
    /**
     * 库名
     *
     * @var string
     */
    public $_targetDbValue = '$database';

    /**
     * 表名
     *
     * @var string
     */
    protected $_targetTableValue = '$newTableNameValue';

    /**
     * 表字段属性
     *
     * @return array
     */
    public function attribute():array{
        return $attr;
    }
EOT;

        return $data;
    }

    /**
     * 限制IP请求
     *
     * @author yls
     * @throws Exception
     */
    private function _limitIp()
    {
        $ipKey             = $this->ip . $this->moduleName . '_' . $this->controllerName . '_' . $this->actionName;
        $ipLimitKey        = md5($ipKey);
        $ipLimitErrorKey   = md5($ipKey . '_error');
        $ipLimitRequestKey = md5($ipKey . '_request');

        if (Redis::getInstance()->exists($ipLimitRequestKey)) {
            throw new Exception('您已被限制请求，如需解除，请联系管理员');
        }

        foreach ([$ipLimitKey => $this->config->ipApiLimitCount, md5($this->ip) => $this->config->ipLimitCount] as $key => $value) {
            if (true === (bool) $this->config->ipLimit) {
                $ipLimitKeyExists = Redis::getInstance()->exists($key);
                $count            = Redis::getInstance()->incr($key);
                if (!$ipLimitKeyExists) {
                    Redis::getInstance()->expire($key, $this->config->ipApiLimitTime);
                }
                if ($count > $value) {
                    $ipLimitErrorKeyExists = Redis::getInstance()->exists($ipLimitErrorKey);
                    if (Redis::getInstance()->incr($ipLimitErrorKey) > 5) {
                        Redis::getInstance()->setEx($ipLimitRequestKey, 12 * 3600, 1);
                    }
                    if (!$ipLimitErrorKeyExists) {
                        Redis::getInstance()->expire($ipLimitErrorKey, 300);
                    }
                    throw new Exception('网络也是有脾气的，慢一点请求哦');
                }
            }
        }

    }

}