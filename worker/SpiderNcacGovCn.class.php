<?php

/**
 * 国家版权局
 * http://www.ncac.gov.cn/chinacopyright/channels/479.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/18
 * Time: PM12:40
 */
define("CRAWLER_NAME", "spider-ncac.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderNcacGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.ncac.gov.cn/chinacopyright/channels/479.html"
    );

    protected $ContentHandlers = array(
        "#http://www\.ncac\.gov\.cn/chinacopyright/contents/[0-9]+/[0-9]+\.html# i" => "handleDetailPage",
        "#http://www\.ncac\.gov\.cn/chinacopyright/channels/(480|479|481|482|483|514|510|511|515|513)([0-9_]+)?\.html# i"   => "handleListPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderNcacGovCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    //span class="STYLE24"
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $doc = $extract->getExtractor()->extractor->document();
        $title = trim($doc->query("//span[@class='STYLE24']")->item(0)->nodeValue);

        preg_match("#<td align=\"center\">&nbsp;([0-9]{4}-[0-9]{2}-[0-9]{2})# i", $DocInfo->source, $matches);
        $publish_time = 0;
        if (!empty($matches) && count($matches) > 1) {
            $publish_time = strtotime($matches[1]);
        }
        $extract->parse();

        $content = $extract->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($title) ? $title : (!empty($extract->title) ? $extract->title : $extract->guessTitle());
        $record->author = $extract->author;
        $record->content = $content;
        $record->doc_ori_no = $extract->doc_ori_no;
        $record->publish_time = !empty($publish_time) ? $publish_time : intval($extract->publish_time);
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