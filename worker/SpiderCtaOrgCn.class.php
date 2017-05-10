<?php

/**
 * 中国商标协会
 * http://www.cta.org.cn/dlfh/hyzl/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/10
 * Time: PM3:15
 */
define("CRAWLER_NAME", "spider-cta.org.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCtaOrgCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.cta.org.cn/dlfh/hyzl/",
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderCtaOrgCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}