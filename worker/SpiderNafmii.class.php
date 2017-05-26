<?php

/**
 * 中国银行间市场交易商协会
 * http://www.nafmii.org.cn/
 * User: xlegal
 * Date: 17/5/10
 * Time: PM4:18
 */
define("CRAWLER_NAME", "spider-nafmii.org.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderNafmii extends SpiderFrame
{
    //http://www.nafmii.org.cn/ggtz/
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.nafmii.org.cn/ggtz/index.html",
    );

    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"  => "handleDetailPage",
        "#http://www\.nafmii\.org\.cn/(ggtz|zlgz)/index([0-9_]+)?\.html# i"    => "handleListPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderNafmii constructor.
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
     * @param $countPage
     * @param $currentPage
     * @param $pageName
     * @param $pageExt
     * @return string
     */
    protected function createPageHTML($countPage, $currentPage, $pageName, $pageExt)
    {
        $nextPage = $currentPage + 1;
        if ($countPage > 1 && $currentPage != ($countPage - 1)) {
            return $pageName . "_" . $nextPage . "." . $pageExt;
        }

        return "";
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        preg_match("#createPageHTML\(([0-9]+), ([0-9]+), \"([a-z]+)\", \"([a-z]+)\"\);# i", $DocInfo->source, $matches);

        $pages = array();

        if (!empty($matches) && count($matches) > 4) {
            $next_page = $this->createPageHTML($matches[1], $matches[2], $matches[3], $matches[4]);
            $pages[] = Formatter::formaturl($DocInfo->url, $next_page);
        }

        return $pages;
    }
}