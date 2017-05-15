<?php

/**
 * 中央国债登记结算有限责任公司
 * http://www.chinabond.com.cn/cb/cn/zqsc/ywgz/zyjsgs/sczrjkxh/list.shtml
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/11
 * Time: PM10:46
 */
define("CRAWLER_NAME", "spider-chinabond.com.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderChinabondComCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.chinabond.com.cn/cb/cn/zqsc/ywgz/zyjsgs/sczrjkxh/list.shtml"
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#/cb/cn/zqsc/ywgz/zyjsgs/[a-z]+/list\.shtml# i"  => "handleListPage",
        "#/cb/cn/zqsc/ywgz/zyjsgs/[a-z]+/[0-9]{8}/[0-9]+\.shtml# i"    => "handleDetailPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment"
    );

    protected $author = "中央国债登记结算有限责任公司";
    protected $tag = "行业规定";

    /**
     * SpiderChinabondComCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        //file_put_contents("dump.html", $DocInfo->source . PHP_EOL);
        $extract = new Extractor($DocInfo->source, $DocInfo->url);
        $document = $extract->domDocument();
        $links = $document->getElementsByTagName("a");
        $pages = array();
        foreach ($links as $link) {
            if ($link->hasAttribute("href")) {
                $href = $link->getAttribute('href');
                $href = Formatter::formaturl($DocInfo->url, $href);
                if (preg_match("#/cb/cn/zqsc/ywgz/zyjsgs/[a-z]+/list\.shtml# i", $href)) {
                    $pages[] = $href;
                } else if (preg_match("#/cb/cn/zqsc/ywgz/zyjsgs/[a-z]+/[0-9]{8}/[0-9]+\.shtml# i", $href)) {
                    $pages[] = $href;
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
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $extract->parse();

        $content = $extract->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($extract->title) ? $extract->title : $extract->guessTitle();
        $record->author = $extract->author;
        $record->content = $content;
        $record->doc_ori_no = '';
        $record->publish_time = $extract->publish_time;
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