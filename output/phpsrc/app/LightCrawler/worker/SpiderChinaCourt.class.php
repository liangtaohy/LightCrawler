<?php

/**
 * chinacourt.org
 *
 * User: liangtaohy@163.com
 * Date: 17/3/31
 * Time: PM10:01
 */
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderChinaCourt extends PHPCrawler
{
    const MAGIC = "SpiderChinaCourt";

    public $storage_root = "/mnt/open-xdp/spider";

    private $raw_data_dir = "/raw_data";

    protected $starting_urls = array();

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.chinacourt.org/law.shtml",
        "http://www.chinacourt.org/law/more/law_type_id/MzAwMkAFAA%3D%3D/page/1.shtml",
        "http://www.chinacourt.org/law/more/law_type_id/MzAwNEAFAA%3D%3D.shtml",
        "http://www.chinacourt.org/law/more/law_type_id/MzAwM0AFAA%3D%3D.shtml",
    );

    static $ContentHandlers = array(
        "#(http://www.chinacourt.org/law/more/law_type_id/[a-zA-Z]+%3D%3D/page/[0-9]+.shtml)$# i" => "handleListPage",
        "#(http://www.chinacourt.org/law/more/law_type_id/[a-zA-Z]+%3D%3D.shtml)$# i" => "handleListPage",
        "#(http://www.chinacourt.org/law/detail/[0-9]+/[0-9]+/id/[0-9]+.shtml)$# i" => "handleDetailPage",
    );

    /**
     * @var null
     */
    public $storage = null;

    private $contenttyperules = null;

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

        foreach (self::$ContentHandlers as $key => $contentHandler) {
            $matched = preg_match($key, $DocInfo->url);
            if ($matched) {
                echo "enter handler " . $contentHandler . PHP_EOL;
                call_user_func(array($this, $contentHandler), $DocInfo);
            }
            unset($matched);
        }

        return true;
    }

    public function handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        return true;
    }

    public function handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        // write raw data into local file system
        $tmp = $this->storage_root . $this->raw_data_dir . '/' . date("Ymd");

        if (!file_exists($tmp)) {
            mkdir($tmp, 0777, true);
        }

        $tmp_file = $tmp . '/' . md5($DocInfo->url) . '.html';

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
        $extractor = new Extractor($source, $DocInfo->url);
        $doc = $extractor->document();

        if (empty($doc)) {
            echo "content is null: " . $DocInfo->url . PHP_EOL;
            return;
        }

        $source = str_replace($patterns, $replaces, $DocInfo->source);

        $extractor = new Extractor($source, $DocInfo->url);
        $doc = $extractor->document();

        if (empty($doc)) {
            echo "content is null: " . $DocInfo->url . PHP_EOL;
            return;
        }

        $stitles = $doc->query("//div[@class='law_content']/span[@class='STitle']");
        $context_texts = $doc->query("//div[@class='law_content']/div[@class='content_text']");

        $mtitles = $doc->query("//p[@align='center']/font[@class='MTitle']");

        $title = '';
        $raw_content = '';
        $summary = '';

        if ($stitles instanceof DOMNodeList && !empty($stitles)) {
            foreach ($stitles as $element) {
                if ($element->nodeName === 'span') {
                    $summary = $element->nodeValue;
                    break;
                }
            }
        }

        if ($mtitles instanceof DOMNodeList && !empty($mtitles)) {
            foreach ($mtitles as $mtitle) {
                $title = $mtitle->nodeValue;
                break;
            }
        }

        if ($context_texts instanceof DOMNodeList && !empty($context_texts)) {
            foreach ($context_texts as $context_text) {
                if ($context_text->nodeName === 'div') {
                    if (empty($title)) {
                        $title = mb_substr($context_text->nodeValue, 0, mb_strpos($context_text->nodeValue, "\n", 0, "UTF-8"), "UTF-8");
                    }

                    $raw_content = $context_text->nodeValue;
                    break;
                }
            }
        }

        $document['title'] = trim($title);
        $document['ctime'] = $document['mtime'] = Utils::microTime();
        $document['content'] = $raw_content;
        $document['craw_url'] = $DocInfo->url;

        $summary = $this->parseSummary($summary);

        if (is_array($summary) && !empty($summary)) {
            $document = array_merge($document, $summary);
        }

        // If exists, drop it begin
        $document['id'] = ContentHelper::GenContentMd5($document['content']);

        $urlmd5 = md5($document['craw_url']);

        if (DaoSpiderlLawBase::getInstance()->ifContentExists($urlmd5, $document['id'])) {
            echo "data exsits: urlmd5-{$urlmd5}, doc_id-{$document['id']}, " . $DocInfo->url;
            return true;
        }

        $res = FlaskRestClient::GetInstance()->simHash($document['content']);

        $simhash = '';
        if (isset($res['simhash']) && !empty($res['simhash'])) {
            $simhash = $res['simhash'];
        }

        if (isset($res['repeated']) && !empty($res['repeated'])) {
            echo 'data repeated: ' . $DocInfo->url . PHP_EOL;
            return true;
        }

        // If exists end

        $data = array(
            'doc_id'    => $document['id'],
            'type'  => DaoSpiderlLawBase::TYPE_JSON,
            'url'   => $document['craw_url'],
            'url_md5'   => $urlmd5,
            'content'   => json_encode($document, JSON_UNESCAPED_UNICODE),
            'simhash'   => $simhash,
        );

        DaoSpiderlLawBase::getInstance()->insert($data);
        return true;
    }

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

    private function parseSummary($text)
    {
        $txts = explode("\n", $text);

        $needles = array(
            "doc_ori_no"  => "发布文号",
            "publish_time"     => "发布日期",
            "t_valid"    => "生效日期",
            "t_invalid"  => "失效日期",
            "tags"      => "所属类别",
            "author"    => "文件来源",
        );

        $summary = array();

        foreach ($txts as $txt) {
            $txt = trim($txt);
            if (!empty($txt)) {
                foreach ($needles as $key => $needle) {
                    $p = mb_strpos($txt, $needle, 0, "UTF-8");
                    if ($p !== false && $p >= 0) {
                        $summary[$key] = trim(mb_ereg_replace("【.*】", "", $txt));
                        break;
                    }
                }
            }
        }

        if (!empty($summary['publish_time'])) {
            $summary['publish_time'] = strtotime($summary['publish_time']);
        }

        if (!empty($summary['t_valid'])) {
            $summary['t_valid'] = strtotime($summary['t_valid']);
        }

        if (!empty($summary['t_invalid'])) {
            $summary['t_invalid'] = strtotime($summary['t_invalid']);
        }

        return $summary;
    }

    public function addStartingUrls($url)
    {
        $this->starting_urls[] = $url;
    }
}

$pid = posix_getpid();

file_put_contents('spider_' . SpiderChinaCourt::MAGIC . '.pid', $pid);
gsettings()->debug = false;

gsettings()->url_cache_type = URL_CACHE_IN_MEMORY;
gsettings()->enable_resume = false;
gsettings()->number_of_process = 1;


$spider = new SpiderChinaCourt();
$spider->setFeed(SpiderChinaCourt::$SeedConf[0]);

for ($i=1; $i < count(SpiderChinaCourt::$SeedConf); $i++) {
    $spider->addStartingUrls(SpiderChinaCourt::$SeedConf[$i]);
}

$spider->run();