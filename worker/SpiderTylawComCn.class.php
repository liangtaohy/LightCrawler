<?php

/**
 * 天元律师事务所
 * http://www.tylaw.com.cn/CN/Research.aspx?Lan=CN&KeyID=00000000000000000889&MenuID=00000000000000000006
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/8
 * Time: PM5:05
 */
define("CRAWLER_NAME", "spider-www.tylaw.com.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderTylawComCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.tylaw.com.cn/CN/Research.aspx?Lan=CN&MenuID=00000000000000000006",
    );

    protected $ContentHandlers = array(
        "#http://www\.tylaw\.com\.cn/CN/news_content\.aspx\?contentID=[0-9]+# i"    => "handleDetailPage",
        "#/.*\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    // div class="bottom"
    /**
     * SpiderTylawComCn constructor.
     */
    public function __construct()
    {
        parent::__construct();

        ExtractContent::$DefaultSpecialClasses[] = "//div[@class='bottom']";
        ExtractContent::$DefaultSpecialClasses[] = "//div[@class='right_nav']";
        ExtractContent::$DefaultSpecialClasses[] = "//ul[@class='dropdown-menu top_qrcode']";
    }

    public function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $total_page = 24;

        do {
            $extract = new Extractor($DocInfo->source, $DocInfo->url);

            $document = $extract->domDocument();

            $links = $document->getElementsByTagName("a");
            if (!empty($links) && $links instanceof DOMNodeList) {
                $pages = array();
                foreach ($links as $link) {
                    if ($link->hasAttribute("href")) {
                        $href = Formatter::formaturl($DocInfo->url, trim($link->getAttribute("href")));
                        if (preg_match("#http://www\.tylaw\.com\.cn/CN/news_content\.aspx\?contentID=[0-9]+# i", $href)) {
                            $record = new stdClass();
                            $record->url = $href;
                            $record->refering_url = $DocInfo->url;
                            $record->title = trim($link->nodeValue);
                            $pages[] = $record;
                        }
                    }
                }

                if (gsettings()->debug) {
                    var_dump($pages);
                    exit(0);
                } else {
                    var_dump($pages);
                    $this->insert2urls($pages);
                }

            }

            $input_viewstate = $document->getElementById("__VIEWSTATE")->getAttribute("value");
            $__VIEWSTATEGENERATOR = $document->getElementById("__VIEWSTATEGENERATOR")->getAttribute("value");
            $currentPage = $document->getElementById("currentPage")->getAttribute("value");

            $url = $DocInfo->url;

            $purl = parse_url($DocInfo->url);
            $queries = explode("&", $purl['query']);
            $queryMap = array();
            foreach ($queries as $query) {
                $q = explode("=", $query);
                if (count($q) > 1) {
                    $queryMap[$q[0]] = $queryMap[$q[1]];
                }
            }

            $form = array(
                '__VIEWSTATE'   => $input_viewstate,
                '__VIEWSTATEGENERATOR'  => $__VIEWSTATEGENERATOR,
                'Lan'           => 'CN',
                'MenuID'        => $queryMap['MenuID'],
                'KeyID'         => '',
                'currentPage'   => empty($currentPage) ? 2 : intval($currentPage) + 1,
                'contentID'     => '',
                'htmlcontent'   => '',
                'txtPhone'      => '',
                'txt'           => '',
                'keyWord'       => '',
                'SearchYear'    => '',
                'YWLY'          => '',
            );

            sleep(rand(1,4));
            $ret = bdHttpRequest::post($url, $form, array(), array("UserAgent"=>SpiderFrame::USER_AGENT));
            if ($ret->getStatus() == 200) {
                $DocInfo->source = $ret->getBody();
            } else {
                return false;
            }
        } while ($currentPage <= $total_page);
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
        $record->author = "天元律师事务所";
        $record->content = $content;
        $record->doc_ori_no = $extract->doc_ori_no;
        $record->publish_time = $extract->publish_time;
        $record->t_valid = $extract->t_valid;
        $record->t_invalid = $extract->t_invalid;
        $record->negs = implode(",", $extract->negs);
        $record->tags = "律所实务";
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