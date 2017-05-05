<?php

/**
 * 华税律师博客
 * http://blog.sina.com.cn/hwuasonlawyers
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/4
 * Time: PM6:01
 */
define("CRAWLER_NAME", "spider-blog.sina.com.cn/hwuasonlawyers");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderHwuasonLawlers extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://blog.sina.com.cn/hwuasonlawyers",
    );

    protected $ContentHandlers = array(
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );
}