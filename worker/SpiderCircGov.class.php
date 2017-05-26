<?php

/**
 * 保监会
 * http://www.circ.gov.cn/web/site0/tab5176/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/14
 * Time: PM8:39
 */
define("CRAWLER_NAME", "spider-circ.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCircGov extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.circ.gov.cn/web/site0/tab5176/",
        "http://www.circ.gov.cn/web/site0/tab5178/",
        "http://www.circ.gov.cn/web/site0/tab5240/module14430/page1.htm",
        "http://www.circ.gov.cn/web/site0/tab5241/module14458/page1.htm",
        "http://www.circ.gov.cn/web/site0/tab7765/module27147/page1.htm",
        "http://www.circ.gov.cn/web/site0/tab7765/module27149/page2.htm",
        "http://www.circ.gov.cn/web/site0/tab7765/module27151/page2.htm",
        "http://www.circ.gov.cn/tabid/5272/Default.aspx?type=mulu",
    );

    protected $ContentHandlers = array(
        "#http://www.circ.gov.cn/web/site0/tab[0-9]+/$# i" => "void",
        "#http://www.circ.gov.cn/web/site0/tab[0-9]+/module[0-9]+/page[0-9]+\.htm# i"   => "void",
        "#http://www.circ.gov.cn/web/site0/tab[0-9]+/info[0-9]+\.htm# i"  => "handleDetailPage",
        "#http://www.circ.gov.cn/tabid/[0-9]+/InfoID/[0-9]+/Default\.aspx\?type=Apply# i"    => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderCircGov constructor.
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
}