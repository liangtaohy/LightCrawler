<?php

/**
 * 最高检
 * http://www.spp.gov.cn/flfg/gfwj/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/19
 * Time: PM2:13
 */
define("CRAWLER_NAME", "spider-spp.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderSppGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.spp.gov.cn/flfg/gfwj/",
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#http://www\.spp\.gov\.cn/(flfg|tzgg1)/(sfjs|gfwj|nbgz)/index([_0-9]+)?\.shtml# i" => "handleListPage",
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.shtml# i" => "handleDetailPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderSppGovCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function createPageHTML($url, $_nPageCount, $_nCurrIndex, $_sPageName, $_sPageExt)
    {
        if(empty($_nPageCount) || $_nPageCount<=1){
            return false;
        }

        if($_nCurrIndex<$_nPageCount-1)
        {
            return Formatter::formaturl($url, $_sPageName . "_" . ($_nCurrIndex+1) . "." . $_sPageExt);
        }

        return false;
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pages = array();
        $patterns = array(
            chr(13),
        );

        $replaces = array(
            "\n",
        );

        $source = str_replace($patterns, $replaces, $DocInfo->source);

        $lines = explode("\n", $source);
        foreach ($lines as $line) {
            preg_match("#createPageHTML\(([0-9]+), ([0-9]+), \"([a-zA-Z0-9_]+)\", \"([a-z]+)\"\);# i", $line, $matches);
            if (!empty($matches) && count($matches) > 4) {
                $page = $this->createPageHTML($DocInfo->url, $matches[1], $matches[2], $matches[3], $matches[4]);
                if (!empty($page)) {
                    $pages[] = $page;
                }
            }
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }

        return $pages;
    }
}