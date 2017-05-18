<?php

/**
 * 国土资源部
 * http://f.mlr.gov.cn/201704/t20170428_1506260.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/16
 * Time: PM10:23
 */
define("CRAWLER_NAME", "spider-mlr.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderMlrGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://f.mlr.gov.cn/index_3553.html"
    );

    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"  => "handleDetailPage",
        "#http://f\.mlr\.gov\.cn/index_3553([0-9_]+)?\.html# i"    => "handleListPage",
        "#/[0-9a-zA-Z_]+\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderMepGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function createPageHTML($url, $_nPageCount, $_nCurrIndex, $_sPageName, $_sPageExt)
    {
        if(empty($_nPageCount) || $_nPageCount<=1){
            return false;
        }

        if($_nCurrIndex<$_nPageCount-1)
        {
            return Formatter::formaturl($url, $_sPageName . "_" . ($_nCurrIndex+1) . "." . $_sPageExt);
        }

        return false;
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pages = array();
        $patterns = array(
            chr(13),
        );

        $replaces = array(
            "\n",
        );

        $source = str_replace($patterns, $replaces, $DocInfo->source);

        $lines = explode("\n", $source);
        foreach ($lines as $line) {
            preg_match("#createPageHTML\(([0-9]+), ([0-9]+), \"([a-zA-Z0-9_]+)\", \"([a-z]+)\"\);# i", $line, $matches);
            if (!empty($matches) && count($matches) > 4) {
                $page = $this->createPageHTML($DocInfo->url, $matches[1], $matches[2], $matches[3], $matches[4]);
                if (!empty($page)) {
                    $pages[] = $page;
                }
            }
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }

        return $pages;
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        preg_match("#file_appendix='<a href=\"(\./[A-Z0-9]+\.doc|\./[A-Z0-9]+\.docx|\./[A-Z0-9]+\.pdf)\">(.*)?</a># i", $DocInfo->source, $attachments);
        $attachment = array();
        if (!empty($attachments) && count($attachments) > 2) {
            $attachment['title'] = $attachments[2];
            $attachment['url']  = Formatter::formaturl($DocInfo->url, $attachments[1]);
        }

        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $tags = array();
        $document = $extract->getExtractor()->extractor->domDocument();
        $doc = $extract->getExtractor()->extractor->document();
        $spans = $doc->query("//div[@id='country']/div[@class='dtl-middle']/div[@class='mid-2']/span");
        $doc_ori_no = ExtractContent::UnifyDocOriNo(trim($spans->item(0)->nodeValue));
        $author = trim($spans->item(1)->nodeValue);
        $tags[] = trim($spans->item(2)->nodeValue);
        $spans2 = $doc->query("//div[@id='country']/div[@class='dtl-middle']/div[@class='mid-4']/span");
        $publish_time = ExtractContent::UnifyPublishtime(trim($spans2->item(0)->nodeValue));
        $tags[] = trim($spans2->item(1)->nodeValue);
        $extract->parse();

        $content = $extract->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($extract->title) ? $extract->title : $extract->guessTitle();
        $record->author = !empty($author) ? $author : $extract->author;
        $record->content = $content;
        $record->doc_ori_no = !empty($doc_ori_no) ? $doc_ori_no : $extract->doc_ori_no;
        $record->publish_time = !empty($publish_time) ? $publish_time : $extract->publish_time;
        $record->t_valid = $extract->t_valid;
        $record->t_invalid = $extract->t_invalid;
        $record->negs = implode(",", $extract->negs);
        $record->tags = implode(",", $tags);
        $record->simhash = '';

        if (!empty($attachment)) {
            $record->attachment = json_encode($attachment, JSON_UNESCAPED_UNICODE);
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