<?php

/**
 * 中国金融期货交易所
 * http://www.cffex.com.cn/flfg/jysgz/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/10
 * Time: PM4:07
 */
define("CRAWLER_NAME", "spider-cffex.com.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCffexComCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.cffex.com.cn/flfg/jysgz/"
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"    => "handleDetailPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment"
    );

    /**
     * SpiderCffexComCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}