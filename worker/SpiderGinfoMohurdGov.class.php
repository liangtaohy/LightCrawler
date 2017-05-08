<?php

/**
 * 住房和城乡建设部
 * http://ginfo.mohurd.gov.cn/
 * User: xlegal
 * Date: 17/5/3
 * Time: AM11:10
 */
define("CRAWLER_NAME", "spider-ginfo.mohurd.gov");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderGinfoMohurdGov extends SpiderFrame
{
    const MAGIC = __CLASS__;
    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://ginfo.mohurd.gov.cn/",
    );

    protected $ContentHandlers = array(
        "#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"  => "handleDetailPage",
        "/\/[\x{4e00}-\x{9fa5}0-9a-zA-Z_\x{3010}\x{3011}\x{FF08}\x{FF09}\]\[]+\.(doc|pdf|txt|xls|ceb)/ui" => "handleAttachment",
    );

    /**
     * SpiderGinfoMohurdGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    // javascript:__doPostBack(&#39;ctl00$lbtPageDown&#39;,&#39;&#39;)
    protected function doPostBack(DOMDocument $document, $eventTarget = '', $eventArgument = '')
    {
        if (empty($eventArgument) || empty($eventArgument)) {
            return false;
        }
        $theForm = array();
        $theForm['__EVENTTARGET'] = $eventTarget;
        $theForm['__EVENTARGUMENT'] = $eventArgument;
        $theForm['ctl00$ddlType1'] = 0;
        $theForm['ctl00$ddlType2'] = 0;
        $theForm['ctl00$HFPageIndex'] = 1;

        $inputs = $document->getElementsByTagName("input");

        $forms = array();
        if (!empty($inputs) && $inputs instanceof DOMNodeList) {
            foreach ($inputs as $input) {
                if ($input->hasAttribute('name')) {
                    $name = $input->getAttribute('name');
                    $value = '';
                    if ($input->hasAttribute('value')) {
                        $value = $input->getAttribute('value');
                    }
                    $forms[$name] = $value;
                }
            }
        }

        $theForm = array_merge($forms, $theForm);

        return $theForm;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo) {
        if (gsettings()->debug) {
            echo "enter " . __METHOD__ . PHP_EOL;
        }

        $countPage = 1;

        for($i=1;$i<=$countPage;$i++) {
            preg_match('#javascript:__doPostBack\((.*?)\)# i', $DocInfo->source, $matches);
            $doPostBack = '';
            if (!empty($matches) && count($matches) > 1) {
                $doPostBack = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $matches[1]);
            }


            if (gsettings()->debug) {
                var_dump($doPostBack);
            }

            $cookies = $DocInfo->cookies;

            $cookiesArr = array();
            foreach ($cookies as $cookie) {
                $cookiesArr[$cookie->name] = $cookie->value;
            }

            $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $DocInfo->source);

            $extract->parse();

            $document = $extract->extractor->domDocument();

            $links = $document->getElementsByTagName("a");
            
            if (!empty($links) && $links instanceof DOMNodeList) {
                $records = array();
                foreach ($links as $li) {
                    if ($li->hasAttribute('href')) {
                        $href = trim($li->getAttribute('href'));
                        if (preg_match("#/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i", $href)) {
                            $href = Formatter::formaturl($DocInfo->url, $href);
                            $record = new stdClass();
                            $record->url = $href;
                            $record->title = $li->nodeValue;
                            $record->refering_url = $DocInfo->url;
                            $records[] = $record;
                        }
                    }
                }
                if (gsettings()->debug) {
                    echo 'urls: ' . json_encode($records, JSON_UNESCAPED_UNICODE);
                } else {
                    $this->insert2urls($records);
                }
            }

            preg_match("/\x{5171}([0-9]+)\x{9875}/u", $DocInfo->source, $matches);

            if (!empty($matches) && count($matches) > 1) {
                $countPage = intval($matches[1]);
            }

            $doPostBack1 = explode(",", $doPostBack);
            $doPostBack = array($document);
            $doPostBack = array_merge($doPostBack, $doPostBack1);
            $theForm = call_user_func_array(array($this, 'doPostBack'), $doPostBack);

            if (gsettings()->debug) {
                var_dump($theForm);
                exit(0);
            }

            sleep(rand(1,5));
            if (empty($theForm)) {
                return array();
            }
            try {
                $res = bdHttpRequest::post("http://ginfo.mohurd.gov.cn/", $theForm, $cookiesArr, array(
                    "User-Agent"    => SpiderFrame::USER_AGENT
                ));

                $DocInfo->source = $res->getBody();
            } catch (Exception $e) {
                continue;
            }
        }

        return array();
    }
}