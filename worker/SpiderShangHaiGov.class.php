<?php

/**
 * 上海政府网
 * http://www.shanghai.gov.cn/nw2/nw2314/nw2319/nw12344/index.html
 * User: xlegal
 * Date: 17/4/22
 * Time: PM11:22
 */
define("CRAWLER_NAME", "spider-shanghai.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderShangHaiGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.shanghai.gov.cn/nw2/nw2314/nw2319/nw41149/nw41150/index.html",
        "http://www.shanghai.gov.cn/nw2/nw2314/nw2319/nw12344/index.html"
    );

    protected $ContentHandlers = array(
        "#http://service\.shanghai\.gov\.cn/pagemore/iframePagerIndex1\.aspx\?page=[0-9]+# i",
        "#http://www\.shanghai\.gov\.cn/nw2/nw2314/nw2319/nw12344/index[0-9]+\.html# i" => "handleListPage",
        "#/u[0-9]+aw[0-9]+\.html# i" => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderNdrcGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}