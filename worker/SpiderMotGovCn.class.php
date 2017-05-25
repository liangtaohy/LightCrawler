<?php

/**
 * 交通运输部
 * http://zizhan.mot.gov.cn/zfxxgk/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/23
 * Time: PM7:25
 */
define("CRAWLER_NAME", "spider-mot.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderMotGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    const MAX_PAGE = 10; // if max page == -1, just for all pages

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://was.mot.gov.cn:8080/govsearch/searPage.jsp?page=1&pubwebsite=zfxxgk&indexPa=2&schn=252&sinfo=252&surl=zfxxgk/&curpos=%E4%B8%BB%E9%A2%98%E5%88%86%E7%B1%BB",
    );

    protected $ContentHandlers = array(
        "#http://zizhan\.mot\.gov\.cn/zfxxgk/bnssj/[a-z]+/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i" => "handleDetailPage",
        "#http://was\.mot\.gov\.cn:8080/govsearch/searPage\.jsp# i" => "handleListPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderMotGovCn constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_pergecache();
    }

    protected function _pergecache()
    {
        $page = 1;
        $pagesize = 100;

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

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        if (gsettings()->debug) {
            file_put_contents("dump.html", $DocInfo->source);
        }

        $m_nRecordCount = 0;
        $m_nPageSize = 0;
        $m_nCurrPage = 0;

        preg_match("#var m_nRecordCount = ([0-9]+);# i", $DocInfo->source, $recordCount);
        if (!empty($recordCount) && count($recordCount) > 1) {
            $m_nRecordCount = intval($recordCount[1]);
        }

        preg_match("#var m_nPageSize = ([0-9]+);# i", $DocInfo->source, $pageSize);
        if (!empty($pageSize) && count($pageSize) > 1) {
            $m_nPageSize = intval($pageSize[1]);
        }

        preg_match("#var m_nCurrPage = ([0-9]+);# i", $DocInfo->source, $currPage);
        if (!empty($currPage) && count($currPage) > 1) {
            $m_nCurrPage = intval($currPage[1]);
        }

        $nextPage = $m_nCurrPage + 1;

        $page = $nextPage + 1;

        $maxPage = 1;
        if ($m_nPageSize !== 0) {
            $maxPage = ceil($m_nRecordCount / $m_nPageSize);
        }

        if (self::MAX_PAGE > 0 && $maxPage > self::MAX_PAGE) {
            $maxPage = self::MAX_PAGE;
        }

        if ($page > $maxPage) {
            return array();
        }

        $p = "http://was.mot.gov.cn:8080/govsearch/searPage.jsp?page={$page}&pubwebsite=zfxxgk&indexPa=2&schn=252&sinfo=252&surl=zfxxgk/&curpos=%E4%B8%BB%E9%A2%98%E5%88%86%E7%B1%BB";

        if (gsettings()->debug) {
            echo "find new url: ";
            echo $p . PHP_EOL;
            exit(0);
        }

        return array($p);
    }
}