<?php

/**
 * 中国外汇网
 * http://www.chinaforex.com.cn/index.php/cms/item-list-category-61.shtml
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/6/4
 * Time: PM11:14
 */
define("CRAWLER_NAME", "spider-chinaforex.com.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderChinaForExComCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.chinaforex.com.cn/index.php/cms/item-list-category-61.shtml",
        "http://www.chinaforex.com.cn/index.php/cms/item-list-category-113.shtml",
    );

    protected $ContentHandlers = array(
        "#http://www\.chinaforex\.com\.cn/index\.php/cms/item-list-category-(61|113)-page-(2|3|4|5)+\.shtml# i" => "handleListPage",
        "#http://www\.chinaforex\.com\.cn/index\.php/cms/item-list-category-(61|113)\.shtml# i"    => "handleListPage",
        "#http://www\.chinaforex\.com\.cn/index\.php/cms/item-view-id-[0-9]+\.shtml# i" => "handleDetailPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment",
    );

    public function __construct()
    {
        parent::__construct();
        DaoUrlCache::getInstance()->cleanup(CRAWLER_NAME);
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $doc = $extract->getExtractor()->extractor->document();
        $title = trim($doc->query("//div[@id='article']/h4")->item(0)->nodeValue);
        $publish_time = strtotime(trim($doc->query("//div[@class='info']/span")->item(0)->nodeValue));
        $author = trim($doc->query("//div[@class='info']/span")->item(1)->nodeValue);
        $extract->parse();

        $content = $extract->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = $title;
        $record->author = !empty($author) ? $author : $extract->author;
        $record->content = $content;
        $record->doc_ori_no = $extract->doc_ori_no;
        $record->publish_time = $publish_time;
        $record->t_valid = $extract->t_valid;
        $record->t_invalid = $extract->t_invalid;
        $record->negs = implode(",", $extract->negs);
        $record->tags = $extract->tags;
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