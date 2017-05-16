<?php

/**
 * 中国农业部
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/2
 * Time: PM5:06
 */
define("CRAWLER_NAME", "spider-moa.gov");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderMoaGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.moa.gov.cn/govpublic/22/26/index_1148.htm",
        "http://www.moa.gov.cn/govpublic/22/28/index_1148.htm",
        "http://www.moa.gov.cn/govpublic/22/32/index_1148.htm",
    );

    protected $ContentHandlers = array(
        "#http://www\.moa\.gov\.cn/govpublic/[0-9]+/[0-9]+/index_[0-9]+([0-9_]+)?\.htm# i" => "handleListPage",
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.htm# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls|ceb)# i" => "handleAttachment",
    );

    /**
     * SpiderMoaGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $m_sPageName = '';
        $m_sPageExt = '';
        $m_nRecordCount = '';
        $m_nPageSize = '';

        $lines = explode("\n", $DocInfo->source);
        foreach ($lines as $line) {
            preg_match("#var m_sPageName = \"([a-z0-9_]+)\";# i", $line, $matches);

            if (!empty($matches) && count($matches) > 1) {
                $m_sPageName = $matches[1];
                unset($matches);
                break;
            }
        }

        unset($matches);

        foreach ($lines as $line) {
            preg_match("#var m_sPageExt = \"([a-z0-9]+)\";# i", $line, $matches);
            if (!empty($matches) && count($matches) > 1) {
                $m_sPageExt = $matches[1];
                unset($matches);
                break;
            }
        }

        unset($matches);

        preg_match("#var m_nRecordCount = \"([0-9]+)\";# i", $DocInfo->source, $matches);
        if (!empty($matches) && count($matches) > 1) {
            $m_nRecordCount = intval($matches[1]);
        }

        unset($matches);
        preg_match("#var m_nPageSize = ([0-9]+);# i", $DocInfo->source, $matches);
        if (!empty($matches) && count($matches) > 1) {
            $m_nPageSize = intval($matches[1]);
        }

        $pages = ceil($m_nRecordCount/$m_nPageSize);

        $pageArray = array();
        for($i = 1; $i <= $pages; $i++) {
            if($i == 1) {
                $sURL = $m_sPageName . "." . $m_sPageExt;
                $sURL = Formatter::formaturl($DocInfo->url, $sURL);
                $pageArray[] = $sURL;
            } else {
                $sURL = $m_sPageName . "_" . ($i - 1) . "." . $m_sPageExt;
                $sURL = Formatter::formaturl($DocInfo->url, $sURL);
                $pageArray[] = $sURL;
            }
        }

        if (gsettings()->debug) {
            var_dump($pageArray);
            exit(0);
        }
        return $pageArray;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool|XlegalLawContentRecord
     */
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