<?php
/***************************************************************************
 *
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 *
 **************************************************************************/



/**
 * @file Crawler.class.php
 * @author liangtao01(sumeru-engine@baidu.com)
 * @date 2015/05/12 21:23:59
 * @brief
 *
 **/
require_once dirname(__FILE__) . "/CrawlerSettings.class.php";
require_once dirname(__FILE__) . "/UrlFilterManager.lib.php";
require_once dirname(__FILE__) . "/ContentTypeRecvRulesMgr.lib.php";
require_once dirname(__FILE__) . "/Extractor.lib.php";
require_once dirname(__FILE__) . "/../vendor/PHPCrawl_083/libs/PHPCrawler.class.php";

class Crawler extends PHPCrawler
{
    private $urlfiltermgr = null;
    private $contenttyperules = null;

    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    public function setFeed($url)
    {
        parent::setURL($url);
    }

    public function handleDocumentInfo(PHPCrawlerDocumentInfo $DocInfo)
    {
        // Just detect linebreak for output
        if (PHP_SAPI == "cli") $lb = "\n";
        else $lb = "<br />";
        $log = "page:" . $DocInfo->url;
        $log .= " status:" . $DocInfo->http_status_code . " referer:" . $DocInfo->referer_url;
        $log .= " bytes:" . $DocInfo->bytes_received;
        if ($DocInfo->error_occured) {
            $log .= " error_occured:" . $DocInfo->error_occured . " error_string:({$DocInfo->error_string})";
        } else if ((int)($DocInfo->http_status_code) == 200) { // status 200 OK
            if ($DocInfo->bytes_received > 100) { // call extractor
                $this->handleContent($DocInfo, $total, $inserted);
                $log .= " total:" . $total . " inserted:" . $inserted;
            }
        }

        echo 'NOTICE {$log}' . PHP_EOL;

        flush();

        unset($DocInfo);
        // for test
        if (gsettings()->debug === true)
            exit(0);
    }

    public function handleContent($DocInfo, &$total, &$inserted)
    {
        // insert all followed urls into data service
        // mark the current url to be followed
        // encode content into utf8
        // extracting
        // write result into data service
        $extractor = new Extractor($DocInfo->source, $DocInfo->url);
        $res = $extractor->buildProducts();
        $inserted = $this->write($res, $total, $inserted);
    }

    private function write($res, &$total, &$inserted)
    {
        $total = $inserted = 0;
        if (!isset($res) || empty($res)) {
            return 0;
        }

        if (isset($res) && !is_array($res)) return 0;
        if (!isset($res["products"]) || empty($res["products"])) return 0;
        $num = 0;
        if (gsettings()->debug === false)
            $storage = KVStorageFactory::create(KVStorageFactory::KV_LEVELDB);
        else
            $storage = KVStorageFactory::create(KVStorageFactory::KV_FILE);
        foreach($res["products"] as $v) {
            if (isset($v["src_url"]))
                $key = $v["src_url"];
            else $key = "";

            $total++;
            $r = $storage->set($key, $v);
            if ($r === false) {
                LeoLog::WARNING("insert ds service fail: %s", $key);
            } else $inserted++;
        }
        return $inserted;
    }

    private function init()
    {
        // memory limit
        ini_set('memory_limit', '256M');

        // Conf
        $this->urlfiltermgr = UrlFilterManager::instance();
        $this->contenttyperules = ContentTypeRecvRulesMgr::instance();

        // Content Type Rules
        foreach($this->contenttyperules->_rules as $v) {
            parent::addReceiveContentType($v);
        }

        // URL Filter Rules
        foreach($this->urlfiltermgr->_filterrules as $v) {
            parent::addURLFilterRule($v);
        }

        // URL Follow Rules

        // Follow Mode - default is UrlFollowMode::FOLLOW_MODE_HOST
        parent::setFollowMode(gsettings()->follow_mode);
        parent::enableCookieHandling(gsettings()->cookie_handling_mode);
        parent::setUrlCacheType(gsettings()->url_cache_type);
        parent::obeyRobotsTxt(gsettings()->obey_robots);
        parent::setConnectionTimeout(gsettings()->connect_timeout);
        parent::setStreamTimeout(gsettings()->stream_timeout);
        parent::enableAggressiveLinkSearch(gsettings()->aggressive_link_search);
        parent::setRequestLimit(gsettings()->request_limit);
        parent::setContentSizeLimit(gsettings()->content_size_limit);
        parent::setTrafficLimit(gsettings()->traffic_limit);
        // UserAgent Settings
        if (stripos(gsettings()->user_agent, UA_DEFAULT) !== false) {
            parent::setUserAgentString(gsettings()->ua_default);
        } else if (stripos(gsettings()->user_agent, UA_ANDROID) !== false) {
            parent::setUserAgentString(gsettings()->ua_android);
        } else if (stripos(gsettings()->UserAgent, UA_IPHONE) !== false) {
            parent::setUserAgentString(gsettings()->ua_iphone);
        }

        parent::requestGzipContent(gsettings()->gzip_encoded_mode);
        parent::setFollowRedirects(gsettings()->header_redirects_mode);
        // Request Delay
        // Limit request rate
        parent::setRequestDelay(gsettings()->global_request_delay);
        // Retry Limit
        // default:1
        parent::setRetryLimit(gsettings()->retry_limit);
        if (gsettings()->enable_resume)
            parent::enableResumption();
    }

    public function run()
    {
        $h = md5($this->feed);
        $tmp = "/tmp/crawler_id_for_$h";
        if (gsettings()->enable_resume) {
            if (!file_exists($tmp)) {
                $crawler_id = parent::getCrawlerId();
                file_put_contents($tmp, $crawler_id);
            } else {
                $crawler_id = file_get_contents($tmp);
                parent::resume($crawler_id);
            }
        }
        // exclude user code (user code will be excuted in child process)
        parent::goMultiProcessed(gsettings()->number_of_process, 2);
        // After the process is finished completely: Delete the crawler-ID
        if (gsettings()->enable_resume)
            unlink($tmp);

        $report = parent::getProcessReport();
        $notice = "links-followed:".$report->links_followed;
        $notice .= " documents-received:" . $report->files_received;
        $notice .= " bytes-received:" . $report->bytes_received;
        $notice .= " process-runtime:" . $report->process_runtime;
        echo "NOTICE {$notice}" . PHP_EOL;
    }
};

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */