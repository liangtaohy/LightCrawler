<?php
/**
 * @file CrawlerSettings.class.php
 * @author LiangTao (liangtaohy@163.com)
 * @date 2015/05/12 20:47:19
 * @brief
 *
 **/
require_once dirname(__FILE__) . '/CrawlerConst.class.php';

function gsettings()
{
    $root = dirname(__FILE__);
    static $_g_settings = null;
    if (!isset($_g_settings)) {
        $_g_settings = array(
            "log_level" => 16, // debug
            // Content-Type rules
            "content_type" => $root . "/content_type_recv_rules.txt",
            // UrlFilter Rules. If url matched, will be ignore.
            "urlfilter_rules" => $root . "/urlfilter_rules.txt",
            // Url Follow Mode (domain, host, path)
            "follow_mode" => FOLLOW_MODE_DOMAIN,
            // Multi-Process Number
            "number_of_process" => 4,
            "enable_resume" => true,
            // If redirects enabled, set to be true
            "header_redirects_mode" => true,
            // Limit Requirements
            "request_limit" => 0,
            "content_size_limit" => 0,
            "traffic_limit" => 0,
            "cookie_handling_mode" => true,
            "aggressive_link_search" => true,
            "user_agent" => UA_CHROME,
            // Retry Limit
            "retry_limit" => 3,
            // Timers
            "connect_timeout" => 60,
            "stream_timeout" => 60,
            "url_cache_type" => URL_CACHE_IN_MYSQL,
            "gzip_encoded_mode" => true,
            "global_request_delay" => 5,
            "max_crawling_depth" => 1,
            "obey_robots" => false,
            "ua_default" => "PHPCrawl 1.0/lotushy",
            "ua_wap" => "",
            "ua_chrome" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36",
            "debug"=>false,
            "working_space_path"=> "/mnt/open-xdp/spider/data/",
            "storage"=>'file',
        );
    }
    static $res = null;
    if (!isset($res))
        $res = json_decode(json_encode($_g_settings));
    return $res;
}

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */