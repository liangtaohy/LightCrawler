<?php

/**
 * 中国农业机械流通协会
 * http://www.camda.cn/items/10110.html
 * User: xlegal
 * Date: 17/5/11
 * Time: PM7:46
 */
define("CRAWLER_NAME", "spider-camda.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCamdaCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.camda.cn/items/10110/1.html",
        "http://www.camda.cn/items/10122/1.html",
        "http://www.camda.cn/items/10118/1.html"
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#http://www\.camda\.cn/items/(10110|10122|10118)/[0-9]+\.html# i"=> "handleListPage",
        "#http://www\.camda\.cn/html/[0-9]+\.html# i"   => "handleDetailPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment",
    );

    protected $author = "中国农业机械流通协会";
    protected $tag = "行业规定";

    /**
     * SpiderCamdaCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}