<?php

/**
 * http://zhengce.beijing.gov.cn/zyk_search_zc/searchForZhengCe
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/4
 * Time: PM6:43
 */
define("CRAWLER_NAME", "spider-zhengce.beijing.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderZhengceBeijingGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    static $SeedConf = array(
        "http://zhengce.beijing.gov.cn/zyk_search_zc/searchForZhengCe",
    );

    protected $ContentHandlers = array(
        "#http://zhengce\.beijing\.gov\.cn/zyk_search_zc/searchForZhengCe(\?nothing=nothing&pageBean\.currentPage=[0-9]+&pageBean\.itemsPerPage=[0-9]+)?# i"    => "handleListPage",
        "#http://zhengce\.beijing\.gov\.cn/library/[0-9]+/[0-9]+/[0-9]+/[0-9]+/[0-9]+/[0-9]+/index\.html# i"   => "handleDetailPage",
        "/\/[\x{4e00}-\x{9fa5}0-9a-zA-Z_\x{3010}\x{3011}\x{FF08}\x{FF09}\]\[]+\.(doc|pdf|txt|xls|ceb)/ui" => "handleAttachment",
    );

    /**
     * SpiderZfxxgkNeaGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $extract->keep_img = true;

        $document = $extract->getExtractor()->extractor->domDocument();
        $body = $document->getElementsByTagName("body")->item(0);

        $blocks = array();
        $extract->linkBlocks($body, $blocks)->deleteNodes($blocks);

        $extract->parse();

        $content = $extract->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($extract->title) ? $extract->title : $extract->guessTitle();
        $record->author = $extract->author;
        $record->content = $content;
        $record->doc_ori_no = $extract->doc_ori_no;
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