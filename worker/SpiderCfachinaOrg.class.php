<?php

/**
 * 中国期货业协会
 * http://www.cfachina.org/ZCFG/ZLGZ/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/10
 * Time: PM5:47
 */
define("CRAWLER_NAME", "spider-cfachina.org");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCfachinaOrg extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.cfachina.org/ZCFG/ZLGZ/index.html"
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#http://www\.cfachina\.org/ZCFG/ZLGZ/index([0-9_]+)\.html# i"  => "handleListPage",
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"    => "handleDetailPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment"
    );

    /**
     * @var string
     */
    protected $author = "中国期货业协会";

    /**
     * @var string
     */
    protected $tag = "行业规定";

    /**
     * SpiderCfachinaOrg constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_pergecache();
    }

    protected function _pergecache()
    {
        $page = 1;
        $pagesize = 10000;

        $where = array(
            "spider"    => md5(CRAWLER_NAME),
            "processed" => 1,
            "in_process"    => 0,
        );

        $sort = array(
            "id" => "ASC"
        );

        $fields = array(
            "id",
            "url_rebuild",
            "distinct_hash",
        );

        $res = $url_cache = DaoUrlCache::getInstance()->search_data($where, $sort, $page, $pagesize, $fields);

        $pages = $res['pages'];

        $lists = array();
        foreach ($res['data'] as $re) {
            $url = $re['url_rebuild'];
            foreach ($this->ContentHandlers as $pattern => $contentHandler) {
                if ($contentHandler === "handleListPage" || $contentHandler === "void") {
                    if (preg_match($pattern, $url)) {
                        if (!isset($lists[$pattern])) {
                            $lists[$pattern] = array();
                        }

                        $lists[$pattern][] = $re;
                    }
                }
            }
        }

        $ids = array();
        foreach ($lists as $pattern => $list) {
            $total = ceil(count($list) / 3);
            if ($total > self::MAX_PAGE) {
                $total = self::MAX_PAGE;
            }

            for ($i = 0; $i < $total; $i++) {
                $u = $list[$i];
                $ids[] = $u['id'];
            }
        }

        if (gsettings()->debug) {
            var_dump($ids);
            exit(0);
        }
        DaoUrlCache::getInstance()->pergeCacheByIds($ids);
    }

    /**
     * @param $currentPage
     * @param $countPage
     * @return string
     */
    protected function createHtmlPage($currentPage,$countPage)
    {
        $nextPage = $currentPage + 1;
        if($countPage>1&&$currentPage!=($countPage-1))
            return "index"."_" . $nextPage . "."."html";
        return "";
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $currentPage = 0;
        $countPage = 0;
        preg_match("#var currentPage = ([0-9]+);# i", $DocInfo->source, $currentPages);
        if (!empty($currentPages) && count($currentPages)>1) {
            $currentPage = intval($currentPages[1]);
        }
        preg_match("#var countPage = ([0-9]+)# i", $DocInfo->source, $countPages);
        if (!empty($countPages) && count($countPages) > 1) {
            $countPage = intval($countPages[1]);
        }

        $pages = array();

        $href = $this->createHtmlPage($currentPage, $countPage);
        $pages[] = Formatter::formaturl($DocInfo->url, $href);

        return $pages;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return XlegalLawContentRecord
     */
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        preg_match("#file_appendix='<a href=\"(\./[A-Z0-9]+\.doc|\./[A-Z0-9]+\.docx|\./[A-Z0-9]+\.pdf)\">(.*)?</a># i", $DocInfo->source, $attachments);
        $attachemnt = array();
        if (!empty($attachments) && count($attachments) > 2) {
            $attachemnt['title'] = $attachments[2];
            $attachemnt['url']  = Formatter::formaturl($DocInfo->url, $attachments[1]);
        }

        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $document = $extract->getExtractor()->extractor->domDocument();
        $h1 = $document->getElementsByTagName("h1");
        $title = '';
        if (!empty($h1) && $h1 instanceof DOMNodeList) {
            $title = trim($h1->item(0)->nodeValue);
        }
        $h2 = $document->getElementsByTagName("h2");
        $publish_time = 0;
        if (!empty($h2) && $h2 instanceof DOMNodeList) {
            preg_match("/([0-9]{4})\x{5E74}([0-9]{2})\x{6708}([0-9]{2})\x{65E5}/u", trim($h2->item(0)->nodeValue), $matches);
            if (!empty($matches) && count($matches) > 3) {
                $publish_time = strtotime(sprintf("%s-%s-%s", $matches[1], $matches[2], $matches[3]));
            }
        }
        $extract->parse();

        $content = $extract->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($title) ? $title : (!empty($extract->title) ? $extract->title : $extract->guessTitle());
        $record->author = $extract->author;
        $record->content = $content;
        $record->doc_ori_no = $extract->doc_ori_no;
        $record->publish_time = !empty($publish_time) ? $publish_time : $extract->publish_time;
        $record->t_valid = $extract->t_valid;
        $record->t_invalid = $extract->t_invalid;
        $record->negs = implode(",", $extract->negs);
        $record->tags = $extract->tags;
        $record->simhash = '';
        if (!empty($attachemnt)) {
            $extract->attachments[] = $attachemnt;
        }
        if (!empty($extract->attachments)) {
            $record->attachment = json_encode($extract->attachments, JSON_UNESCAPED_UNICODE);
        }

        if (empty(gsettings()->debug)) {
            $res = FlaskRestClient::GetInstance()->simHash($c);

            $simhash = '';
            if (isset($res['simhash']) && !empty($res['simhash'])) {
                $simhash = $res['simhash'];
            }

            if (isset($res['repeated']) && !empty($res['repeated'])) {
                echo 'data repeated: ' . $DocInfo->url . ', repeated simhash: ' . $res['simhash1'] .PHP_EOL;
                $flag = 1;
                if (!empty($record->doc_ori_no)) {
                    $r = DaoXlegalLawContentRecord::getInstance()->ifDocOriExisted($record);
                    if (empty($r)) {
                        $flag = 0;
                    }
                }

                if ($flag)
                    return false;
            }

            $record->simhash = $simhash;
        }


        if (!empty($this->author)) {
            $record->author = $this->author;
        }

        if (!empty($this->tag)) {
            $record->tags = $this->tag;
        }

        $record->type = DaoSpiderlLawBase::TYPE_TXT;
        $record->status = 1;
        $record->url = $extract->baseurl;
        $record->url_md5 = md5($extract->url);

        if (gsettings()->debug) {
            //echo "raw: " . implode("\n", $extract->text) . PHP_EOL;
            //$index_blocks = $extract->indexBlock($extract->text);
            //echo implode("\n", $index_blocks) . PHP_EOL;
            var_dump($record);
            exit(0);
        }
        echo "insert data: " . $record->doc_id . PHP_EOL;
        return $record;
    }
}