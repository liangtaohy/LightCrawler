<?php

/**
 * 海关总署
 * http://www.customs.gov.cn/publish/portal0/tab49661/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/2
 * Time: PM10:53
 */
define("CRAWLER_NAME", "spider-customs.gov");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCustomsGov extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.customs.gov.cn/publish/portal0/tab49661/",
    );

    protected $ContentHandlers = array(
        "#http://www\.customs\.gov\.cn/publish/portal[0-9]+/tab(49659|49660|49661|70755|70757)# i"  => "void",
        "#http://www\.customs\.gov\.cn/publish/portal[0-9]+/tab[0-9]+/module[0-9]+/page[0-9]+\.htm# i" => "void",
        "#http://[a-z]+\.customs\.gov\.cn/publish/portal[0-9]+/tab[0-9]+/info[0-9]+\.htm# i" => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    protected $LinkTextHandlers = array(
        "/^[\x{4e00}-\x{9fa5}]{2,4}\x{6D77}\x{5173}$/u"    => "handleListPage",
        "/^\x{4E0B}\x{4E00}\x{9875}$/u" => "handleListPage",
    );

    /**
     * SpiderCustomsGov constructor.
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

    // x6D77;&#x5173;&#x884C;&#x653F;&#x5904;&#x7F5A;&#x51B3;&#x5B9A;&#x4E66;
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $extract->parse();

        $doc = $extract->extractor->document();
        $content_node = $doc->query("//span[@id='zoom']");

        $content = '';
        if (!empty($content_node) && $content_node instanceof DOMNodeList) {
            $content = $extract->toHTML($content_node->item(0));
            $extract1 = new ExtractContent($DocInfo->url, $DocInfo->url, $content);
            $extract1->parse();
            $content = $extract1->getContent();
        }

        if (empty($content)) {
            $content = $extract->getContent();
        }

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