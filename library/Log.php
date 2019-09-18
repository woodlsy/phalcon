<?php
namespace woodlsy\phalcon\library;

use Phalcon\DI;

/**
 * Class Log
 *
 * @author yls
 * @package library
 */
class Log
{
    
    private static $_isSetTrigger = false;
    
    /**
     * 手动埋点写业务日志
     *
     * @param string $mark      业务标记
     * @param string|array $message
     * @param string $fileName
     * @create_time 2017年11月23日
     */
    public static function write(string $mark, $message, string $fileName='') {
        if(is_array($message) || is_object($message))$message = json_encode($message, JSON_UNESCAPED_UNICODE);
        
        $logPath = DI::getDefault()->get('config')->logsPath.date('Y-m').'/';
        self::directory($logPath);
        
        //文件名
        $fileName = $fileName ? date('d').'-'.$fileName : date('d').'-'.$mark;
        if(explode('.', $fileName)[count(explode('.', $fileName))-1] != 'log')$fileName .= '.log';
        $file = $logPath.$fileName;
        
        $message = date('Y-m-d H:i:s')." 【{$mark}】"." {$message}\r\n";
        
        error_log($message, 3,$file );
    }
    
    /**
     * 捕捉错误并记入日志
     *
     * @param string $errno
     * @param string $errstr
     * @param string $errfile
     * @param string $errline
     * @create_time 2017年12月18日
     */
    public static function setTriggerError($errno='', $errstr='', $errfile='', $errline='')
    {
        if (self::$_isSetTrigger == false){
            //设置错误日志
            set_error_handler(__CLASS__.'::setTriggerError');
            self::$_isSetTrigger = true;
            return;
        }
        switch ($errno) {
            case E_USER_ERROR:
                $level = 'error';
                break;
            case E_USER_WARNING:
                $level = 'warning';
                break;
            case E_USER_NOTICE:
                $level = 'notice';
                break;
            default:
                $level = 'otherError';
                break;
        }
        self::write($level, $errstr."\r\n保存定位：".$errfile.' '.$errline, 'trigger_error');
    }
    
    /**
     * 自动创建目录
     * @param string $dir
     * @return boolean
     * @create_time 2017年11月21日
     */
    public static function directory( $dir ){  
       if(is_dir ( $dir ) && !is_writable($dir)){
           echo '日志目录没有写入权限';
           exit;
       }
       return  is_dir ( $dir ) or self::directory(dirname( $dir )) and  mkdir ( $dir , 0777);
    }
    
}