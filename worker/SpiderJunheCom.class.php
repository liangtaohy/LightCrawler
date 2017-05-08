<?php

/**
 *
 * http://www.junhe.com/law-reviews
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/8
 * Time: PM4:36
 */
define("CRAWLER_NAME", md5("spider-www.junhe.com"));
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderJunheCom extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.junhe.com/law-reviews?year=2017"
    );

    protected $ContentHandlers = array(
        "#http://www\.junhe\.com/law\-reviews/[0-9]+$# i"   => "handleDetailPage",
        "#http://www\.junhe\.com/law\-reviews\?(year=[0-9]+&)?page=[0-9]+# i" => "handleListPage",
        "#/.*\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderJunheCom constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    // printArea
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $doc = $extract->getExtractor()->extractor->document();
        $document = $extract->getExtractor()->extractor->domDocument();
        $main_stream = $document->saveHTML($doc->query("//div[@id='printArea']")->item(0));

        $extract1 = new ExtractContent($DocInfo->url, $DocInfo->url, $main_stream);

        $extract1->parse();
        $content = $extract1->getContent();

        if (empty($content)) {
            $extract->parse();

            $content = $extract->getContent();
        }

        $title = trim($doc->query("//h1[@class='d-title']")->item(0)->nodeValue);

        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = $title;
        $record->author = "君合律师事务所";
        $record->content = $content;
        $record->doc_ori_no = $extract->doc_ori_no;
        $record->publish_time = $extract->publish_time;
        $record->t_valid = $extract->t_valid;
        $record->t_invalid = $extract->t_invalid;
        $record->negs = implode(",", $extract->negs);
        $record->tags = "律所实务";
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

        $doc = $extract->extractor->document();
        if (empty($record->publish_time)) {
            $biaoti_s = $doc->query("//span[@class='biaoti_s']");
            if (!empty($biaoti_s) && $biaoti_s instanceof DOMNodeList) {
                foreach ($biaoti_s as $biaoti_) {
                    preg_match("#([0-9]{4})-([0-9]{2})-([0-9]{2})# i", $biaoti_->nodeValue, $matches);
                    if (!empty($matches) && count($matches) > 3) {
                        $record->publish_time = strtotime(sprintf("%s-%s-%s", $matches[1], $matches[2], $matches[3]));
                        break;
                    }
                }
            }
        }

        $record->type = DaoSpiderlLawBase::TYPE_TXT;
        $record->status = 1;
        $record->url = $extract->baseurl;
        $record->url_md5 = md5($extract->url);

        if (gsettings()->debug) {
            //echo "raw: " . implode("\n", $extract->text) . PHP_EOL;
            //$index_blocks = $extract->indexBlock($extract->text);
            //echo implode("\n", $index_blocks) . PHP_EOL;
            var_dump($record);
            exit(0);
        }
        echo "insert data: " . $record->doc_id . PHP_EOL;
        return $record;
    }
}