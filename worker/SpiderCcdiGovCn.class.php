<?php

/**
 * 监察部（中共中央纪律检查委）
 * http://www.ccdi.gov.cn/fgk/index
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/19
 * Time: AM12:01
 */
define("CRAWLER_NAME", "spider-ccdi.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCcdiGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.ccdi.gov.cn/fgk/index",
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#http://www\.ccdi\.gov\.cn/fgk/law_display/[0-9]+# i"    => "handleDetailPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment"
    );

    public $maxPages = -1;

    /**
     * SpiderCcdiGovCn constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    // searchFrom_3
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $cookies = array();
        $extra_headers = array(
            'Cookie' => 'JSESSIONID=wKgIBgBQWR5-MavnLlb9wEoSn1MiGuSBHVYA; _gscu_1507282980=95122790orea3f18; _gscbrs_1507282980=1; NSC_DDEJ_XFC_MC=ffffffffc3a0190145525d5f4f58455e445a4a423660',
            'User-Agent'    => SpiderFrame::USER_AGENT,
            "Referer"   => "http://www.ccdi.gov.cn/fgk/law_pagenumb"
        );

        foreach ($DocInfo->cookies as $item) {
            $cookies[$item->name] = $item->value;
        }

        preg_match("#onclick=\"submitForm_1\(([0-9]+),1265\)\"# i", $DocInfo->source, $totals);

        $total = 1;

        if (!empty($totals) && count($totals) > 1) {
            $total = intval($totals[1]);
        }

        $page = 1;

        while ($page <= $total) {
            $extract = new Extractor($DocInfo->source, $DocInfo->url);
            $document = $extract->domDocument();

            // Get All Urls
            $links = $document->getElementsByTagName("a");
            if (!empty($links) && $links instanceof DOMNodeList) {
                foreach ($links as $link) {
                    if ($link->hasAttribute("onclick")) {
                        $onclick = $link->getAttribute("onclick");
                        preg_match("#submitForm_0\(([0-9]+)\)# i", $onclick, $matches);

                        if (!empty($matches) && count($matches) > 1) {
                            sleep(rand(1,5));
                            // Get Detail Page - begin
                            $form = $document->getElementById("searchFrom_" . $matches[1]);
                            $inputs = $form->getElementsByTagName("input");
                            $formParams = array();
                            foreach ($inputs as $input) {
                                $name = $input->getAttribute('name');
                                $value = $input->getAttribute('value');
                                $formParams[$name] = $value;
                            }

                            $action = $form->getAttribute("action");

                            $detail_url = Formatter::formaturl($DocInfo->url, $action);

                            $record = new stdClass();
                            $record->url_md5 = md5($detail_url);
                            if (DaoXlegalLawContentRecord::getInstance()->ifUrlMd5Existed($record)) {
                                continue;
                            }
                            if (gsettings()->debug) {
                                var_dump($formParams);
                                var_dump($detail_url);
                                var_dump($cookies);
                            }

                            try {
                                $r = bdHttpRequest::post($detail_url, $formParams, $cookies, $extra_headers);

                                if ($r->getStatus() === 200) {
                                    $cookies = array_merge($cookies, $r->getCookies());
                                    $docinfo = new PHPCrawlerDocumentInfo();
                                    $docinfo->source = $r->getBody();
                                    $docinfo->url = $detail_url;

                                    $this->handleDetailPage($docinfo);
                                }
                            } catch (Exception $e) {
                                continue;
                            }

                            // Get Detail Page - end
                        }
                    }
                }
            }

            // Get Next Page
            $searFrom_3 = $document->getElementById("searchFrom_3");
            $childs = $searFrom_3->childNodes;

            $action = $searFrom_3->getAttribute("action");

            $url = Formatter::formaturl($DocInfo->url, $action);

            $formData = array();

            foreach ($childs as $child) {
                if ($child->nodeName == 'input') {
                    $name = $child->getAttribute('name');
                    $value = $child->getAttribute('value');
                    $formData[$name] = trim($value);
                }
            }

            if (gsettings()->debug) {
                var_dump($formData);
                var_dump($url);
                exit(0);
            }

            sleep(rand(1,5));
            try {
                $res = bdHttpRequest::post($url, $formData, $cookies, $extra_headers);
            } catch (Exception $e) {
                continue;
            }

            if ($res->getStatus() == 200) {
                $cookies = array_merge($cookies, $res->getCookies());
                $DocInfo->source = $res->getBody();
                $DocInfo->url = $url;
            }

            $page++;

            if ($this->maxPages > 0 && $page > $this->maxPages) {
                break;
            }
        }
    }
}