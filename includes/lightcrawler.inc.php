<?php
/**
 * Created by PhpStorm.
 * User: LiangTao (liangtaohy@163.com)
 * Date: 17/2/8
 * Time: PM2:59
 */
define('IS_DEBUG', false);

//define('IS_ONLINE', true);
define('APP_NAME' , 'LightCrawler');
define('APP_PATH', dirname(__FILE__) . '/..');
define('DEPLOY_ROOT', APP_PATH . '/../../../');
define('APP_CONF_PATH', APP_PATH . '/../../conf/' . APP_NAME);
define('CURRENT_TAG', 'default');
define('QUERY_ENABLE', true);
define('LOG_PATH', DEPLOY_ROOT . '/phpsrc/logs/');

date_default_timezone_set('Asia/Shanghai');
define('PROCESS_START_TIME', (int)($_SERVER['REQUEST_TIME_FLOAT'] * 1000));

require_once(APP_PATH . '/../../phplib/phplib_headers.php');

/** We will use autoloader instead of include path. */
$appIncludePath = APP_PATH .'/worker/:'.
    APP_CONF_PATH . '/:';
ini_set('include_path', ini_get('include_path') . ':' . $appIncludePath);

//日志打印相关参数定义
$GLOBALS['LOG'] = array(
    'log_level' => MeLog::LOG_LEVEL_ALL,
    'log_file'  => DEPLOY_ROOT . '/phpsrc/logs/lightcrawler.log',
);

require_once dirname(__FILE__) . "/../libs/CrawlerSettings.class.php";
require_once dirname(__FILE__) . "/../libs/UrlFilterManager.class.php";
require_once dirname(__FILE__) . "/../libs/ContentTypeRecvRulesMgr.class.php";
require_once dirname(__FILE__) . "/../libs/Extractor.class.php";
require_once dirname(__FILE__) . "/../libs/Formatter.class.php";
require_once dirname(__FILE__) . "/../vendor/PHPCrawl_083/libs/PHPCrawler.class.php";
require_once dirname(__FILE__) . "/../libs/DSKVStorage.lib.php";

