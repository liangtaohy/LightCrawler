<?php

/**
 * 公安部
 * http://www.mps.gov.cn/n2254492/n2254528/index.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/25
 * Time: PM6:24
 */
define("CRAWLER_NAME", "spider-mps.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderMpsGov extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.sda.gov.cn/WS01/CL0463/",
    );

    protected $ContentHandlers = array(
        "#http://www.sda.gov.cn/WS01/CL0463/# i"    => "handleListPage",
        "#http://www.sda.gov.cn/wbpp/generalsearch# i"  => "handleListPage",
        "#/CL[0-9]+/[0-9]+\.html# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderMpsGov constructor.
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

    //document.cookie="maxPageNum3497341=4"
    public function computePages(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pagesPatterns = array(
            "#document.cookie=\"maxPageNum[0-9]+=([0-9]+?)\"# i",
        );

        $pages = 0;

        foreach ($pagesPatterns as $pagesPattern) {
            $result = preg_match($pagesPattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $pages = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        $res = array();
        $res['pages'] = $pages;

        return $res;
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pager = $this->computePages($DocInfo);
        $sPageName = "index";
        $sPageExt = "html";

        $p = strrpos($DocInfo->url, "/");
        $prefix = substr($DocInfo->url, 0, $p + 1);

        $pages = array();
        for ($i = 1; $i <= $pager['pages']; $i++)
        {
            if($i == 1){
                $url = $sPageName . "." . $sPageExt;
            }else{
                $url = $sPageName . "_" . ($i-1) . "." . $sPageExt;
            }
            $pages[] = $prefix . $url;
        }

        return $pages;
    }
}