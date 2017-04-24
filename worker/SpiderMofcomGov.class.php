<?php

/**
 * 商务部公开目录
 * http://file.mofcom.gov.cn/search.shtml?class=01
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/24
 * Time: AM10:20
 */
define("CRAWLER_NAME", "spider-mep.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderMofcomGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://file.mofcom.gov.cn/",
    );

    protected $ContentHandlers = array(
        "#http://file\.mofcom\.gov\.cn/search\.shtml\?class=[0-9]+# i" => "handleListPage",
        "#http://file\.mofcom\.gov\.cn/article/gkml/[0-9]{6}/[0-9]+\.shtml# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderMofcomGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array|bool
     */
    public function computePages(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pagesPatterns = array(
            "#var maxpage=[\"]?([0-9]+)?[\"]?;# i"
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

        if (empty($pages)) {
            echo "FATAL get pages failed: " . $DocInfo->url . PHP_EOL;
            return false;
        }

        $res = array();

        $res['pages'] = intval($pages);

        return $res;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pager = $this->computePages($DocInfo);

        $urlinfo = parse_url($DocInfo->url);

        $queries = explode("&", $urlinfo['query']);
        $q = array();
        foreach ($queries as $query) {
            $s = explode("=", $query);
            if (count($s) == 2) {
                $q[$s[0]] = $s[1];
            } else {
                $q[$s[0]] = '';
            }
        }

        $pages = array();

        for ($i = 1; $i <= $pager['pages']; $i++)
        {
            $q['pageNum'] = $i;
            $query_str = http_build_query($q);
            $pages[] = $urlinfo['scheme'] . "://" . $urlinfo['host'] . $urlinfo['path'] . "?" . $query_str;
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