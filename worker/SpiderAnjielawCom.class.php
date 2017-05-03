<?php

/**
 * 安杰律师事务所
 * http://www.anjielaw.com/news/media/index.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/3
 * Time: PM4:12
 */
define("CRAWLER_NAME", md5("spider-anjielaw.com"));
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderAnjielawCom extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.anjielaw.com/news/index.html",
        "http://www.anjielaw.com/news/media/index.html",
        "http://www.anjielaw.com/publication/research/index.html",
    );

    protected $ContentHandlers = array(
        "#http://www\.anjielaw\.com/news/industry/[0-9]+\.html# i" => "handleDetailPage",
        "#http://www\.anjielaw\.com/news/media/index\.html# i" => "handleListPage",
        "#http://www\.anjielaw\.com/news/index\.html# i"   => "handleListPage",
        "#http://www\.anjielaw\.com/publication/research/index\.html# i"    => "handleListPage",
        "#http://www\.anjielaw\.com/news/media/[0-9]+\.html# i" => "handleDetailPage",
        "#http://www\.anjielaw\.com/publication/research/list_[0-9]+_[0-9]+\.html#" => "handleListPage",
        "#http://www\.kwm\.com/zh/knowledge/insights/[a-zA-Z\-]+-[0-9]+# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderKwmCom constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $extract->parse();

        $doc = $extract->getExtractor()->extractor->document();

        $articles = $doc->query("//div[@class='article-box']");

        $divHd = $doc->query("//div[@class='hd']/h6");
        $content = '';
        if (!empty($articles) && $articles instanceof DOMNodeList) {
            $source = $extract->toHTML($articles->item(0));
            $extract1 = new ExtractContent($DocInfo->url, $DocInfo->url, $source);
            $extract1->parse();
            $content = $extract1->toText();
        }

        if (empty($content)) {
            $content = $extract->getContent();
        }

        if (!empty($divHd) && $divHd instanceof DOMNodeList) {
            $divHd = trim($divHd->item(0)->nodeValue);
            preg_match("/([0-9]{4})\/([0-9]{2})\/([0-9]{2})/u", $divHd, $matches);
            if (!empty($matches) && count($matches) > 3) {
                $extract->publish_time = strtotime(sprintf("%s-%s-%s", $matches[1], $matches[2], $matches[3]));
            }
        }

        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($extract->title) ? $extract->title : $extract->guessTitle();
        $record->author = "安杰律师事务所";
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