<?php

/**
 * 国家开发银行
 * http://www.cdb.com.cn/xwzx/xxgg/fzgg/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/10
 * Time: PM4:46
 */
define("CRAWLER_NAME", "spider-cdb.com.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCdbComCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.cdb.com.cn/xwzx/xxgg/fzgg/",
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"    => "handleDetailPage",
        "#http://www\.cdb\.com\.cn/xwzx/xxgg/fzgg/(index_[0-9]+\.html)?# i" => "handleListPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment"
    );


    protected $author = '国家开发银行';
    protected $tag = '行业规定';

    /**
     * SpiderCdbComCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_pergecache();
    }

    /**
     * @param $_nPageCount
     * @param int $_nCurrIndex
     * @return string
     */
    protected function createPageHTML($_nPageCount, $_nCurrIndex = 0)
    {
        if(empty($_nPageCount) || $_nPageCount <= 1){
            return "";
        }

        $nextPage = $_nCurrIndex + 1;

        return "index" . "_" . $nextPage . "." . "html";
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

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        preg_match("#createPageHTML\(\"([0-9]+)\", \"([0-9]+)\"\);# i", $DocInfo->source, $matches);

        $pages = array();

        if (!empty($matches) && count($matches) > 2) {
            $next_page = $this->createPageHTML($matches[1], $matches[2]);
            $pages[] = Formatter::formaturl($DocInfo->url, $next_page);
        }

        return $pages;
    }
}