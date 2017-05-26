<?php

/**
 * 汉坤
 * 法律评述
 * http://www.hankunlaw.com/newsAndInsights/lawList.html
 * User: xlegal
 * Date: 17/5/8
 * Time: PM2:45
 */
define("CRAWLER_NAME", "spider-www.hankunlaw.com");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderHankunlawCom
{
    const MAGIC = __CLASS__;

    const AJAX_POST_INFO_LIST = "http://www.hankunlaw.com/newsAndInsight/getInsightInfoList.do";

    const ATTACHMENT_PREFIX = "http://www.hankunlaw.com/downloadfile/newsAndInsights/%s.pdf";

    const MAX_PAGE = 3;
    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.hankunlaw.com/newsAndInsights/lawList.html"
    );

    protected $ContentHandlers = array(
        "#http://www\.hankunlaw\.com/newsAndInsights/newsDetail\.html\?id=[0-9a-z]# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderHankunlawCom constructor.
     */
    public function __construct()
    {
    }

    /**
     * @throws Exception
     * @throws bdHttpException
     */
    public function run()
    {
        $pageCount = 1;
        $page = 0;
        $params = array(
            'language'  => 'cn',
            'type'      => 1,
            'pageIndex' => $page,
            'pageNum'   => 10,
        );

        while ($page < self::MAX_PAGE) {
            $url = self::AJAX_POST_INFO_LIST;

            $res = bdHttpRequest::post($url, $params, array(), array("User-Agent"=>SpiderFrame::USER_AGENT, "Cookie"   => "safedog-flow-item=F02FA4700D43562E324FF6B4A88573D4; JSESSIONID=91B5F241B8A48F84C130DEE19A8FF067.tomcat1"));

            if ($res->getStatus() == 200) {
                $json = json_decode($res->getBody(), true);
                $pageCount = $json['pageCount'];
                $insightInfoList = $json['insightInfoList'];

                foreach ($insightInfoList as $item) {
                    if (!empty($item['fileName'])) {
                        $record = new XlegalLawContentRecord();
                        $record->title = $item['title'];
                        $record->publish_time = strtotime($item['infoDate']);
                        $record->url = sprintf(self::ATTACHMENT_PREFIX, $item['fileName']);
                        $record->author = "汉坤律师事务所";
                        $record->tags = "律所实务";
                        $record->doc_id = md5($record->url);
                        $record->url_md5 = $record->doc_id;
                        $record->type = DaoUrlCache::TYPE_PDF;
                        $record->status = 0;

                        if (gsettings()->debug) {
                            var_dump($record);
                            exit(0);
                        }

                        DaoXlegalLawContentRecord::getInstance()->insert($record);
                    }
                }
            }
            $page++;
            sleep(rand(1,5));
        }
    }
}