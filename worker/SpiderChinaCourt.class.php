<?php

/**
 * chinacourt.org
 *
 * User: liangtaohy@163.com
 * Date: 17/3/31
 * Time: PM10:01
 */
define("CRAWLER_NAME", "spider-chinacourt.gov");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderChinaCourt extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.chinacourt.org/law.shtml",
        "http://www.chinacourt.org/law/more/law_type_id/MzAwMkAFAA%3D%3D/page/1.shtml",
        "http://www.chinacourt.org/law/more/law_type_id/MzAwNEAFAA%3D%3D.shtml",
        "http://www.chinacourt.org/law/more/law_type_id/MzAwM0AFAA%3D%3D.shtml",
    );

    protected $ContentHandlers = array(
        "#(http://www.chinacourt.org/law/more/law_type_id/[a-zA-Z]+%3D%3D/page/[0-9]+.shtml)$# i" => "handleListPage",
        "#(http://www.chinacourt.org/law/more/law_type_id/[a-zA-Z]+%3D%3D.shtml)$# i" => "handleListPage",
        "#(http://www.chinacourt.org/law/detail/[0-9]+/[0-9]+/id/[0-9]+.shtml)$# i" => "handleDetailPage",
    );

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

        $pages = $res['pages'];

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

        if (gsettings()->debug) {
            var_dump($ids);
            exit(0);
        }
        DaoUrlCache::getInstance()->pergeCacheByIds($ids);
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        return true;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool
     */
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        // remove \r\n (^M character)
        $patterns = array(
            chr(13),
            '<BR>',
            '<br />',
            '<br>',
            '<BR />'
        );

        $replaces = array(
            "\n",
            "\n",
            "\n",
            "\n",
            "\n"
        );

        $source = str_replace($patterns, $replaces, $DocInfo->source);
        $extractor = new Extractor($source, $DocInfo->url);
        $doc = $extractor->document();

        if (empty($doc)) {
            echo "content is null: " . $DocInfo->url . PHP_EOL;
            return;
        }

        $source = str_replace($patterns, $replaces, $DocInfo->source);

        $extractor = new Extractor($source, $DocInfo->url);
        $doc = $extractor->document();

        if (empty($doc)) {
            echo "content is null: " . $DocInfo->url . PHP_EOL;
            return;
        }

        $stitles = $doc->query("//div[@class='law_content']/span[@class='STitle']");
        $context_texts = $doc->query("//div[@class='law_content']/div[@class='content_text']");

        $mtitles = $doc->query("//p[@align='center']/font[@class='MTitle']");

        $title = '';
        $raw_content = '';
        $summary = '';

        if ($stitles instanceof DOMNodeList && !empty($stitles)) {
            foreach ($stitles as $element) {
                if ($element->nodeName === 'span') {
                    $summary = $element->nodeValue;
                    break;
                }
            }
        }

        if ($mtitles instanceof DOMNodeList && !empty($mtitles)) {
            foreach ($mtitles as $mtitle) {
                $title = $mtitle->nodeValue;
                break;
            }
        }

        if ($context_texts instanceof DOMNodeList && !empty($context_texts)) {
            foreach ($context_texts as $context_text) {
                if ($context_text->nodeName === 'div') {
                    if (empty($title)) {
                        $title = mb_substr($context_text->nodeValue, 0, mb_strpos($context_text->nodeValue, "\n", 0, "UTF-8"), "UTF-8");
                    }

                    $raw_content = $context_text->nodeValue;
                    break;
                }
            }
        }

        $document['title'] = trim($title);
        $document['ctime'] = $document['mtime'] = Utils::microTime();
        $document['content'] = $raw_content;
        $document['craw_url'] = $DocInfo->url;

        $summary = $this->parseSummary($summary);

        if (is_array($summary) && !empty($summary)) {
            $document = array_merge($document, $summary);
        }

        $record = new XlegalLawContentRecord();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $raw_content);
        $record->doc_id = md5($c);
        $record->title = $document['title'];
        $record->author = $document['author'];
        $record->content = $document['content'];
        $record->doc_ori_no = $document['doc_ori_no'];
        $record->publish_time = $document['publish_time'];
        $record->t_valid = $document['t_valid'];
        $record->t_invalid = $document['t_invalid'];
        //$record->negs = implode(",", $extract->negs);
        $record->tags = $document['tags'];
        $record->simhash = '';

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
        $record->url = $DocInfo->url;
        $record->url_md5 = md5($DocInfo->url);

        if (gsettings()->debug) {
            var_dump($record);
            exit(0);
        }

        return $record;
    }

    private function parseSummary($text)
    {
        $txts = explode("\n", $text);

        $needles = array(
            "doc_ori_no"  => "发布文号",
            "publish_time"     => "发布日期",
            "t_valid"    => "生效日期",
            "t_invalid"  => "失效日期",
            "tags"      => "所属类别",
            "author"    => "文件来源",
        );

        $summary = array();

        foreach ($txts as $txt) {
            $txt = trim($txt);
            if (!empty($txt)) {
                foreach ($needles as $key => $needle) {
                    $p = mb_strpos($txt, $needle, 0, "UTF-8");
                    if ($p !== false && $p >= 0) {
                        $summary[$key] = trim(mb_ereg_replace("【.*】", "", $txt));
                        break;
                    }
                }
            }
        }

        if (!empty($summary['publish_time'])) {
            $summary['publish_time'] = strtotime($summary['publish_time']);
        }

        if (!empty($summary['t_valid'])) {
            $summary['t_valid'] = strtotime($summary['t_valid']);
        }

        if (!empty($summary['t_invalid'])) {
            $summary['t_invalid'] = strtotime($summary['t_invalid']);
        }

        return $summary;
    }
}