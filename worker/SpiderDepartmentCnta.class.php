<?php

/**
 * 中华人民共和国旅游局
 * http://www.cnta.gov.cn/zwgk/fgwj/gfxwj_2120/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/7
 * Time: PM7:19
 */
define("CRAWLER_NAME", "spider-cnta.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderDepartmentCnta extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.cnta.gov.cn/zwgk/fgwj/gfxwj_2120/",
    );

    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.shtml# i"  => "handleDetailPage",
        "#http://www.cnta.gov.cn/zwgk/fgwj/[a-z0-9_A-Z]+\/(index.*\.shtml)?$# i"   => "void",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderDepartmentCnta constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * pager computer
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array|bool
     */
    public function computePages(PHPCrawlerDocumentInfo $DocInfo)
    {
        $totalPatterns = array(
            "#var countPage = ([0-9]+)# i",
            "#var countPage=([0-9]+);# i"
        );

        $pagesizePatterns = array();

        $total = 0;
        $pagesize = 0;

        foreach ($totalPatterns as $totalPattern) {
            $result = preg_match($totalPattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $total = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        if (empty($total)) {
            echo "FATAL get total page failed: " . $DocInfo->url . PHP_EOL;
            return true;
        }

        unset($result);
        unset($matches);

        foreach ($pagesizePatterns as $pagesizePattern) {
            $result = preg_match($pagesizePattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $pagesize = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        if (empty($pagesize)) {
            echo "FATAL get pagesize failed: " . $DocInfo->url . PHP_EOL;
            return array(
                'total' => $total,
            );
        }

        $res = array(
            'total' => $total,
        );
        $total = intval($total / $pagesize);
        $res['pages'] = $total;
        $res['pagesize'] = $pagesize;

        return $res;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $sPageExt = $sPageName = '';
        $countPage = 0;
        $currentPage = 0;

        preg_match("#var currentPage=([0-9]+);# i", $DocInfo->source, $currentPages);
        if (!empty($currentPages) && count($currentPages) > 1) {
            $currentPage = intval($currentPages[1]);
        }
        $nextPage = $currentPage + 1;
        preg_match("#var countPage=([0-9]+);# i", $DocInfo->source, $countPages);
        if (!empty($countPage) && count($countPages) > 1) {
            $countPage = intval($countPages[1]);
        }

        preg_match('#"<a class=\'tow\' href=\"([0-9]+)"\+"_"\+nextPage+"\."\+"([0-9]+)\" target=\"_self\"# i', $DocInfo->source, $matches);
        if (!empty($matches) && count($matches) > 2) {
            $sPageName = $matches[1];
            $sPageExt = $matches[2];
        }

        $pages = array();
        if($countPage > 1 && $currentPage != ($countPage - 1)) {
            $path = $sPageName . "_" . $nextPage . "." . $sPageExt;
            $pages[] = Formatter::formaturl($DocInfo->url, $path);
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }

        return $pages;
    }
}