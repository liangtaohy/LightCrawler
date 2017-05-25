<?php

/**
 * 中国证券投资基金协会
 * http://www.amac.org.cn/xhgg/tzgg/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/9
 * Time: PM5:32
 */
define("CRAWLER_NAME", "spider-anjielaw.com");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderAmacOrgCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.amac.org.cn/xhgg/zlgzfb/index.shtml",
    );

    protected $ContentHandlers = array(
        "#http://www\.amac\.org\.cn/xhgg/zlgzfb/[0-9]+\.shtml# i"   => "handleDetailPage",
        "#http://www\.amac\.org\.cn/xhgg/zlgzfb/index([_0-9]+)?\.shtml# i"    => "handleListPage",
        "#http://www\.amac\.org\.cn/cms/contentcore/resource/download\?ID=[0-9]+# i" => "handleAttachment",
    );

    /**
     * SpiderAmacOrgCn constructor.
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

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool|XlegalLawContentRecord
     */
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $doc = $extract->getExtractor()->extractor->document();
        $title = $doc->query("//div[@class='ldT']")->item(0)->nodeValue;
        $document = $extract->getExtractor()->extractor->domDocument();

        $extract->parse();

        $content = $extract->getContent();

        $links = $document->getElementsByTagName("a");
        if (!empty($links) && $links instanceof DOMNodeList) {
            foreach ($links as $link) {
                if ($link->hasAttribute("href")) {
                    $href = trim($link->getAttribute("href"));
                    if (preg_match("#http://www\.amac\.org\.cn/cms/contentcore/resource/download\?ID=[0-9]+# i", $href)) {
                        $r = array();
                        $r['title'] = $link->getAttribute("title");
                        $r['url'] = $href;
                        echo $href . PHP_EOL;
                        $extract->attachments[] = $r;
                    }
                }
            }
        }

        $publish_time = $doc->query("//div[@class='ldDate']")->item(0)->nodeValue;
        preg_match("#([0-9\-]+)# i", $publish_time, $matches);
        if (!empty($matches) && count($matches) > 1) {
            $publish_time = strtotime($matches[1]);
        }

        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = $title;
        $record->author = !empty($extract->author) ? $extract->author : "中国证券投资基金业协会";
        $record->content = $content;
        $record->doc_ori_no = $extract->doc_ori_no;
        $record->publish_time = !empty($extract->publish_time) ? $extract->publish_time : $publish_time;
        $record->t_valid = $extract->t_valid;
        $record->t_invalid = $extract->t_invalid;
        $record->negs = implode(",", $extract->negs);
        $record->tags = "行业规定";
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