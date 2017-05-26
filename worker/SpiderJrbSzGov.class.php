<?php

/**
 * 深圳金融办
 * http://www.sz.gov.cn/jrb/sjrb/tzgg/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/2
 * Time: PM2:01
 */
define("CRAWLER_NAME", "spider-jrbsz.gov");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderJrbSzGov extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.sz.gov.cn/jrb/sjrb/jrjg/",
        "http://www.sz.gov.cn/jrb/sjrb/zwgk/zcfg/jrfzzc/index.htm",
        "http://www.sz.gov.cn/jrb/sjrb/tzgg/index.htm",
    );

    protected $ContentHandlers = array(
        "#http://www\.sz\.gov\.cn/jrb/sjrb/zwgk/zcfg/jrfzzc/index([0-9_]+)?\.htm# i" => "handleListPage",
        "#http://www\.sz\.gov\.cn/jrb/sjrb/tzgg/index([0-9_]+)?\.htm# i" => "handleListPage",
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.htm# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
        "#http://www\.sz\.gov\.cn/jrb/sjrb/jrjg/# i"    => "handleListPage",
    );

    /**
     * SpiderJrbSzGov constructor.
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

        DaoUrlCache::getInstance()->pergeCacheByIds($ids);
        if (gsettings()->debug) {
            var_dump($ids);
            exit(0);
        }
    }

    /**
     * @param $url
     * @param $_nPageCount
     * @param $_nCurrIndex
     * @param $_sPageName
     * @param $_sPageExt
     * @return array
     */
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
     * @return bool|XlegalLawContentRecord
     */
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $extract->parse();

        $content = $extract->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($extract->title) ? $extract->title : $extract->guessTitle();
        $record->author = $extract->author;
        $record->content = $content;
        $record->doc_ori_no = $extract->doc_ori_no;
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

        $doc = $extract->extractor->document();
        if (empty($record->publish_time)) {
            $biaoti_s = $doc->query("//span[@class='biaoti_s']");
            if (!empty($biaoti_s) && $biaoti_s instanceof DOMNodeList) {
                foreach ($biaoti_s as $biaoti_) {
                    preg_match("#([0-9]{4})-([0-9]{2})-([0-9]{2})# i", $biaoti_->nodeValue, $matches);
                    if (!empty($matches) && count($matches) > 3) {
                        $record->publish_time = strtotime(sprintf("%s-%s-%s", $matches[1], $matches[2], $matches[3]));
                        break;
                    }
                }
            }
        }

        $biaoti_b = $doc->query("//span[@class='biaoti_b']");

        if (!empty($biaoti_b) && $biaoti_b instanceof DOMNodeList) {
            !empty($biaoti_b->item(0)->nodeValue) ? $record->title = $biaoti_b->item(0)->nodeValue : null;
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