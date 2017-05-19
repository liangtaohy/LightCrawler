<?php

/**
 * 文化部
 * http://zwgk.mcprc.gov.cn/
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/27
 * Time: PM4:50
 */
define("CRAWLER_NAME", "spider-zwgk.mcprc.gov");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderZwgkMcprcGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://zwgk.mcprc.gov.cn/146/list_2398.htm",
    );

    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9]{6,8}_[0-9]+\.htm# i"    => "handleDetailPage",
        "#http://www\.aqsiq\.gov\.cn/xxgk_13386/# i"   => "handleListPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderZwgkMcprcGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        sleep(rand(1,4));
        if (strpos($DocInfo->url, "http://zwgk.mcprc.gov.cn/146/") === false) {
            return true;
        }

        $m_sPageName = '';
        $m_sPageExt = '';
        $pages = 0;

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

        preg_match("#var m_nRecordCount = \"([0-9]+)\";# i", $DocInfo->source, $matches);
        if (!empty($matches) && count($matches) > 1) {
            $pages = ceil(intval($matches['1']) / 20);
        }

        $pageArray = array();
        for($i = 1; $i <= $pages; $i++) {
            if($i == 1){
                $sURL = $m_sPageName . "." . $m_sPageExt;
                $sURL = Formatter::formaturl($DocInfo->url, $sURL);
                $pageArray[] = $sURL;
            } else if ($i <= 9) {
                $sURL = $m_sPageName . "_" . ($i - 1) . "." . $m_sPageExt;
                $sURL = Formatter::formaturl($DocInfo->url, $sURL);
                $pageArray[] = $sURL;
            } else {
                $this_URl = $DocInfo->url;
                $sr = strpos($this_URl, ".6");
                $this_URl_Array = substr($DocInfo->url, $sr, strlen($this_URl));
                $surl = substr($this_URl, 0, strrpos($this_URl, "/") + 1);
                $s = explode("/", $this_URl_Array);

                $curpos = urlencode("主题分类");

                $queries = array(
                    "page"  => $i,
                    "SearchClassInfoId" => '',
                    "surl"      => $surl,
                    "curpos"    => $curpos,
                );

                $length = count($s);
                if($length == 3)
                {
                    $queries["SearchClassInfoId"] = $s[1];
                }
                else if($length == 4)
                {
                    $queries["SearchClassInfoId"] = $s[2];
                }
                else if($length == 5)
                {
                    $queries["SearchClassInfoId"] = $s[3];
                }
                else if($length == 6)
                {
                    $queries["SearchClassInfoId"] = $s[4];
                }

                $params = array();
                foreach ($queries as $key => $query) {
                    if (!empty($query)) {
                        $params[] = "{$key}={$query}";
                    }
                }
                $query = implode("&", $params);
                $sURL = "http://59.252.212.6:8088/govsearch/searPage.jsp?{$query}";
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
        sleep(rand(1,4));
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