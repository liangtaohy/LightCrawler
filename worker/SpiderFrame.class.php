<?php

/**
 * 通用Spider
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/14
 * Time: PM4:00
 */
class SpiderFrame extends PHPCrawler
{
    const MAGIC = __CLASS__;

    public $storage_root = "/mnt/open-xdp/spider";

    protected $raw_data_dir = "/raw_data";

    /**
     * @var null
     */
    public $storage = null;

    private $contenttyperules = null;

    protected $ContentHandlers = array();
    protected $starting_urls = array();

    /**
     * SpiderDepartment constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    public function setFeed($url)
    {
        $this->feed = $url;
        parent::setURL($url);
    }

    private function init()
    {
        // memory limit
        ini_set('memory_limit', '512M');

        $this->seeds_file = dirname(__FILE__) . "/../config/". self::MAGIC . "_seeds.txt";

        if (file_exists($this->seeds_file)) {
            @unlink($this->seeds_file);
        }

        // Conf
        $this->contenttyperules = ContentTypeRecvRulesMgr::instance();

        // Content Type Rules
        foreach($this->contenttyperules->_rules as $v) {
            parent::addReceiveContentType($v);
        }

        // URL Filter Rules
        parent::addURLFilterRule("#(jpg|jpeg|css|js|png|mp4|mp3|download=doc|download=txt)# i");

        foreach ($this->ContentHandlers as $key => $item) {
            parent::addURLFollowRule($key);
        }

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
        parent::setWorkingDirectory(gsettings()->working_space_path);

        // UserAgent Settings
        if (stripos(gsettings()->user_agent, UA_DEFAULT) !== false) {
            parent::setUserAgentString(gsettings()->ua_default);
        } else if (stripos(gsettings()->user_agent, UA_ANDROID) !== false) {
            parent::setUserAgentString(gsettings()->ua_android);
        } else if (stripos(gsettings()->user_agent, UA_IPHONE) !== false) {
            parent::setUserAgentString(gsettings()->ua_iphone);
        } else if (stripos(gsettings()->user_agent, UA_CHROME) !== false) {
            parent::setUserAgentString(gsettings()->ua_chrome);
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

        $this->storage = KVStorageFactory::create(gsettings()->storage);

        if (!file_exists($this->storage_root)) {
            mkdir($this->storage_root, 0777, true);
        }

        if (!file_exists($this->storage_root . $this->raw_data_dir)) {
            mkdir($this->storage_root . $this->raw_data_dir, 0777, true);
        }
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

        echo $log . PHP_EOL;

        // Now you should do something with the content of the actual
        // received page or file ($DocInfo->source), we skip it in this example

        flush();

        unset($DocInfo);

        return true;
    }

    public function handleContent($DocInfo, &$total, &$inserted)
    {
        $total = 0;
        $inserted = 0;

        $has_matched = 0;
        foreach ($this->ContentHandlers as $key => $contentHandler) {
            $matched = preg_match($key, $DocInfo->url);
            if ($matched) {
                echo "enter handler " . $contentHandler . PHP_EOL;
                $has_matched = 1;
                if (method_exists($this, $contentHandler)) {
                    call_user_func(array($this, $contentHandler), $DocInfo);
                }
            }
        }

        if (empty($has_matched)) {
            $this->handleListPage($DocInfo);
        }

        return true;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool
     */
    public function handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        // write raw data into local file system
        $pages = $this->_handleListPage($DocInfo);

        foreach ($pages as $page) {
            $this->addFeed($page);
        }

