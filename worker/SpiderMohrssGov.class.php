<?php

/**
 * Created by PhpStorm.
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/21
 * Time: PM1:20
 */
define("CRAWLER_NAME", "spider-mohrss.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderMohrssGov extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.mohrss.gov.cn/gkml/216/list.html",
    );

    protected $ContentHandlers = array(
        "#http://www.mohrss.gov.cn/gkml/216/list([_0-9]+)?\.html# i"   => "handleListPage",
        "#http://www.mohrss.gov.cn/gkml/xxgk/[0-9]+/t[0-9]+_[0-9]+\.html# i"   => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderNdrcGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_pergecache();
    }

    protected function _pergecache()
    {
        $page = 1;
        $pagesize = 10000;

        $where = array(
            "spider"    => md5(CRAWLER_NAME),
            "processed" => 1,
            "in_process"    => 0,
        );

        $sort = array(
            "id" => "ASC"
        );

        $fields = array(
            "id",
            "url_rebuild",
            "distinct_hash",
        );

        $res = $url_cache = DaoUrlCache::getInstance()->search_data($where, $sort, $page, $pagesize, $fields);

        $lists = array();
        foreach ($res['data'] as $re) {
            $url = $re['url_rebuild'];
            foreach ($this->ContentHandlers as $pattern => $contentHandler) {
                if ($contentHandler === "handleListPage" || $contentHandler === "void") {
                    if (preg_match($pattern, $url)) {
                        if (!isset($lists[$pattern])) {
                            $lists[$pattern] = array();
                        }

                        $lists[$pattern][] = $re;
                    }
                }
            }
        }

        $ids = array();
        foreach ($lists as $pattern => $list) {
            $total = ceil(count($list) / 3);
            if ($total > self::MAX_PAGE) {
                $total = self::MAX_PAGE;
            }

            for ($i = 0; $i < $total; $i++) {
                $u = $list[$i];
                $ids[] = $u['id'];
            }
        }

        DaoUrlCache::getInstance()->pergeCacheByIds($ids);
        if (gsettings()->debug) {
            var_dump($ids);
            exit(0);
        }
    }

    //var m_nRecordCount = "2278";
    //var m_nPageSize = 20;
    public function computePages(PHPCrawlerDocumentInfo $DocInfo)
    {
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

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pager = $this->computePages($DocInfo);
        $sPageName = "list";
        $sPageExt = "html";

        $r = preg_match("#index(_[0-9]+)?.html# i", $DocInfo->url);

        if (!empty($r)) {
            $sPageName = "index";
        }

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