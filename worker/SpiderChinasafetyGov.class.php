<?php

/**
 * 安全生产监督局
 * http://zfxxgk.chinasafety.gov.cn/portal/source.do?method=getgongkaimuluSelectList&pageSize=30
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/28
 * Time: PM1:40
 */
define("CRAWLER_NAME", "spider-chinasafety.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderChinasafetyGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://zfxxgk.chinasafety.gov.cn/portal/source.do?method=newInfoMore",
        "http://zfxxgk.chinasafety.gov.cn/portal/source.do?method=getgongkaimuluSelectList&pageSize=30"
    );

    protected $ContentHandlers = array(
        "#http://zfxxgk\.chinasafety\.gov\.cn/portal/source\.do\?method=getgongkaimuluSelectList&pageSize=30# i" => "handleListPage",
        "#http://zfxxgk\.chinasafety\.gov\.cn/portal/source\.do\?method=newInfoMore(&currPage=[0-9]+)?# i"   => "handleListPage",
        "#http://zfxxgk\.chinasafety\.gov\.cn/portal/source\.do\?.*id=[0-9]+# i" => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderChinasafetyGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function selInfo($openFileId){
        return '/source.do?location=4&method=detailedInfo&gongkaimulu=true&id=' . $openFileId;
    }

    /**
     * @param $toPageIndex
     * @param $nianfen
     * @param $index_number
     * @param $cid
     * @param $cname
     * @param $org
     * @param $catalogId
     * @param array $cookies
     * @param array $extra_headers
     * @throws Exception
     * @throws bdHttpException
     */
    protected function displaySubmit($toPageIndex, $nianfen, $index_number, $cid, $cname, $org, $catalogId, array $cookies = array(), array $extra_headers = array())
    {
        $form = array(
            "currPage"  => $toPageIndex,
            "nianfen"   => $nianfen,
            "index_number"  => $index_number,
            "cid"   => $cid,
            "cname" => $cname,
            "org"   => $org,
            "catalogId" => $catalogId,
        );

        $method = "getgongkaimuluSelectList";
        $url = '/portal/source.do?method=' . $method . "&pageSize=30";
        $res = bdHttpRequest::post($url, $form, $cookies, $extra_headers);
        if ($res->getStatus() === 200) {
            $body = $res->getBody();
            $extract = new Extractor($body, $url);
            $document = $extract->domDocument();
            $node = $document->getElementById("tableDiv1");
            $links = $node->getElementsByTagName("a");

            $records = array();
            if (!empty($links) && $links instanceof DOMNodeList) {
                foreach ($links as $link) {
                    if ($link->hasAttribute("onclick")) {
                        $onclick = $link->getAttribute("onclick");
                        $articleId = preg_match("#selInf\('([0-9A-Z]+)'\)# i", $onclick, $matches);
                        $d = $this->selInfo($articleId);
                        $record = new stdClass();
                        $record->url = Formatter::formaturl($url, $d);
                        $record->refering_url = $url;
                        $record->title = trim($link->nodeValue);
                        $records[] = $record;
                    }
                }
            }

            if (!empty($records)) {
                $this->insert2urls($records);
            }
        }
    }

    /**
     * @param $document
     * @param $toPageIndex
     * @param $totalPageSize
     * @param $obj3
     */
    protected function goPage($document, $toPageIndex,$totalPageSize,$obj3)
    {
        $toPageIndex = intval($toPageIndex);
        $totalPageSize = intval($totalPageSize);

        $nianfen = $document->getElementById("nianfen")->nodeValue;
        $suoyinhao = $document->getElementById("suoyinhao")->nodeValue;
        $cidhidden = $document->getElementById("cidhidden")->nodeValue;
        $cnamehidden = $document->getElementById("cnamehidden")->nodeValue;

        if($toPageIndex >= $totalPageSize) {//当前页大于最大页数，取最大页数
            $this->displaySubmit($totalPageSize, $nianfen, $suoyinhao, $cidhidden, $cnamehidden, $obj3,'');
            exit(0);
        } else if ($toPageIndex === 0) {//当前页是0，取1
            $this->displaySubmit(1, $nianfen, $suoyinhao, $cidhidden, $cnamehidden, $obj3,'');
        } else {
            $this->displaySubmit($toPageIndex, $nianfen, $suoyinhao, $cidhidden, $cnamehidden, $obj3,'');
        }
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pages = array();
        if (preg_match("#http://zfxxgk\.chinasafety\.gov\.cn/portal/source\.do\?method=newInfoMore# i", $DocInfo->url)) {
            preg_match("/onclick=\"goPage\('([0-9]+)','([0-9]+)'\)\">\x{4E0B}\x{4E00}\x{9875}/ui", $DocInfo->source, $matches);
            if (!empty($matches) && count($matches) > 1) {
                $pages[] = "http://zfxxgk.chinasafety.gov.cn/portal/source.do?method=newInfoMore&currPage=" . $matches[1];
            }

            $extract1 = new Extractor($DocInfo->source, $DocInfo->url);
            $doc = $extract1->document();

            $links = $doc->query("//a[@class='infoTtl']");

            $records = array();
            if (!empty($links) && $links instanceof DOMNodeList) {
                foreach ($links as $link) {
                    if ($link->hasAttribute("onclick")) {
                        $onclick = $link->getAttribute("onclick");
                        $articleId = preg_match("#selInf\('([0-9A-Z]+)'\)# i", $onclick, $matches);
                        $d = $this->selInfo($articleId);
                        $record = new stdClass();
                        $record->url = Formatter::formaturl($url, $d);
                        $record->refering_url = $url;
                        $record->title = trim($link->nodeValue);
                        $records[] = $record;
                    }
                }
            }

            if (!empty($records)) {
                $this->insert2urls($records);
            }

        } else {
            preg_match("/onclick=\"goPage\('([0-9]+)','([0-9]+)','([0-9]+)'\)\">\x{4E0B}\x{4E00}\x{9875}/ui", $DocInfo->source, $matches);

            $extract = new Extractor($DocInfo->source, $DocInfo->url);

            if (!empty($matches) && count($matches) > 3) {
                $this->goPage($extract->domDocument(), $matches[1], $matches[2], $matches[3]);
            }
        }

        if (gsettings()->debug) {
            var_dump($pages);
            exit(0);
        }

        return $pages;
    }
}