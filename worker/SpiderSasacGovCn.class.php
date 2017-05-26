<?php

/**
 * 国资委
 * http://www.sasac.gov.cn/n85881/n85921/index.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/18
 * Time: PM6:16
 */
define("CRAWLER_NAME", "spider-sasac.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderSasacGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.sasac.gov.cn/n85881/n85921/index.html"
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#http://www\.sasac\.gov\.cn/n[0-9]+/n[0-9]+/index_[0-9]+_[0-9]+\.html# i"  => "dumpNull",
        "#http://www\.sasac\.gov\.cn/n[0-9]+/n[0-9]+/c[0-9]+/content\.html# i"   => "handleDetailPage",
        "#http://www\.sasac\.gov\.cn/n85881/n85921/index.html# i"  => "handleListPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderSasacGovCn constructor.
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
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @param $purl
     * @param $pName
     * @return array
     */
    protected function createUrl(PHPCrawlerDocumentInfo $DocInfo, $purl, $pName)
    {
        $u=$DocInfo->url;
        $index1=stripos($u, "/manageweb");
        $index2 = stripos($u, "/serviceweb");

        $purl = Formatter::formaturl($DocInfo->url, $purl);

        var_dump($purl);
        $pages = array();
        for ($i = 2; $i <= $pName; $i++) {
            if($index1 !== false) {
                $uu=$purl . "&pageName=" . $i;
                $pages[] = $uu;
                continue;
            }
            if ($index2 !== false) {
                $uu = $purl . "_" . $i . ".html";
                $pages[] = $uu;
            } else {
                $uu = $purl . "_" . $i . ".html";
                $pages[] = $uu;
            }
        }

        return $pages;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        preg_match("#document\.cookie=\"maxPageNum[0-9]+=([0-9]+)\";# i", $DocInfo->source, $matches);

        preg_match("#var purl=\"(.*)\"# i", $DocInfo->source, $m_purl);

        var_dump($matches);
        var_dump($m_purl);
        $purl = "";

        $pages = array();

        if (!empty($m_purl) && count($m_purl) > 1) {
            $purl = trim($m_purl[1]);
        }

        if (!empty($purl)) {
            if (!empty($matches) && count($matches) > 1) {
                $maxPageNum = intval($matches[1]);
                $pages = $this->createUrl($DocInfo, $purl, $maxPageNum);
            }
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }

        return $pages;
    }
}