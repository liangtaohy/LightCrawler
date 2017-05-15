<?php

/**
 * 北京食品药品监督管理局
 * http://www.bjda.gov.cn/bjfda/gzdt14/tzgg/gg/index.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/5
 * Time: PM6:08
 */
define("CRAWLER_NAME", md5("spider-bjda.gov.cn"));
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderBjdaGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.bjda.gov.cn/bjfda/gzdt14/tzgg/gg/index.html",
    );

    protected $ContentHandlers = array(
        "#http://www\.bjda\.gov\.cn/bjfda/gzdt[0-9]+/tzgg/gg/index\.html# i" => "handleListPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderBjdaGovCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }
}