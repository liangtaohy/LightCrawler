<?php

/**
 * 国家知识产权局
 * http://www.sipo.gov.cn/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/3
 * Time: AM10:22
 */
define("CRAWLER_NAME", md5("spider-sipo.gov"));
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderSipoGov extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.sipo.gov.cn/",
        "http://www.sipo.gov.cn/gwywj",
        "http://www.sipo.gov.cn/tz",
        "http://www.sipo.gov.cn/zscqgz",
        "http://www.sipo.gov.cn/zwgg/jl/",
        "http://www.sipo.gov.cn/zwgg/gg/",
    );

    protected $ContentHandlers = array(
        "#/[0-9]+/t[0-9]+_[0-9]+\.html# i"  => "handleDetailPage",
        "#http://www.sipo.gov.cn/(gwywj|tz|zscqgz|zwgg/jl/|zwgg/gg/)# i"  => "handleListPage",
        "/\/[\x{4e00}-\x{9fa5}0-9a-zA-Z_\x{3010}\x{3011}\x{FF08}\x{FF09}\]\[]+\.(doc|pdf|txt|xls|ceb)/ui" => "handleAttachment",
    );

    /**
     * SpiderSipoGov constructor.
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

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $currentPage = 0;//所在页从0开始
        $countPage = 0;//共多少页
        preg_match("#var currentPage = ([0-9]+);# i", $DocInfo->source, $matches);
        if (!empty($matches) && count($matches) > 1) {
            $currentPage = intval($matches[1]);
        }

        unset($matches);
        preg_match("#var countPage = ([0-9]+)# i", $DocInfo->source, $matches);
        if (!empty($matches) && count($matches) > 1) {
            $countPage = intval($matches[1]);
        }

        $nextPage = $currentPage+1;//下一页

        $pages = array();

        if($countPage>1&&$currentPage!=($countPage-1)) {
            if ($DocInfo->url[strlen($DocInfo->url) - 1] == '/') {
                $pages[] = $DocInfo->url . "index" . "_" . $nextPage . "." . "html";
            } else {
                $pages[] = Formatter::formaturl($DocInfo->url, "index" . "_" . $nextPage . "." . "html");
            }
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }

        return $pages;
    }
}