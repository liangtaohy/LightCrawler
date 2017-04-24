<?php

/**
 * 上海政府见多
 * http://sjr.sh.gov.cn/index.html - 上海金融办公室
 * User: xlegal
 * Date: 17/4/22
 * Time: PM11:22
 */
class SpiderShangHaiGov
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.ndrc.gov.cn/zcfb/zcfbtz/index.html",
        "http://www.ndrc.gov.cn/zcfb/zcfbqt/index.html",
        "http://www.ndrc.gov.cn/xzcf/index.html",
    );

    protected $ContentHandlers = array(
        "#http://www.ndrc.gov.cn/zcfb/(zcfbtz|zcfbqt)/index([_0-9]+)?\.html# i" => "handleListPage",
        "#http://www.ndrc.gov.cn/xzcf/index([_0-9]+)?\.html# i" => "handleListPage",
        "#http://www.ndrc.gov.cn/zcfb/(zcfbqt|zcfbtz)/[0-9]+/t[0-9]+_[0-9]+\.html# i"   => "handleDetailPage",
        "#/t[0-9]+_[0-9]+\.html# i"  => "handleDetailPage",
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