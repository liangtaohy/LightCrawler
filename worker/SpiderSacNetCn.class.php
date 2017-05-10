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