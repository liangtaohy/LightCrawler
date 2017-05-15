<?php

/**
 * 保监会
 * http://www.circ.gov.cn/web/site0/tab5176/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/14
 * Time: PM8:39
 */
define("CRAWLER_NAME", "spider-circ.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCircGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.circ.gov.cn/web/site0/tab5176/",
        "http://www.circ.gov.cn/web/site0/tab5178/",
        "http://www.circ.gov.cn/web/site0/tab5240/module14430/page1.htm",
        "http://www.circ.gov.cn/web/site0/tab5241/module14458/page1.htm",
        "http://www.circ.gov.cn/web/site0/tab7765/module27147/page1.htm",
        "http://www.circ.gov.cn/web/site0/tab7765/module27149/page2.htm",
        "http://www.circ.gov.cn/web/site0/tab7765/module27151/page2.htm",
        "http://www.circ.gov.cn/tabid/5272/Default.aspx?type=mulu",
    );

    protected $ContentHandlers = array(
        "#http://www.circ.gov.cn/web/site0/tab[0-9]+/$# i" => "void",
        "#http://www.circ.gov.cn/web/site0/tab[0-9]+/module[0-9]+/page[0-9]+\.htm# i"   => "void",
        "#http://www.circ.gov.cn/web/site0/tab[0-9]+/info[0-9]+\.htm# i"  => "handleDetailPage",
        "#http://www.circ.gov.cn/tabid/[0-9]+/InfoID/[0-9]+/Default\.aspx\?type=Apply# i"    => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderCircGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}