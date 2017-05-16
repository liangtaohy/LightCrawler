<?php

/**
 * 北京广播电视局行政许可
 * http://www.bjrt.gov.cn/zwgk/xzxkxxgk/
 * User: xlegal
 * Date: 17/5/15
 * Time: PM4:45
 */
define("CRAWLER_NAME", "spider-xxgk.bjrt.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderBjrtXZXK extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://218.246.104.101:8090/gdwzyy/xzXkQueryController.do?xzXkQuery",
    );

    protected $ContentHandlers = array(
        "#http://218\.246\.104\.101:8090/gdwzyy/xzXkQueryController\.do\?xzXkQuery# i"  => "handleListPage",
        "#http://218\.246\.104\.101:8090/gdwzyy/xzXkQueryController\.do\?view&id=[0-9a-z_]+# i" => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderBjrtXZXK constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $detail = "http://218.246.104.101:8090/gdwzyy/xzXkQueryController.do?view&id=%s";
        $url = "http://218.246.104.101:8090/gdwzyy/xzXkQueryController.do?datagrid&field=id,infoName,itemName,numOrder,decisionDepart,licenceCode,approvalPlace,personName,phone,creditUnifyCode,settingGist,permissionDecision,remark,xzjdlb,xzxdrlb,ratifyDate,";
        $cookiesArr = $DocInfo->cookies;

        $cookies = array();
        foreach ($cookiesArr as $cookie) {
            $cookies[$cookie->name] = $cookie->value;
        }

        $rows = 20;
        $page = 1;
        $pages = 1;

        while($page <= $pages) {
            $postParams = array(
                "page"  => $page,
                "rows"  => $rows,
            );

            $res = bdHttpRequest::post($url, $postParams, $cookies, array(
                "User-Agent"    => SpiderFrame::USER_AGENT,
                "Referer"   => "http://218.246.104.101:8090/gdwzyy/xzXkQueryController.do?xzXkQuery"
            ));

            if (!empty($res) && $res->getStatus() == 200) {
                $json = json_decode($res->getBody(), true);
                if ($pages === 1) {
                    $pages = ceil($json['total'] / $rows);
                }

                $records = array();
                foreach ($json['rows'] as $row) {
                    $id = $row['id'];
                    $title = $row['infoName'];
                    $url = sprintf($detail, $id);
                    $record = new stdClass();
                    $record->refering_url = "http://218.246.104.101:8090/gdwzyy/xzXkQueryController.do?xzXkQuery";
                    $record->url = $url;
                    $record->title = $title;
                }
                $this->insert2urls($records);
            }
        }

    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool|XlegalLawContentRecord
     */
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $content = '';
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $doc = $extract->getExtractor()->extractor->document();
        $document = $extract->getExtractor()->extractor->domDocument();

        $title = trim($doc->query("////div[@class='dybox']/h1")->item(0)->nodeValue);
        $dybox = $doc->query("//div[@class='dybox']");
        if (!empty($dybox) && $dybox instanceof DOMNodeList) {
            $content = $document->saveHTML($dybox->item(0));
            unset($extract);
            $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $content);
            $content = base64_encode(gzdeflate($content));
        }

        $extract->parse();

        if (empty($content))
            $content = $extract->getContent();

        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($title) ? $title : (!empty($extract->title) ? $extract->title : $extract->guessTitle());
        $record->author = "北京市新闻出版广电局";
        $record->content = $content;
        $record->doc_ori_no = $extract->doc_ori_no;
        $record->publish_time = $extract->publish_time;
        $record->t_valid = $extract->t_valid;
        $record->t_invalid = $extract->t_invalid;
        $record->negs = implode(",", $extract->negs);
        $record->tags = "行政许可";
        $record->simhash = '';
        if (!empty($extract->attachments)) {
            $record->attachment = json_encode($extract->attachments, JSON_UNESCAPED_UNICODE);
        }

        if (empty(gsettings()->debug)) {
            $res = FlaskRestClient::GetInstance()->simHash($c);

            $simhash = '';
            if (isset($res['simhash']) && !empty($res['simhash'])) {
                $simhash = $res['simhash'];
            }

            if (isset($res['repeated']) && !empty($res['repeated'])) {
                echo 'data repeated: ' . $DocInfo->url . ', repeated simhash: ' . $res['simhash1'] .PHP_EOL;
                $flag = 1;
                if (!empty($record->doc_ori_no)) {
                    $r = DaoXlegalLawContentRecord::getInstance()->ifDocOriExisted($record);
                    if (empty($r)) {
                        $flag = 0;
                    }
                }

                if ($flag)
                    return false;
            }

            $record->simhash = $simhash;
        }


        if (!empty($this->author)) {
            $record->author = $this->author;
        }

        if (!empty($this->tag)) {
            $record->tags = $this->tag;
        }

        $record->type = DaoSpiderlLawBase::TYPE_HTML_FRAGMENT;
        $record->status = 1;
        $record->url = $extract->baseurl;
        $record->url_md5 = md5($extract->url);

        if (gsettings()->debug) {
            //echo "raw: " . implode("\n", $extract->text) . PHP_EOL;
            //$index_blocks = $extract->indexBlock($extract->text);
            //echo implode("\n", $index_blocks) . PHP_EOL;
            //var_dump($record);
            //exit(0);
        }
        echo "insert data: " . $record->doc_id . PHP_EOL;
        return $record;
    }
}