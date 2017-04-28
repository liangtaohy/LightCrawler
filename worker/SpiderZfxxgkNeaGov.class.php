<?php

/**
 * 国家能源局
 * 信息公开
 * http://zfxxgk.nea.gov.cn/148/151/index_55.htm
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/28
 * Time: PM3:12
 */
define("CRAWLER_NAME", "spider-zfxxgk.nea.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderZfxxgkNeaGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    static $SeedConf = array(
        "http://zfxxgk.nea.gov.cn/index.htm",
    );

    protected $ContentHandlers = array(
        "#http://zfxxgk.nea.gov.cn/148/[0-9]+/index_[0-9]+.htm# i"  => "handleListPage",
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.htm# i" => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderZfxxgkNeaGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function computePages(PHPCrawlerDocumentInfo $DocInfo)
    {
        if (preg_match("#http://zfxxgk.nea.gov.cn/148/[0-9]+/index_[0-9]+.htm# i", $DocInfo->url) === false) {
            return array();
        }

        $totalPatterns = array(
            "#var m_nRecordCount = [\"]?([0-9]+)[\"]?;# i",
        );

        $pagesizePatterns = array(
            "#var m_nPageSize = [\"]?([0-9]+)[\"]?;# i",
        );

        $pagesPatterns = array();

        $total = 0;
        $pagesize = 0;
        $pages = 0;

        foreach ($pagesPatterns as $pagesPattern) {
            $result = preg_match($pagesPattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $pages = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        if (!empty($pages)) {
            $res = array(
            );
            $res['pages'] = $pages;
            return $res;
        }

        unset($result);
        unset($matches);
        foreach ($totalPatterns as $totalPattern) {
            $result = preg_match($totalPattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $total = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        if (empty($total)) {
            echo "FATAL get total page failed: " . $DocInfo->url . PHP_EOL;
            return true;
        }

        unset($result);
        unset($matches);

        foreach ($pagesizePatterns as $pagesizePattern) {
            $result = preg_match($pagesizePattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $pagesize = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        if (empty($pagesize)) {
            echo "FATAL get pagesize failed: " . $DocInfo->url . PHP_EOL;
            return array(
                'total' => $total,
            );
        }

        $res = array(
            'total' => $total,
        );
        $total = ceil($total / $pagesize);
        $res['pages'] = $total;
        $res['pagesize'] = $pagesize;

        return $res;
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pager = $this->computePages($DocInfo);
        $m_sPageName = '';
        $m_sPageExt = '';

        $lines = explode("\n", $DocInfo->source);
        foreach ($lines as $line) {
            preg_match("#var m_sPageName = \"([a-z0-9_]+)\";# i", $line, $matches);

            if (!empty($matches) && count($matches) > 1) {
                $m_sPageName = $matches[1];
                unset($matches);
                break;
            }
        }

        foreach ($lines as $line) {
            preg_match("#var m_sPageExt = \"([a-z0-9]+)\";# i", $line, $matches);
            if (!empty($matches) && count($matches) > 1) {
                $m_sPageExt = $matches[1];
                unset($matches);
                break;
            }
        }

        $r = preg_match("#index(_[0-9]+)?.html# i", $DocInfo->url);

        if (!empty($r)) {
            $m_sPageName = "index";
        }

        $p = strrpos($DocInfo->url, "/");
        $prefix = substr($DocInfo->url, 0, $p + 1);

        $pages = array();
        for ($i = 1; $i <= $pager['pages']; $i++)
        {
            if($i == 1){
                $url = $m_sPageName . "." . $m_sPageExt;
            }else{
                $url = $m_sPageName . "_" . ($i-1) . "." . $m_sPageExt;
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
            return false;
        }
        echo "insert data: " . $record->doc_id . PHP_EOL;
        return $record;
    }
}