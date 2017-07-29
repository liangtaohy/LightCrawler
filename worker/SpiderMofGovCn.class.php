<?php

/**
 * 财政部
 * http://www.mof.gov.cn/zhengwuxinxi/zhengcefabu/index_1.htm
 *
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/6/8
 * Time: AM9:16
 */
define("CRAWLER_NAME", "spider-mof.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderMofGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.mof.gov.cn/zhengwuxinxi/zhengcefabu/index.htm",
        "http://www.mof.gov.cn/zhengwuxinxi/bulinggonggao/tongzhitonggao/index.htm",
    );

    protected $ContentHandlers = array(
        "#http://www\.mof\.gov\.cn/zhengwuxinxi/zhengcefabu/index([_0-9]+)?\.htm# i" => "handleListPage",
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"  => "handleDetailPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderMofcomGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_pergecache();
    }

    protected function _pergecache()
    {
        //DaoUrlCache::getInstance()->cleanup(CRAWLER_NAME);
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pages = array();
        $url = $DocInfo->url;

        $pos = strpos($url, "index.htm");

        if ($pos > 0) {
            $prefix = substr($url, 0, $pos);
            for($i=1;$i<=23;$i++) {
                $pages[] = $prefix . "index_" . $i . ".htm";
            }
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }

        return $pages;
    }
}