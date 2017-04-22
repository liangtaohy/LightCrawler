<?php

/**
 * 中国人民银行
 * 政务公开
 *
 * 支付许可机构
 *
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/21
 * Time: PM2:07
 */
define("CRAWLER_NAME", "xzcf.gsxt.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderPbcGovOnMac
{
    const COOKIE = "ccpassport=f531961685520380ff09094ddf972ac7; wzwsconfirm=b68890647e767acd78fd8f41fbc47a64; wzwsvtime=1492765972; wzwstemplate=Nw==; wzwschallenge=V1pXU19DT05GSVJNX1BSRUZJWF9MQUJFTDcxNDc1NTM=; _gscu_1042262807=92762079wonm9214; _gscs_1042262807=92762079t4bsju14|pv:17; _gscbrs_1042262807=1";
    public static function run()
    {
        for($i = 1; $i <= 16; $i++) {
            $url = "http://www.pbc.gov.cn/zhengwugongkai/127924/128041/2951606/1923625/1923629/d6d180ae/index{$i}.html";
            $r = bdHttpRequest::get($url, array(), array(),array('Cookie' => self::COOKIE));
            $html = $r->getBody();
            echo $html . PHP_EOL;
            $extract = new Extractor($html, $url);
            $doc = $extract->document();
            $page = $doc->query("//td[@class='hei12jj']/font/a");
            if ($page instanceof DOMNodeList && !empty($page)) {
                $record = array();
                foreach ($page as $link) {
                    $record['cname'] = $link->getAttribute('title');
                    $record['detail_link'] = "http://www.pbc.gov.cn" . $link->getAttribute('href');
                    $d = bdHttpRequest::get($record['detail_link'], array(), array(), array('Cookie' => self::COOKIE));
                    $dextract = new Extractor($d->getBody(), $record['detail_link']);
                    $d = $dextract->document();
                    $portlets = $d->query("//div[@class='portlet']");
                    if ($portlets instanceof DOMNodeList && !empty($portlets)) {
                        foreach ($portlets as $portlet) {
                            $htmlFragment = $dextract->domDocument()->saveHTML($portlet);
                            echo ">>>: " . $htmlFragment . PHP_EOL;
                            file_put_contents(md5($record['detail_link']) . ".html", $htmlFragment);
                        }
                    }
                }
            }
        }
    }
}

SpiderPbcGov::run();