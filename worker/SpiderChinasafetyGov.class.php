<?php

/**
 * 安全生产监督局
 * http://zfxxgk.chinasafety.gov.cn/portal/source.do?method=getgongkaimuluSelectList&pageSize=30
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/28
 * Time: PM1:40
 */
define("CRAWLER_NAME", "spider-chinasafety.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderChinasafetyGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://zfxxgk.chinasafety.gov.cn/portal/main.do?method=toPage&pageId=1000",
    );

    protected $ContentHandlers = array(
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderChinasafetyGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}