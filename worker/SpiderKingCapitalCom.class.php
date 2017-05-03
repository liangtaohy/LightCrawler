<?php

/**
 * 京都律师事务所
 * http://www.king-capital.com/news/lastest-results.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/3
 * Time: PM5:13
 */
define("CRAWLER_NAME", "spider-king-capital.com");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderKingCapitalCom extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.king-capital.com/magazine/judicial-case.html",
        "http://www.king-capital.com/news/lastest-results.html",
    );

    protected $ContentHandlers = array(
        "#http://www\.king-capital\.com/magazine/judicial-case([0-9_]+)?\.html# i"    => "handleListPage",
        "#http://www\.king-capital\.com/news/Typical-case([0-9_]+)?\.html# i"    => "handleListPage",
        "#http://www\.king-capital\.com/content/details[0-9]+_[0-9]+\.html# i" => "handleDetailPage",
        "#http://www\.king-capital\.com/news/lastest-results([_0-9]+)?\.html# i" => "handleListPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderKingCapitalCom constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}