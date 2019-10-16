# phalcon
### 一、入口文件
```php
define('APP_NAME', 'blog'); // 项目名称
define('APP_DIR', 'application'); // 项目文件根目录文件，无则留空

require '../vendor/woodlsy/phalcon/public/index.php';
```
### 配置文件
```php
[
    'open_modules' => true, // 是否开启多模块，true 是 false 否
    'limit_request' => true, // 限制频繁请求 true 是 false 否
    'csrf' => true, // 是否开启csrf true是 false 否
    'csrf_key_name' => 'tokenKey',
    'csrf_key_value' => 'tokenValue',
    'logsPath'  => '/data/logs/'.APP_NAME.'/',
    'viewsDir'       => APP_PATH . '/views/',
    'debug'          => true,
    'redis' => [
        'host' =>'127.0.0.1',
        'port' =>'6379',
        'password' => '123456', //密码 默认为空
        'prefix' => 'app_', //KEY的前缀 默认 空
    ],
    'db' => [
        'master'=>[
            'adapter'  => 'mysql',
            'host'     => '127.0.0.1',
            'username' => 'root',
            'password' => '',
            'port'     => '3306',
            'dbname'   => 'user',
            'prefix'   => 'pr_',
            'charset'  => 'utf8',
        ],
    ],
]
```
