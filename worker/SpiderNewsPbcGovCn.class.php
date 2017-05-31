<?php

/**
 * 中国人民银行
 * http://www.pbc.gov.cn/goutongjiaoliu/113456/113469/11040/index1.html
 * User: Liang Tao
 * Date: 17/5/27
 * Time: AM10:55
 */
define("CRAWLER_NAME", "spider-news.pbc.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderNewsPbcGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.pbc.gov.cn/goutongjiaoliu/113456/113469/11040/index1.html"
    );

    protected $ContentHandlers = array(
        "#http://www\.pbc\.gov\.cn/goutongjiaoliu/113456/113469/[0-9]+/index\.html# i" => "handleDetailPage",
        "#http://www\.pbc\.gov\.cn/goutongjiaoliu/113456/113469/11040/index[0-9]+\.html# i" => "handleListPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderNewsPbcGovCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
        //$this->_pergecache();
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
}