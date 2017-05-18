<?php

/**
 * 烟草专卖总局
 * http://www.tobacco.gov.cn/html/27/2701.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/18
 * Time: PM2:21
 */
define("CRAWLER_NAME", "spider-tobacco.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderTobaccoGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.tobacco.gov.cn/html/27/2701.html",
        "http://www.tobacco.gov.cn/html/27/2703.html",
    );

    protected $ContentHandlers = array(
        "#http://www\.tobacco\.gov\.cn/html/27/270[0-9]\.html# i"  => "handleListPage",
        "#http://www\.tobacco\.gov\.cn/html/27/270[0-9]/[0-9]+(_[0-9]+)?\.html# i"  => "handleListPage",
        "#http://www\.tobacco\.gov\.cn/html/27/270[0-9]/([0-9]+/)?[0-9]+_n\.html# i"   => "handleDetailPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderTobaccoGovCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}