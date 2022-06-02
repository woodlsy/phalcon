# phalcon
- version 0.3.29
### 入口文件
```php
define('APP_NAME', 'app'); // 项目名称
define('APP_DIR', 'application'); // 项目文件根目录文件，无则留空

require '../vendor/woodlsy/phalcon/public/index.php';
```
### 脚本
tasks文件夹放在根目录下
入口文件cli.php
```php
define('APP_NAME', 'app');

require_once 'vendor/woodlsy/phalcon/cli.php';
```

### 配置文件
配置文件有两种方式
- application/config/config.php
```php
return [
    'open_modules' => true, // 是否开启多模块，true 是 false 否
    'modules' => [
        'index',
    ],
    'yar_service' => false, // 是否作为yar的服务端
    'limit_request' => true, // 限制频繁请求 true 是 false 否
    'csrf' => true, // 是否开启csrf true是 false 否
    'csrf_key_name' => 'tokenKey',
    'csrf_key_value' => 'tokenValue',
    'logsPath'  => '/data/logs/'.APP_NAME.'/',
    'viewsDir'       => APP_PATH . '/views/',
    'debug'          => true,
    'suffix' => 'html', // URL后缀
    'pSql'             => false, // 打印sql
    'isCast'           => false, // 强制转换数据类型
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
];
```

- .env（优先）

env文件放在根目录下，配置的内容和config一致，只是写法不同而已，具体可以参考[.example_env](.example_env)
env文件中定义的配置，也可在程序中通过getenv('DEBUG')来获取，需要注意的是一下两点：
1. 根据大众的惯例，env中的KEY都会转为大写
2. 因PHP读取env文件的限制，key不能为null，yes，no，true 和 false，而value为null，no 和 false 等效于 ""，value为 yes 和 true 等效于 "1"。需自己进行类型的转换。