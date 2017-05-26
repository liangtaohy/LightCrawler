<?php

/**
 * 北京市发展改革委
 * http://www.bjpc.gov.cn/zwxx/ztzl/yfxz/xzxk_sgs/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/5
 * Time: PM5:06
 */
define("CRAWLER_NAME", "spider-bjpc.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderBjpcGov extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.bjpc.gov.cn/zwxx/ztzl/yfxz/xzxk_sgs/",
    );

    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9_]+\.htm# i"   => "handleDetailPage",
        "#http://www.bjpc.gov.cn/zwxx/ztzl/yfxz/[a-z]+_sgs/# i" => "handleListPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderBjpcGov constructor.
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

        DaoUrlCache::getInstance()->pergeCacheByIds($ids);
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $currentPage = 0;//所在页从0开始
        $countPage = 0;//共多少页
        preg_match("#var currentPage = ([0-9]+);# i", $DocInfo->source, $matches);
        if (!empty($matches) && count($matches) > 1) {
            $currentPage = intval($matches[1]);
        }

        unset($matches);
        preg_match("#var countPage = ([0-9]+)# i", $DocInfo->source, $matches);
        if (!empty($matches) && count($matches) > 1) {
            $countPage = intval($matches[1]);
        }

        $nextPage = $currentPage+1;//下一页

        $pages = array();

        if($countPage>1&&$currentPage!=($countPage-1)) {
            if ($DocInfo->url[strlen($DocInfo->url) - 1] == '/') {
                $pages[] = $DocInfo->url . "index" . "_" . $nextPage . "." . "htm";
            }
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }

        return $pages;
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $blocks = array();
        $root = $extract->getExtractor()->extractor->domDocument()->getElementsByTagName('body')->item(0);
        $extract->linkBlocks($root, $blocks)->deleteNodes($blocks);
        $extract->parse();

        $doc = $extract->extractor->document();
        $title = trim($doc->query("//h1[@class='dbox1']")->item(0)->nodeValue);
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

        if (!empty($title)) {
            $record->title = $title;
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