        return true;
    }

    /**
     * 正文页处理
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool
     */
    public function handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        // write raw data into local file system
        $tmp = $this->storage_root . $this->raw_data_dir . '/' . date("Ymd");

        if (!file_exists($tmp)) {
            mkdir($tmp, 0777, true);
        }

        $tmp_file = $tmp . '/' . md5($DocInfo->url) . '.html';

        echo "save " . $DocInfo->url . " into " . $tmp_file . PHP_EOL;
        file_put_contents($tmp_file, "");
        file_put_contents($tmp_file, $DocInfo->url . "\n", FILE_APPEND);
        file_put_contents($tmp_file, $DocInfo->content_type . "\n", FILE_APPEND);
        file_put_contents($tmp_file, $DocInfo->source . "\n", FILE_APPEND);

        // remove \r\n (^M character)
        $patterns = array(
            chr(13),
        );

        $replaces = array(
            "\n",
        );

        $DocInfo->source = str_replace($patterns, $replaces, $DocInfo->source);

        $record = $this->_handleDetailPage($DocInfo);

        if (!empty($record) && $record instanceof XlegalLawContentRecord) {
            DaoXlegalLawContentRecord::getInstance()->insert($record);
        }

        return true;
    }

    /**
     * 保存网页中的附件
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool
     */
    public function handleAttachment(PHPCrawlerDocumentInfo $DocInfo)
    {
        // write raw data into local file system
        $tmp = $this->storage_root . $this->raw_data_dir . '/' . date("Ymd");

        if (!file_exists($tmp)) {
            mkdir($tmp, 0777, true);
        }

        $result = preg_match("#/[0-9a-zA-Z]+\.(doc|pdf|txt|xls)# i", $DocInfo->url, $matches);

        $ext = 'unknown';
        if (!empty($result) && !empty($matches) && is_array($matches)) {
            $ext = $matches[1];
        }

        $ext = strtolower($ext);

        $tmp_file = $tmp . '/' . md5($DocInfo->url) . '.' . $ext;

        echo "save " . $DocInfo->url . " into " . $tmp_file . PHP_EOL;
        file_put_contents($tmp_file, "");
        file_put_contents($tmp_file, $DocInfo->url . "\n", FILE_APPEND);
        file_put_contents($tmp_file, $DocInfo->referer_url . "\n", FILE_APPEND);
        file_put_contents($tmp_file, $DocInfo->content_type . "\n", FILE_APPEND);
        file_put_contents($tmp_file, $DocInfo->source, FILE_APPEND);

        $id = md5($DocInfo->source);

        $type = 0;
        if ($ext == "doc") {
            $type = DaoSpiderlLawBase::TYPE_DOC;
        } else if ($ext == "docx") {
            $type = DaoSpiderlLawBase::TYPE_DOCX;
        } else if ($ext == "txt") {
            $type = DaoSpiderlLawBase::TYPE_TXT;
        } else if ($ext == "xls") {
            $type = DaoSpiderlLawBase::TYPE_XLS;
        } else if ($ext == "xlsx") {
            $type = DaoSpiderlLawBase::TYPE_XLSX;
        } else if ($ext == "pdf") {
            $type = DaoSpiderlLawBase::TYPE_PDF;
        }

        $record = new XlegalLawContentRecord();
        $record->doc_id = $id;
        $record->type = $type;
        $record->url = $DocInfo->url;
        $record->url_md5 = md5($DocInfo->url);

        DaoXlegalLawContentRecord::getInstance()->insert($record);

        return true;
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo) {
        return array();
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo) {
        return false;
    }

    public function addStartingUrls($url)
    {
        $this->starting_urls[] = $url;
    }

    /**
     *
     */
    public function run()
    {
        mb_regex_encoding("UTF-8");

        if (gsettings()->enable_resume) {
            $h = md5($this->feed);
            $tmp = "/tmp/crawler_id_for_$h";
        }

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
        if (empty(gsettings()->number_of_process) || gsettings()->number_of_process < 2) {
            parent::go();
        } else {
            parent::goMultiProcessed(gsettings()->number_of_process, 2);
        }

        // After the process is finished completely: Delete the crawler-ID
        if (gsettings()->enable_resume)
            unlink($tmp);

        if (defined('CRAWLER_NAME') && !empty(CRAWLER_NAME)) {
            DaoUrlCache::getInstance()->cleanup(CRAWLER_NAME);
        }
        
        $report = parent::getProcessReport();
        $notice = "links-followed:".$report->links_followed;
        $notice .= " documents-received:" . $report->files_received;
        $notice .= " bytes-received:" . $report->bytes_received;
        $notice .= " process-runtime:" . $report->process_runtime;
        echo $notice . PHP_EOL;
    }
}