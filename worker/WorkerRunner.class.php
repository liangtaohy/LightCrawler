<?php

/**
 * Worker Runner
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/18
 * Time: PM7:03
 */

/**
 * -t target worker class
 * -h help
 */

$ext = ".class.php";

$params = getopt('t:hds:n:m:');

$base = dirname(__FILE__);

$targetClass = isset($params['t']) && !empty($params['t']) ? trim($params['t']) : '';

$seed = isset($params['s']) && !empty($params['s']) ? trim($params['s']) : '';

$debug = isset($params['d']) ? true : false;

$m = isset($params['m']) ? true : false;

$numProcess = isset($params['n']) && !empty($params['n']) ? intval($params['n']) : 0;

if (empty($targetClass)) {
    echo "Example: " . PHP_EOL;
    echo "-t DemoTargetWorker" . PHP_EOL;
    echo "please input a valid worker class name to run!!" . PHP_EOL;
    exit(0);
}

$target_file = $base . "/" . $targetClass . $ext;

$target_file1 = "/home/work/xdp/phpsrc/app/LightCrawler/worker/" . $targetClass . $ext;

if (!file_exists($target_file) && file_exists($target_file1)) {
    $target_file = $target_file1;
} else {
    if (!file_exists($target_file)) {
        echo "{$target_file} not existed!" . PHP_EOL;
        echo "Example: " . PHP_EOL;
        echo "-t DemoTargetWorker" . PHP_EOL;
        echo "please input a valid worker class name to run!!" . PHP_EOL;
        exit(0);
    }
}

require_once $target_file;

if (!defined("CRAWLER_NAME")) {
    echo "CRAWLER_NAME should be defined in class {$targetClass}!" . PHP_EOL;
    exit(0);
}

$pid = posix_getpid();

file_put_contents('spider_' . $targetClass::MAGIC . '.pid', $pid);
gsettings()->debug = $debug;
if (!empty($numProcess)) {
    gsettings()->number_of_process = $numProcess;
}

if (gsettings()->debug == true || $m == true) {
    gsettings()->url_cache_type = URL_CACHE_IN_MEMORY;
    gsettings()->enable_resume = false;
    gsettings()->number_of_process = 1;
}

$spider = new $targetClass();

if (method_exists($targetClass,'setFeed')) {
    if (!empty($seed)) {
        echo "starting url: " . $seed . PHP_EOL;
        $spider->setFeed($seed);
    } else {
        echo "starting url: " . $targetClass::$SeedConf[0] . PHP_EOL;
        $spider->setFeed($targetClass::$SeedConf[0]);

        for ($i=1;$i<count($targetClass::$SeedConf); $i++) {
            echo "starting urls: " . $targetClass::$SeedConf[$i] . PHP_EOL;
            $spider->addStartingUrls($targetClass::$SeedConf[$i]);
        }
    }
} else {
    echo "no setFeed method, just run method is called" . PHP_EOL;
}

$spider->run();