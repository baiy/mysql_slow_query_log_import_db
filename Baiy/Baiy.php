<?php
namespace Baiy;

Baiy::init();

/**
 * 初始化类
 */
class Baiy {
    /**
     * 初始化
     */
	public static function init(){
        // 注册自动加载
        spl_autoload_register(__CLASS__.'::autoload');

        //环境初始化
        self::setCharset();
        self::setTimeZone();
	}

    /**
     * 注册错误处理
     */
    public static function setErrorShow(){
        // 致命错误捕获
        error_reporting(0);
        register_shutdown_function(__CLASS__.'::fatalError');
    }

	/**
	 * 类加载
	 */
	public static function autoload($class){
		if(strpos($class,'\\') !== false){
			$class = explode('\\', $class);
			$class = end($class);
			$path = __DIR__.DIRECTORY_SEPARATOR.$class.'.php';
			if(is_file($path)){
				include $path;
			}
		}
	}

    /**
     * 设置编码
     * @param type $charset
     */
    public static function setCharset($charset = 'utf-8'){
        header('Content-type: text/html; charset='.$charset);
    }

    /**
     * 设置时区
     * @param type $time
     */
    public static function setTimeZone($time = 'Etc/GMT-8'){
        date_default_timezone_set($time);
    }

    /**
     * 致命错误捕获
     */
    public static function fatalError() {
        if ($e = error_get_last()) {
            switch($e['type']){
              case E_ERROR:
              case E_PARSE:
              case E_CORE_ERROR:
              case E_COMPILE_ERROR:
              case E_USER_ERROR:
                print_r($e);
                exit();
            }
        }
    }
}