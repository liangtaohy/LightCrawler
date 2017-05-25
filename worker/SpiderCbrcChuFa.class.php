<?php

/**
 * 银监会 - 行政处罚
 * http://www.cbrc.gov.cn/chinese/home/docViewPage/110002.html - 银监会机关
 * http://www.cbrc.gov.cn/zhuanti/xzcf/get2and3LevelXZCFDocListDividePage//1.html - 银监局
 * http://www.cbrc.gov.cn/zhuanti/xzcf/get2and3LevelXZCFDocListDividePage//2.html - 银监分局
 * User: xlegal
 * Date: 17/4/20
 * Time: AM11:29
 */

define("CRAWLER_NAME", "spider-cbrc.gov.cn");

require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCbrcChuFa extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 20;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.cbrc.gov.cn/chinese/home/docViewPage/110002.html",
        "http://www.cbrc.gov.cn/zhuanti/xzcf/get2and3LevelXZCFDocListDividePage//1.html",
        "http://www.cbrc.gov.cn/zhuanti/xzcf/get2and3LevelXZCFDocListDividePage//2.html",
    );

    protected $ContentHandlers = array(
        "#http://www.cbrc.gov.cn/chinese/home/docViewPage/110002(\.html|\&current=[0-9]+)# i" => "handleListPage",
        "#http://www.cbrc.gov.cn/zhuanti/xzcf/get2and3LevelXZCFDocListDividePage//1\.html(\?current=[0-9]+)?# i"   => "handleListPage",
        "#http://www.cbrc.gov.cn/zhuanti/xzcf/get2and3LevelXZCFDocListDividePage//2\.html(\?current=[0-9]+)?# i"    => "handleListPage",
        "#http://www.cbrc.gov.cn/chinese/home/docView/(xzcf_)?[A-Z0-9]+\.html# i" => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderDyChinasarftGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->_pergecache();
    }

    protected function _pergecache()
    {
        $page = 1;
        $pagesize = 20000;

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
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        return array();
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool|XlegalLawContentRecord
     */
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $charset = "utf-8";
        $source = $DocInfo->source;
        if (!empty($charset)) {
            $source = '<meta http-equiv="Content-Type" content="text/html; charset=' . $charset . '"/>'. "\n" . $source;
        }

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $extract->parse();

        $extract->parseSummary($extract->text);

        $doc = $extract->extractor->document();

        if (empty($doc)) {
            echo "content is null: " . $DocInfo->url . PHP_EOL;
            return true;
        }

        $n_cent = $doc->query("//div[@class='Section1']|//div[@class='WordSection1']|//div[@class='Section0']");

        $htmlFragment = '';
        if ($n_cent instanceof DOMNodeList && !empty($n_cent)) {
            $n_cent = $n_cent->item(0);
            $doc->formatOutput = true;
            $htmlFragment = $doc->document->saveHTML($n_cent);
        }

        $content = $extract->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($extract->title) ? $extract->title : $extract->guessTitle();
        $record->author = $extract->author;
        $record->content = !empty($htmlFragment) ? base64_encode(gzdeflate($htmlFragment, 9)) : $content;
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


        $record->type = !empty($htmlFragment) ? DaoSpiderlLawBase::TYPE_HTML_FRAGMENT : DaoSpiderlLawBase::TYPE_TXT;
        $record->status = 1;
        $record->url = $extract->baseurl;
        $record->url_md5 = md5($extract->url);

        if (gsettings()->debug) {
            echo "raw: " . implode("\n", $extract->text) . PHP_EOL;
            $index_blocks = $extract->indexBlock($extract->text);
            echo implode("\n", $index_blocks) . PHP_EOL;
            var_dump($record);
            echo gzinflate(base64_decode($record->content)) . PHP_EOL;
            return false;
        }
        echo "insert data: " . $record->doc_id . PHP_EOL;
        return $record;
    }
}