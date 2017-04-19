<?php

/**
 * Created by PhpStorm.
 * User: xlegal
 * Date: 17/2/7
 * Time: PM5:11
 */
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class FaLvFaGuiCrawling extends PHPCrawler
{
    const MAGIC = "FaLvFaGuiCrawling";
    private $feed = "";
    public $seeds = array();
    public $seeds_file = "";
    public $storage_root = "/mnt/open-xdp/spider";

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        //"http://china.findlaw.cn/fagui/p_1/285150.html",
        'http://china.findlaw.cn/fagui/pub/4440/p_1',
        'http://china.findlaw.cn/fagui/pub/434/p_1/',
        'http://china.findlaw.cn/fagui/pub/4468/p_1/',
        'http://china.findlaw.cn/fagui/pub/280/p_1/',
        'http://china.findlaw.cn/fagui/pub/62/p_1/',
        'http://china.findlaw.cn/fagui/pub/554/p_1/',
    );

    static $ContentHandlers = array(
        "#(http://china.findlaw.cn/fagui/p_[0-9]+/[0-9]+.html)$# i"  => 'handle_detail_findlaw_fagui',
        "#(http://china.findlaw.cn/fagui/pub/[0-9]+/p_[0-9]+)$# i" => 'handle_content_findlaw_fagui',
        "#(http://china.findlaw.cn/fagui/pub/[0-9]+/p_[0-9]+)/$# i" => 'handle_content_findlaw_fagui',
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

    public function handle_detail_findlaw_fagui(PHPCrawlerDocumentInfo $DocInfo)
    {
        echo "handle_detail_findlaw_fagui enter" . PHP_EOL;

        if (!file_exists($this->storage_root)) {
            mkdir($this->storage_root, true);
        }

        if (!file_exists($this->storage_root . "/docs/findlaw/raw")) {
            mkdir($this->storage_root . "/docs/findlaw/raw", true);
        }

        if (!file_exists($this->storage_root . "/docs/findlaw/detail")) {
            mkdir($this->storage_root . "/docs/findlaw/detail", true);
        }

        $url_md5 = md5($DocInfo->url);
        $r = DaoSpiderlLawBase::getInstance()->findOneByUrlMd5($url_md5);

        if (!empty($r)) {
            return true;
        }

        file_put_contents($this->storage_root . "/docs/findlaw/raw/" . $url_md5 . ".html", $DocInfo->source);

        $patterns = array(
            chr(13),
        );

        $replaces = array(
            "\n",
        );

        $source = str_replace($patterns, $replaces, $DocInfo->source);
        $extractor = new Extractor($source, $DocInfo->url);
        $doc = $extractor->document();

        if (empty($doc)) {
            echo "content is null: " . $DocInfo->url . PHP_EOL;
            return;
        }

        $title = $doc->query("//div[@id='allPrintContent']/h1[@class='art-h1']");
        $table = $doc->query("//div[@id='allPrintContent']/div[@class='art-info-table']/table");
        $content = $doc->query("//div[@id='allPrintContent']/div[@class='art-info']");

        $doc = array();
        if ($title instanceof DOMNodeList && !empty($title)) {
            foreach ($title as $element) {
                    $doc['title'] = trim($element->nodeValue);
            }
        }

        if ($table instanceof DOMNodeList && !empty($table)) {
            foreach ($table as $element) {
                $t = trim($element->nodeValue);
                $p1 = mb_strpos($t, "颁布单位：", 0, 'UTF-8');
                $p2 = mb_strpos($t, "文号：", 0, 'UTF-8');
                $p3 = mb_strpos($t, "颁布日期：", 0, 'UTF-8');
                $p4 = mb_strpos($t, "执行日期：", 0, 'UTF-8');
                $p5 = mb_strpos($t, "时 效 性：", 0, 'UTF-8');
                $p6 = mb_strpos($t, "效力级别：", 0, 'UTF-8');
                $author = trim(mb_substr($t, 5, $p2 - 5, 'UTF-8'));
                $doc_order_no = trim(mb_substr($t, $p2 + 3, $p3 - $p2 - 3, 'UTF-8'));
                $publish_time = trim(mb_substr($t, $p3 + 5, $p4 - $p3 - 5, 'UTF-8'));
                $t_valid = trim(mb_substr($t, $p4 + 5, $p5 - $p4 - 5, 'UTF-8'));
                $tag = trim(mb_substr($t, $p6+5, null, 'UTF-8'));
                $doc['doc_ori_no'] = $doc_order_no;
                $doc['t_valid'] = !empty($t_valid) ? strtotime($t_valid) : 0;
                $doc['publish_time'] = !empty($t_valid) ? strtotime($publish_time) : 0;
                $doc['author'] = $author;
                $doc['tags'] = $tag;
            }
        }

        if ($content instanceof DOMNodeList && !empty($content)) {
            foreach ($content as $element) {
                $doc['content'] = trim($element->nodeValue);
            }
        }

        $doc['craw_url'] = $DocInfo->url;
        $doc['id'] = $url_md5;

        $content = json_encode($doc, JSON_UNESCAPED_UNICODE);
        file_put_contents($this->storage_root . "/docs/findlaw/detail/" . $doc['id'] . '.json', $content);

        $data = array(
            'doc_id'    => $doc['id'],
            'type'  => DaoSpiderlLawBase::TYPE_JSON,
            'url'   => $doc['craw_url'],
            'url_md5'   => $doc['id'],
            'content'   => json_encode($doc, JSON_UNESCAPED_UNICODE),
        );

        echo $doc['craw_url'] . " got json" . PHP_EOL;
        DaoSpiderlLawBase::getInstance()->insert($data);
    }

    public function handle_content_findlaw_fagui(PHPCrawlerDocumentInfo $DocInfo)
    {
        echo "handle_content_findlaw_fagui enter" . PHP_EOL;


        if (!file_exists($this->storage_root . "/docs/findlaw")) {
            mkdir($this->storage_root . "/docs/findlaw");
        }

        $file = 'list.txt';
        $patterns = array(
            '<BR>',
            '<br />',
            '<br>',
            '<BR />'
        );

        $replaces = array(
            "\n",
            "\n",
            "\n",
            "\n"
        );

        //$source = str_replace($patterns, $replaces, $DocInfo->source);

        $extractor = new Extractor($DocInfo->source, $DocInfo->url);
        $doc = $extractor->document();

        if (empty($doc)) {
            echo "content is null: " . $DocInfo->url . PHP_EOL;
            return;
        }

        $raw_tag = $doc->query("//div[@class='search-end-title']/div[@class='search-end-title-txt']");
        $title_urls = $doc->query("//ul[@class='aside-info-listbox-ul']/li/a");
        $paging = $doc->query("//div[@class='Paging']/a");

        $list = array();

        $tag = '';

        if ($raw_tag instanceof DOMNodeList && !empty($raw_tag)) {
            foreach ($raw_tag as $element) {
                if ($element->nodeName == 'div') {
                    $tag = trim($element->nodeValue);
                    echo "tag: " . $tag . PHP_EOL;
                    break;
                }
            }
        }

        echo 'title urls: ' . $title_urls->length . PHP_EOL;
        if ($title_urls instanceof DOMNodeList && !empty($title_urls)) {
            foreach ($title_urls as $element) {
                if ($element->nodeName == 'a') {
                    $title = trim($element->getAttribute('title'));
                    $href = trim($element->getAttribute('href'));
                    echo 'find content item: ' . $title . $tag . $href . ', referer: ' . $DocInfo->referer_url. PHP_EOL;
                    $this->addFeed($href);
                    $list[] = array($tag, $title, $href);
                }
            }
        }

        if ($paging instanceof DOMNodeList && !empty($paging)) {
            foreach ($paging as $element) {
                if ($element->nodeName === 'a') {
                    //$href = Formatter::formaturl($DocInfo->url, trim($element->getAttribute['href']));
                    $href = trim($element->getAttribute('href'));
                    $this->addFeed($href);
                    echo "find feed paging $href, referer: " . $DocInfo->referer_url . PHP_EOL;
                    file_put_contents($this->storage_root . "/docs/findlaw/" . $file, "paging\t" . $href . "\n", FILE_APPEND);
                }
            }
        }
        
        if (!empty($list)) {
            foreach ($list as $item) {
                $l = implode("\t",$item);
                file_put_contents($this->storage_root . "/docs/findlaw/" . $file, 'item\t' . $l . "\n", FILE_APPEND);
            }
        }

        //$artcontent_title = $doc->query("//div[@class='artcontent']/div[@id='allPrintContent']/h1[@class='art-h1']");
        //$art_info_table = $doc->query("//div[@class='artcontent']/div[@id='allPrintContent']/div[@class='art-info-table']");
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
        }

        return true;
    }

    private function handleDetailContent($DocInfo, &$total, &$inserted)
    {
        $document = array(
            'title' => '',
            'content'   => '',
            'summary'   => array(),
            'ctime'     => 0,
            'mtime'     => 0,
        );

        $total = 0;
        $inserted = 0;

        $patterns = array(
            '<BR>',
            '<br />',
            '<br>',
            '<BR />'
        );

        $replaces = array(
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
                    $total++;
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

        $document['summary'] = $this->parseSummary($summary);
        $document['title'] = trim($title);
        $document['ctime'] = $document['mtime'] = time();
        $document['content'] = $raw_content;
        $document['ref_url'] = $DocInfo->url;

        $doc = json_encode($document, JSON_UNESCAPED_UNICODE);

        mkdir("./docs");
        file_put_contents("./docs/" . md5($document['title']) . ".txt", $doc);
        //$this->storage->set(md5($document['title']), $doc);
    }

    private function parseSummary($text)
    {
        $txts = explode("\n", $text);

        $needles = array(
            "docOrder"  => "发布文号",
            "cTime"     => "发布日期",
            "tValid"    => "生效日期",
            "tInvalid"  => "失效日期",
            "type"      => "所属类别",
            "source"    => "文件来源",
        );

        $summary = array();

        foreach ($txts as $txt) {
            $txt = trim($txt);
            if (!empty($txt)) {
                foreach ($needles as $key => $needle) {
                    $p = mb_strpos($txt, $needle, 0, "UTF-8");
                    if ($p !== false && $p >= 0) {
                        $summary[$key] = $txt;
                        break;
                    }
                }
            }
        }

        return $summary;
    }

    public function seeds()
    {
        return $this->seeds;
    }

    private function write($urls)
    {

        $f = fopen($this->seeds_file, "a+");
        if ($f) {
            foreach($urls as $url) {
                fwrite($f, $url . "\n");
            }
        }
        fclose($f);
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
    }

    public function parseKVStorage()
    {
        // kv_db10829.db
        $f = "/Users/xlegal/Program/Work/NextLegalDev/LightCrawler/data/kv_db10829.db";

        $handle = fopen($f, "r");

        $urls = array();
        while($buf = fgets($handle, 2048)) {
            $s = trim($buf);
            $s = explode("\t", $s);

            if (isset($s[2]) && !empty($s[2])) {
                $r = json_decode($s[2]);
                echo $r . PHP_EOL;
                $urls[] = $r;
            }
        }

        return $urls;
    }

    public function run()
    {
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
}

$pid = posix_getpid();

file_put_contents('crawler.pid', $pid);

gsettings()->debug = false;

$c = new FaLvFaGuiCrawling();

foreach (FaLvFaGuiCrawling::$SeedConf as $item) {
    $c->setFeed($item);
}

$c->run();