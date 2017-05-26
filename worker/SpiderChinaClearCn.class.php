<?php

/**
 * 中国证券登记结算有限公司
 * http://www.chinaclear.cn/zdjs/xtzgg/center_flist.shtml
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/9
 * Time: PM7:36
 */
define("CRAWLER_NAME", "spider-www.chinaclear.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderChinaClearCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.chinaclear.cn/zdjs/xtzgg/center_flist.shtml"
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#http://www\.chinaclear\.cn/zdjs/xtzgg/center_flist([0-9_]+)?\.shtml# i"   => "handleListPage",
        "#/[0-9]{6}/[0-9a-z]+\.shtml# i"    => "handleDetailPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment"
    );

    /**
     * SpiderChinaClearCn constructor.
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
     * @param $url
     * @param $divName
     * @param $_nPageCount
     * @param $_nCurrIndex
     * @param $_sPageName
     * @param $_sPageExt
     * @param $_nPageSum
     * @return string
     */
    protected function createPageHTML($url, $divName, $_nPageCount, $_nCurrIndex, $_sPageName, $_sPageExt,$_nPageSum)
    {
        $nCurrIndex = $_nCurrIndex;
        $page = "";
        if($nCurrIndex < $_nPageCount){
            $page = Formatter::formaturl($url, $_sPageName . "_" . ($nCurrIndex+1) . "." . $_sPageExt);
        }
        return $page;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        preg_match("#\{createPageHTML\(\'([a-z_]+)\',([0-9]+), ([0-9]+),\'([a-z_]+)\',\'([a-z]+)\',([0-9]+)\);\}# i", $DocInfo->source, $matches);

        $pages = array();
        if (!empty($matches) && count($matches) > 6) {
            $pages[] = $this->createPageHTML($DocInfo->url, $matches[1], $matches[2], $matches[3], $matches[4], $matches[5], $matches[6]);
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }
        return $pages;
    }
}