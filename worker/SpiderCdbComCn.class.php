<?php

/**
 * 国家开发银行
 * http://www.cdb.com.cn/xwzx/xxgg/fzgg/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/10
 * Time: PM4:46
 */
define("CRAWLER_NAME", "spider-cdb.com.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCdbComCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.cdb.com.cn/xwzx/xxgg/fzgg/"
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"    => "handleDetailPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment"
    );


    protected $author = '国家开发银行';
    protected $tag = '行业规定';

    /**
     * SpiderCdbComCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $_nPageCount
     * @param int $_nCurrIndex
     * @return string
     */
    protected function createPageHTML($_nPageCount, $_nCurrIndex = 0)
    {
        if(empty($_nPageCount) || $_nPageCount <= 1){
            return "";
        }

        $nextPage = $_nCurrIndex + 1;

        return "index" . "_" . $nextPage . "." . "html";
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        preg_match("#createPageHTML\(\"([0-9]+)\", \"([0-9]+)\"\);# i", $DocInfo->source, $matches);

        $pages = array();

        if (!empty($matches) && count($matches) > 2) {
            $next_page = $this->createPageHTML($matches[1], $matches[2]);
            $pages[] = Formatter::formaturl($DocInfo->url, $next_page);
        }

        return $pages;
    }
}