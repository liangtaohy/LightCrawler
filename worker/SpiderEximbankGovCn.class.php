<?php

/**
 * 中国进出口银行
 * http://www.eximbank.gov.cn/tm/medialist/index_23.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/10
 * Time: PM5:03
 */
define("CRAWLER_NAME", "spider-eximbank.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderEximbankGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.eximbank.gov.cn/tm/medialist/index.aspx?nodeid=23&pagesize=1&pagenum=10"
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#http://www\.eximbank\.gov\.cn/tm/medialist/index\.aspx\?nodeid=23&pagesize=[0-9]+&pagenum=[0-9]+# i"    => "handleListPage",
        "#http://www\.eximbank\.gov\.cn/tm/medialist/index_23_[0-9]+\.html# i"    => "handleDetailPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment"
    );


    protected $author = '中国进出口银行';
    protected $tag = '行业规定';

    /**
     * SpiderEximbankGovCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $doc = $extract->getExtractor()->extractor->document();
        $zwNode = $doc->query("//div[@class='auto fabu']")->item(0);
        $html = $extract->getExtractor()->extractor->domDocument()->saveHTML($zwNode);
        $extract1 = new ExtractContent($DocInfo->url, $DocInfo->url, $html);
        $extract1->parse();
        $content = $extract1->getContent();
        $title = trim($doc->query("//div[@class='f4 tc lh22 mb12 fb_tit']")->item(0)->nodeValue);
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = $title;
        $record->author = $this->author;
        $record->content = $content;
        $record->doc_ori_no = $extract->doc_ori_no;
        $record->publish_time = $extract->publish_time;
        $record->t_valid = $extract->t_valid;
        $record->t_invalid = $extract->t_invalid;
        $record->negs = implode(",", $extract->negs);
        $record->tags = $this->tag;
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


        $record->type = DaoSpiderlLawBase::TYPE_TXT;
        $record->status = 1;
        $record->url = $extract->baseurl;
        $record->url_md5 = md5($extract->url);

        if (gsettings()->debug) {
            echo "raw: " . implode("", $extract->text) . PHP_EOL;
            //$index_blocks = $extract->indexBlock($extract->text);
            //echo implode("\n", $index_blocks) . PHP_EOL;
            var_dump($record);
            exit(0);
        }
        echo "insert data: " . $record->doc_id . PHP_EOL;
        return $record;
    }
}