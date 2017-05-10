<?php

/**
 * 上海证券交易所
 * http://www.sse.com.cn/home/webupdate/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/10
 * Time: PM3:25
 */
define("CRAWLER_NAME", "spider-sse.com.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderSseComCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.sse.com.cn/lawandrules/sserules/all/",
        "http://www.sse.com.cn/home/webupdate/"
    );

    protected $ContentHandlers = array(
        "#/c/c_[0-9]+_[0-9]+\.shtml# i" => "handleDetailPage",
        "#http://www.sse.com.cn/lawandrules/sserules/all/$# i"   => "handleListPage",
        "#http://www.sse.com.cn/home/webupdate/$# i" => "handleListPage",
        "/\/[\x{4e00}-\x{9fa5}0-9a-zA-Z_\x{3010}\x{3011}\x{FF08}\x{FF09}\]\[]+\.(doc|docx|pdf|txt|xls|ceb)/ui" => "handleAttachment",
    );

    /**
     * SpiderSjrShGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}