<?php

/**
 * http://www.sda.gov.cn/WS01/CL0463/#
 * 食品药品监督管理局
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/24
 * Time: PM10:48
 */
define("CRAWLER_NAME", "spider-sda.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";
class SpiderSdaGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.sda.gov.cn/WS01/CL0463/",
    );

    protected $ContentHandlers = array(
        "#http://www.sda.gov.cn/WS01/CL0463/# i"    => "handleListPage",
        "#http://www.sda.gov.cn/wbpp/generalsearch# i"  => "handleListPage",
        "#http://www.sda.gov.cn/WS01/CL[0-9]+/[0-9]+\.html# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    private $record = null;
    private $mytarget = null;
    private $dateFormat = null;
    private $titleLength = null;
    private $subTitleFlag = null;
    private $classStr = null;
    private $CLID = null;
    private $OPTIONS_VALUE10 = null;
    private $CTITLE = null;
    private $CTIME2 = null;

    /**
     * SpiderSdaGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param $mylocation
     * @param $tableName
     * @param $colNum
     * @param $qryNum
     * @param $qryidstr
     * @param $qryvalue
     * @param $curpage
     * @return string
     */
    protected function goSearch2 ($mylocation,$classStr,$tableName,$colNum,$qryNum,$qryidstr,$qryvalue,$curpage) {
        $qryvalue = strip_tags($qryvalue);

        $mylocation = $mylocation . "&tableName=" . $tableName;
        $mylocation = $mylocation . "&colNum=" . $colNum;
        $mylocation = $mylocation . "&qryNum=" . $qryNum;
        //当前页
        $mylocation = $mylocation . "&curPage=" . $curpage;
        $mylocation = $mylocation . "&qryidstr=" . $qryidstr;
        $mylocation = $mylocation . "&qryValue=|||null,null";

        if ($this->record) {
            $mylocation .= "&record=" . $this->record;
        }

        if ($this->mytarget) {
            $mylocation .= "&mytarget=" . $this->mytarget;
        }
        if ($this->dateFormat) {
            $mylocation .= "&dateFormat=" . $this->dateFormat;
        }
        if ($this->titleLength) {
            $mylocation .= "&titleLength=" . $this->titleLength;
        }
        if ($this->subTitleFlag) {
            $mylocation .= "&subTitleFlag=" . $this->subTitleFlag;
        }
        if ($this->classStr) {
            $mylocation .= "&classStr=" . $this->classStr;
        }
        $mylocation .= "&gelqryType=1";

        return $mylocation;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pageUrls = array();

        $source = '';
        $pageNum = 1;

        $isAjax = strpos($DocInfo->url, "http://www.sda.gov.cn/wbpp/generalsearch");
        if ($isAjax !== false) {
            $charset = $DocInfo->responseHeader->charset;

            if (isset($charset) && !empty($charset)) {
                $source = sprintf('<META content="text/html; charset=%s" http-equiv="Content-Type" />', $charset);
            }
        }

        $source .= $DocInfo->source;

        $extract = new Extractor($source, $DocInfo->url);
        $document = $extract->domDocument();

        if ($isAjax === false) {
            if ($element = $document->getElementById("record")) {
                $element->hasAttribute('value') ? $this->record = $element->getAttribute('value') : $this->record = 'null';
            }

            if ($element = $document->getElementById("mytarget")) {
                $element->hasAttribute('value') ? $this->mytarget = $element->getAttribute('value') : $this->mytarget = 'null';
            }

            if ($element = $document->getElementById("dateFormat")) {
                $element->hasAttribute('value') ? $this->dateFormat = $element->getAttribute('value') : $this->dateFormat = 'null';
            }

            if ($element = $document->getElementById("titleLength")) {
                $element->hasAttribute('value') ? $this->titleLength = $element->getAttribute('value') : $this->titleLength = 'null';
            }

            if ($element = $document->getElementById("subTitleFlag")) {
                $element->hasAttribute('value') ? $this->subTitleFlag = $element->getAttribute('value') : $this->subTitleFlag = 'null';
            }

            if ($element = $document->getElementById("classStr")) {
                $element->hasAttribute('value') ? $this->classStr = $element->getAttribute('value') : $this->classStr = 'null';
            }

            if ($element = $document->getElementById("CLID")) {
                $element->hasAttribute('value') ? $this->CLID = $element->getAttribute('value') : $this->CLID = 'null';
            }

            if ($element = $document->getElementById("OPTIONS_VALUE10")) {
                $element->hasAttribute('value') ? $this->OPTIONS_VALUE10 = $element->getAttribute('value') : $this->OPTIONS_VALUE10 = 'null';
            }

            if ($element = $document->getElementById("CTITLE")) {
                $element->hasAttribute('value') ? $this->CTITLE = $element->getAttribute('value') : $this->CTITLE = 'null';
            }

            if ($element = $document->getElementById("CTIME2")) {
                $element->hasAttribute('value') ? $this->CTIME2 = $element->getAttribute('value') : $this->CTIME2 = 'null';
            }

            $pageUrls[] = $this->goSearch2('http://www.sda.gov.cn/wbpp/generalsearch?sort=true&sortId=CTIME&record=10&columnid=CLID|OPTIONS_VALUE10|CTITLE|CTIME2&relation=MUST|MUST|MUST|MUST',$this->classStr,'Region','4','4','CLID|OPTIONS_VALUE10|CTITLE|CTIME2',$this->CLID . '|' . $this->OPTIONS_VALUE10 . '|' . $this->CTITLE . '|' . $this->CTIME2, $pageNum);
        } else {
            $doc = $extract->document();
            $pages = $doc->query("//td[@class='pageTdSTR15']");

            var_dump($pages);
            $total = 0;
            if (!empty($pages) && $pages instanceof DOMNodeList) {
                foreach ($pages as $page) {
                    $value = $page->nodeValue;
                    echo $value . PHP_EOL;
                    preg_match("/\x{5171}([0-9]+)\x{9875}/u", $value, $matches);
                    if (count($matches) > 1) {
                        $total = intval($matches[1]);
                        break;
                    }
                }
            }

            for ($i = 2; $i <= $total; $i++) {
                $pageNum++;
                $pageUrls[] = $this->goSearch2('http://www.sda.gov.cn/wbpp/generalsearch?sort=true&sortId=CTIME&record=10&columnid=CLID|OPTIONS_VALUE10|CTITLE|CTIME2&relation=MUST|MUST|MUST|MUST',$this->classStr,'Region','4','4','CLID|OPTIONS_VALUE10|CTITLE|CTIME2',$this->CLID . '|' . $this->OPTIONS_VALUE10 . '|' . $this->CTITLE . '|' . $this->CTIME2, $pageNum);
            }
        }

        if (gsettings()->debug) {
            var_dump($pageUrls);
        }
        return $pageUrls;
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
        $record->url_md5 = md5($extract->baseurl);

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

//curl "http://www.sda.gov.cn/wbpp/generalsearch?sort=true&sortId=CTIME&columnid=CLID|OPTIONS_VALUE10|CTITLE|CTIME2&relation=MUST|MUST|MUST|MUST&tableName=Region&colNum=4&qryNum=4&curPage=1&qryidstr=CLID|OPTIONS_VALUE10|CTITLE|CTIME2&qryValue=|||null,null&record=10&mytarget=~blank&dateFormat=yyyy-MM-dd&titleLength=-1&subTitleFlag=0&classStr=|-1|-1|ListColumnClass5|LawListSub5|listnew5|listtddate5|listmore5|distance5|classtab5|classtd5|pageTdSTR5|pageTdSTR5|pageTd5|pageTdF5|pageTdE5|pageETd5|pageTdGO5|pageTdGOTB5|pageGOButton5|pageDatespan5|pagestab5|pageGOText5&gelqryType=1" -H "User-Agent:Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36"