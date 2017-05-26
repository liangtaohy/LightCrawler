<?php

/**
 * 中国证券业协会
 * http://www.sac.net.cn/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/10
 * Time: PM6:28
 */
define("CRAWLER_NAME", "spider-sac.net.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderSacNetCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.sac.net.cn/tzgg/index.html",
        "http://www.sac.net.cn/flgz/zlgz/index.html"
    );

    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"  => "handleDetailPage",
        "#http://www\.sac\.net\.cn/tzgg/index([_0-9]+)\.html# i"   => "handleListPage",
        "#http://www\.sac\.net\.cn/flgz/zlgz/index([_0-9]+)\.html# i"   => "handleListPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    protected $author = "中国证券业协会";
    protected $tag = "行业规定";

    /**
     * SpiderSacNetCn constructor.
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
}