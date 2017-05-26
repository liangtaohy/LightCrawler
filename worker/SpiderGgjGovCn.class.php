<?php

/**
 * 国管局
 * http://www.ggj.gov.cn/zcfg/index.htm
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/18
 * Time: PM3:06
 */
define("CRAWLER_NAME", "spider-ggj.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderGgjGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.ggj.gov.cn/zcfg/index.htm",
    );

    protected $ContentHandlers = array(
        "#http://www\.ggj\.gov\.cn/zcfg/[a-z]+/[0-9]{6}/t[0-9]{8}_[0-9]+\.htm# i"   => "handleDetailPage",
        "#http://www\.ggj\.gov\.cn/zcfg/index([0-9_]+)?\.htm# i"  => "handleListPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderGgjGovCn constructor.
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

    protected function createPageHTML($url, $_nPageCount, $_nCurrIndex, $_sPageName, $_sPageExt)
    {
        if(empty($_nPageCount) || $_nPageCount<=1){
            return false;
        }

        if($_nCurrIndex<$_nPageCount-1)
        {
            return Formatter::formaturl($url, $_sPageName . "_" . ($_nCurrIndex+1) . "." . $_sPageExt);
        }

        return false;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pages = array();
        $patterns = array(
            chr(13),
        );

        $replaces = array(
            "\n",
        );

        $source = str_replace($patterns, $replaces, $DocInfo->source);

        $lines = explode("\n", $source);
        foreach ($lines as $line) {
            preg_match("#createPageHTML\(([0-9]+), ([0-9]+), \"([a-zA-Z0-9_]+)\", \"([a-z]+)\"\);# i", $line, $matches);
            if (!empty($matches) && count($matches) > 4) {
                $page = $this->createPageHTML($DocInfo->url, $matches[1], $matches[2], $matches[3], $matches[4]);
                if (!empty($page)) {
                    $pages[] = $page;
                }
            }
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }

        return $pages;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return XlegalLawContentRecord
     */
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $doc = $extract->getExtractor()->extractor->document();
        $title = trim($doc->query("//td[@class='txt18']")->item(0)->nodeValue);

        $extract->parse();

        $content = $extract->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($title) ? $title : (!empty($extract->title) ? $extract->title : $extract->guessTitle());
        $record->author = $extract->author;
        $record->content = $content;
        $record->doc_ori_no = '';
        $record->publish_time = $extract->publish_time;
        $record->t_valid = $extract->t_valid;
        $record->t_invalid = $extract->t_invalid;
        $record->negs = implode(",", $extract->negs);
        $record->tags = $extract->tags;
        $record->simhash = '';
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