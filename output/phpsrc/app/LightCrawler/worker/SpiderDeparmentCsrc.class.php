<?php

/**
 * 通用抓取爬虫
 * 只处理列表页和内容页
 * 不做数据挖掘工作,数据挖掘由业务层来做.
 * 此爬虫只保存原始数据信息
 * User: liangtaohy@163.com
 * Date: 17/4/8
 * Time: PM12:02
 */
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderDeparmentCsrc extends PHPCrawler
{
    protected $starting_urls = array();
    const MAGIC = __CLASS__;

    public $storage_root = "/mnt/open-xdp/spider";

    private $raw_data_dir = "/raw_data";

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        //"http://www.csrc.gov.cn/wcm/govsearch/year_gkml_list.jsp?schn=832&years=2017&sinfo=&countpos=0&curpos=%E4%B8%BB%E9%A2%98%E5%88%86%E7%B1%BB&page=1",
        //"http://www.csrc.gov.cn/wcm/govsearch/year_gkml_list.jsp?schn=832&years=2016&sinfo=&countpos=0&curpos=%E4%B8%BB%E9%A2%98%E5%88%86%E7%B1%BB&page=1",
        "http://www.csrc.gov.cn/wcm/govsearch/year_gkml_list.jsp?schn=832&years=2015&sinfo=&countpos=0&curpos=%E4%B8%BB%E9%A2%98%E5%88%86%E7%B1%BB&page=1",
        "http://www.csrc.gov.cn/wcm/govsearch/year_gkml_list.jsp?schn=832&years=2014&sinfo=&countpos=0&curpos=%E4%B8%BB%E9%A2%98%E5%88%86%E7%B1%BB&page=1",
        "http://www.csrc.gov.cn/wcm/govsearch/year_gkml_list.jsp?schn=832&years=2013&sinfo=&countpos=0&curpos=%E4%B8%BB%E9%A2%98%E5%88%86%E7%B1%BB&page=1",
        "http://www.csrc.gov.cn/wcm/govsearch/year_gkml_list.jsp?schn=832&years=2012&sinfo=&countpos=0&curpos=%E4%B8%BB%E9%A2%98%E5%88%86%E7%B1%BB&page=1",
        "http://www.csrc.gov.cn/wcm/govsearch/year_gkml_list.jsp?schn=832&years=2011&sinfo=&countpos=0&curpos=%E4%B8%BB%E9%A2%98%E5%88%86%E7%B1%BB&page=1",
        "http://www.csrc.gov.cn/wcm/govsearch/year_gkml_list.jsp?schn=832&years=ss2011&sinfo=&curpos=%E4%B8%BB%E9%A2%98%E5%88%86%E7%B1%BB&countpos=0&page=1",
    );

    static $ContentHandlers = array(
        "#http://www.csrc.gov.cn/wcm/govsearch/year_gkml_list.jsp# i" => "handleListPage",
        "#http://www.csrc.gov.cn/pub/zjhpublic/.*/t[0-9]+_[0-9]+\.htm# i"  => "handleDetailPage"
    );

    /**
     * @var null
     */
    public $storage = null;

    private $contenttyperules = null;

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

        foreach (self::$ContentHandlers as $key => $item) {
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
        // for test
        if (gsettings()->debug === true)
            exit(0);

        return true;
    }

    public function handleContent($DocInfo, &$total, &$inserted)
    {
        $total = 0;
        $inserted = 0;

        $has_matched = 0;
        foreach (self::$ContentHandlers as $key => $contentHandler) {
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

        $pager = $this->computePages($DocInfo);

        $pages = array();

        for ($i = 1; $i <= $pager['pages']; $i++) {
            $pages[] = preg_replace("#(page=[0-9]+)#", "page=" . $i, $DocInfo->url);
        }

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
            '<BR>',
            '<br />',
            '<br>',
            '<BR />'
        );

        $replaces = array(
            "\n",
            "\n",
            "\n",
            "\n",
            "\n"
        );

        $source = str_replace($patterns, $replaces, $DocInfo->source);

        $pregfind = array("#<script.*>.*</script>#siU");
        $pregreplace = array('');
        $source = preg_replace($pregfind, $pregreplace, $source);

        $extractor = new Extractor($source, $DocInfo->url);
        $doc = $extractor->document();

        if (empty($doc)) {
            echo "content is null: " . $DocInfo->url . PHP_EOL;
            return true;
        }

        $content = $doc->query("//body");

        $raw = array();
        if ($content instanceof DOMNodeList && !empty($content)) {
            foreach ($content as $t) {
                $raw[] = $t->nodeValue;
            }
        }

        $c = implode("", array_filter($raw));

        // If exists, drop it begin
        $id = ContentHelper::GenContentMd5($c);

        $data = array(
            'doc_id'    => $id,
            'type'  => DaoSpiderlLawBase::TYPE_HTML,
            'url'   => $DocInfo->url,
            'url_md5'   => md5($DocInfo->url),
            'content'   => $DocInfo->source,
            'simhash'   => '',
        );

        if (DaoSpiderlLawBase::getInstance()->ifContentExists($data['url_md5'], $data['doc_id'])) {
            echo "data exsits: urlmd5-{$data['url_md5']}, doc_id-{$data['doc_id']}, " . $DocInfo->url;
            return true;
        }

        $res = FlaskRestClient::GetInstance()->simHash($data['content']);

        $simhash = '';
        if (isset($res['simhash']) && !empty($res['simhash'])) {
            $simhash = $res['simhash'];
        }

        if (isset($res['repeated']) && !empty($res['repeated'])) {
            echo 'data repeated: ' . $DocInfo->url . PHP_EOL;
            //return true;
        }

        $data['simhash'] = $simhash;

        DaoSpiderlLawBase::getInstance()->insert($data);

        return true;
    }

    /**
     * 分页计算器
     * pager computer
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array|bool
     */
    public function computePages(PHPCrawlerDocumentInfo $DocInfo)
    {
        $totalPatterns = array(
            "#var m_nRecordCount = \"([0-9]+)\";# i",
            "#var m_nRecordCount = ([0-9]+);# i",
        );

        $pagesizePatterns = array(
            "#var m_nPageSize = ([0-9]+);# i",
        );

        $total = 0;
        $pagesize = 0;

        foreach ($totalPatterns as $totalPattern) {
            $result = preg_match($totalPattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $total = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        if (empty($total)) {
            echo "FATAL get total page failed: " . $DocInfo->url . PHP_EOL;
            return true;
        }

        unset($result);
        unset($matches);

        foreach ($pagesizePatterns as $pagesizePattern) {
            $result = preg_match($pagesizePattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $pagesize = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        if (empty($pagesize)) {
            echo "FATAL get pagesize failed: " . $DocInfo->url . PHP_EOL;
            return array(
                'total' => $total,
            );
        }

        $res = array(
            'total' => $total,
        );
        $total = intval($total / $pagesize);
        $res['pages'] = $total;
        $res['pagesize'] = $pagesize;

        return $res;
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
        file_put_contents($tmp_file, $DocInfo->content_type . "\n", FILE_APPEND);
        file_put_contents($tmp_file, $DocInfo->source . "\n", FILE_APPEND);

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

        $data = array(
            'doc_id'    => $id,
            'type'  => $type,
            'url'   => $DocInfo->url,
            'url_md5'   => md5($DocInfo->url),
            'content'   => '',
            'simhash'   => '',
        );

        if (DaoSpiderlLawBase::getInstance()->ifContentExists($data['url_md5'], $data['doc_id'])) {
            echo "data exsits: urlmd5-{$data['url_md5']}, doc_id-{$data['doc_id']}, " . $DocInfo->url;
            return true;
        }

        DaoSpiderlLawBase::getInstance()->insert($data);

        return true;
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

        $report = parent::getProcessReport();
        $notice = "links-followed:".$report->links_followed;
        $notice .= " documents-received:" . $report->files_received;
        $notice .= " bytes-received:" . $report->bytes_received;
        $notice .= " process-runtime:" . $report->process_runtime;
        echo $notice . PHP_EOL;
    }

    public function addStartingUrls($url)
    {
        $this->starting_urls[] = $url;
    }
}

$pid = posix_getpid();

file_put_contents('spider_' . SpiderDeparmentCsrc::MAGIC . '.pid', $pid);
gsettings()->debug = false;

gsettings()->url_cache_type = URL_CACHE_IN_MEMORY;
gsettings()->enable_resume = false;
gsettings()->number_of_process = 1;

$spider = new SpiderDeparmentCsrc();

$spider->setFeed(SpiderDeparmentCsrc::$SeedConf[0]);

for ($i=1;$i<count(SpiderDeparmentCsrc::$SeedConf); $i++) {
    $spider->addFeed(SpiderDeparmentCsrc::$SeedConf[$i]);
}

$spider->run();