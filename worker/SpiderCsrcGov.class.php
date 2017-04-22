<?php

/**
 * 证监会
 *
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/21
 * Time: AM11:58
 */
define("CRAWLER_NAME", "spider-csrc.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCsrcGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.csrc.gov.cn/pub/newsite/xzcfw/xzcfjd/index.htm",
        "http://www.csrc.gov.cn/pub/newsite/xzcfw/scjrjd/index.htm",
        "http://www.csrc.gov.cn/pub/newsite/xzcfw/zlzgtz/index.htm",
    );

    protected $ContentHandlers = array(
        "#http://www.csrc.gov.cn/pub/newsite/xzcfw/(xzcfjd|scjrjd|zlzgtz)/index([\_0-9]+)?\.htm# i" => "handleListPage",
        "#http://www.csrc.gov.cn/pub/zjhpublic/[0-9A-Z]+/[0-9]+/t[0-9]+_[0-9]+\.htm# i" => "handleDetailPage",
        //"#/t[0-9]+_[0-9]+\.html# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderDyChinasarftGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    public function computePages(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pagesPatterns = array(
            "#var countPage\s+=\s+([0-9]+)?[;\/\/]# i",
        );

        $pages = 0;

        foreach ($pagesPatterns as $pagesPattern) {
            $result = preg_match($pagesPattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $pages = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        $res = array();
        $res['pages'] = $pages;

        return $res;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pager = $this->computePages($DocInfo);
        $sPageName = "index";
        $sPageExt = "htm";

        $p = strrpos($DocInfo->url, "/");
        $prefix = substr($DocInfo->url, 0, $p + 1);

        $pages = array();
        for ($i = 1; $i <= $pager['pages']; $i++)
        {
            if($i == 1){
                $url = $sPageName . "." . $sPageExt;
            }else{
                $url = $sPageName . "_" . ($i-1) . "." . $sPageExt;
            }
            $pages[] = $prefix . $url;
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }

        return $pages;
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

        if (mb_strpos($record->title, "处罚决定", 0, "UTF-8") !== false) {
            $record->doc_ori_no = "证监罚字" . $record->doc_ori_no;
        }

        if (mb_strpos($record->title, "禁入决定", 0, "UTF-8") !== false) {
            $record->doc_ori_no = "证监法律字" . $record->doc_ori_no;
        }

        if (empty(gsettings()->debug)) {
            $res = FlaskRestClient::GetInstance()->simHash($c);

            $simhash = '';
            if (isset($res['simhash']) && !empty($res['simhash'])) {
                $simhash = $res['simhash'];
            }

            if (isset($res['repeated']) && !empty($res['repeated'])) {

                $flag = 1;
                if (!empty($record->doc_ori_no)) {
                    $r = DaoXlegalLawContentRecord::getInstance()->ifDocOriExisted($record);
                    if (empty($r)) {
                        $flag = 0;
                    }
                }
                echo 'data repeated: ' . $DocInfo->url . ', repeated simhash: ' . $res['simhash1'] . 'flag: ' . $flag . ', doc_ori_no' . $record->doc_ori_no . PHP_EOL;
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
            return false;
        }
        echo "insert data: " . $record->doc_id . PHP_EOL;
        return $record;
    }
}