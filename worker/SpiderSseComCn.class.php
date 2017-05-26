<?php

/**
 * 上海证券交易所
 * http://www.sse.com.cn/home/webupdate/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/10
 * Time: PM3:25
 */
define("CRAWLER_NAME", "spider-sse.com.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderSseComCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.sse.com.cn/lawandrules/sserules/all/",
        "http://www.sse.com.cn/home/webupdate/"
    );

    protected $ContentHandlers = array(
        "#/c/c_[0-9]+_[0-9]+\.shtml# i" => "handleDetailPage",
        "#http://www.sse.com.cn/lawandrules/sserules/all/$# i"   => "handleListPage",
        "#http://www.sse.com.cn/home/webupdate/$# i" => "handleListPage",
        "/\/[\x{4e00}-\x{9fa5}0-9a-zA-Z_\x{3010}\x{3011}\x{FF08}\x{FF09}\]\[]+\.(doc|docx|pdf|txt|xls|ceb)/ui" => "handleAttachment",
    );

    /**
     * SpiderSjrShGov constructor.
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
}