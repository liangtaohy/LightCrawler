<?php

/**
 * 工商系统行政处理公示
 * User: liangtaohy@163.com
 * Date: 17/4/20
 * Time: PM5:13
 */
define("CRAWLER_NAME", "xzcf.gsxt.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderGsxtXZCF
{
    const MAGIC = __CLASS__;

    const SPIDER_NAME_MD5 = "46f070c22c55bf189977eab5cb8d80cf";

    const USER_AGENT = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36";

    const COOKIE = "__jsluid=55f7fe7508a8edbe9c70e3b088ae777b; UM_distinctid=15b8a6d2935560-0fcb3288fff66f-123b6e5f-1fa400-15b8a6d293624b; tlb_cookie=28doc_8280; JSESSIONID=E41F9E1A2C5313100604452701B3AAF6-n2:-1; CNZZDATA1261033118=708156743-1492675954-%7C1492675954; Hm_lvt_cdb4bc83287f8c1282df45ed61c4eac9=1492676062; Hm_lpvt_cdb4bc83287f8c1282df45ed61c4eac9=1492680392";

    const AJAX_QUERY_INFO_URL = "http://www.gsxt.gov.cn/Affiche-query-info-getAffichePunishmentInfo.html?areaId=";

    const DETAIL_URL = "http://www.gsxt.gov.cn/affiche-query-info-punish-%s.html";

    static $AreaIds = array(
        //100000,
        110000, 120000, 130000, 140000, 150000, 210000, 220000, 230000, 310000, 320000, 330000, 340000, 350000, 360000, 370000, 410000, 420000, 430000, 440000, 450000, 460000, 500000, 510000, 520000, 530000, 540000, 610000, 620000, 630000, 640000, 650000
    );

    /**
     * @param array $records
     */
    protected function insert2urls(array $records)
    {
        $values = array();

        foreach ($records as $record) {
            $map_key = md5($record->url);
            $ctime = Utils::microTime();
            $value = array("priority_level" => 0,
                "distinct_hash" => $map_key,
                "link_raw" => '',
                "linkcode" => '',
                "linktext" => $record->author . "_" . $record->doc_ori_no,
                "refering_url" => $record->refering_url,
                "url_rebuild" => $record->url,
                "is_redirect_url" => 0,
                "url_link_depth" => 1,
                "spider"    => self::SPIDER_NAME_MD5,
                "ctime" => $ctime,
                "mtime" => 0,
            );

            $values[] = $value;
        }

        DaoUrlCache::getInstance()->insert_batch($values);
    }

    /**
     * @throws bdHttpException
     */
    public function run()
    {
        foreach (self::$AreaIds as $areaId) {
            $url = self::AJAX_QUERY_INFO_URL . $areaId;

            $r = bdHttpRequest::get("http://www.gsxt.gov.cn/affiche-query-info-paperall.html", array('uuId'=>4,'areaId'=>$areaId,'FKID'=>0), array(),array('User-Agent' => self::USER_AGENT));
            $cookie = $r->getHeader("Cookie");
            sleep(1);

            $totalPages = 1;
            $i = 1;
            while ($i <= $totalPages) {

                $request = new stdClass();
                $request->draw = $i;
                $request->start = ($i - 1) * 10;
                $request->length = 10;

                $i++;

                $params = json_decode(json_encode($request), true);

                $query = http_build_query($params);
                echo $query . PHP_EOL;
                $content_length = strlen($query);
                try {
                    $res1 = bdHttpRequest::post($url, $params, array(), array(
                        'Cookie'    => !empty($cookie) ? $cookie : self::COOKIE,
                        //'content-type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
                        'User-Agent'    => self::USER_AGENT,
                        'Referer'   => "http://www.gsxt.gov.cn/affiche-query-info-paperall.html?uuId=4&areaId={$areaId}&FKID=0",
                        //'Origin'    => 'http://www.gsxt.gov.cn',
                        //'Host'    => 'http://www.gsxt.gov.cn',
                        //'Accept'    => 'application/json, text/javascript, */*; q=0.01',
                        //'Accept-Encoding'   => 'gzip, deflate',
                        //'Accept-Language'   => 'zh-CN,zh;q=0.8,en;q=0.6',
                        //'X-Requested-With'  => 'XMLHttpRequest',
                        //'Cache-Control'     => 'max-age=0',
                        //'Content-Length'    => $content_length,
                    ));

                    if ($res1->getStatus() != 200) {
                        echo "error: " . json_encode($params) . PHP_EOL;
                        continue;
                    }

                    try {
                        $res = json_decode($res1->getBody(), true);

                        if (is_string($res)) {
                            $res = json_decode($res, true);
                        }

                        if (empty($res['data'])) {
                            return true;
                        }

                        $records = array();

                        $j = 0;
                        foreach ($res['data'] as $case) {
                            $record = new stdClass();
                            $record->refering_url = $url;
                            $record->caseId = $case['caseId'];
                            $record->author = $case['penAuth_CN'];
                            $record->doc_ori_no = $case['penDecNo'];
                            $record->url = sprintf(self::DETAIL_URL, $record->caseId);

                            $res1 = bdHttpRequest::get($record->url, array(), array(), array(
                                'Cookie'    => !empty($cookie) ? $cookie : self::COOKIE,
                                //'content-type'  => 'application/x-www-form-urlencoded; charset=UTF-8',
                                'User-Agent'    => self::USER_AGENT,
                                'Referer'   => "http://www.gsxt.gov.cn/affiche-query-info-paperall.html?uuId=4&areaId={$areaId}&FKID=0",
                                //'Origin'    => 'http://www.gsxt.gov.cn',
                                //'Host'    => 'http://www.gsxt.gov.cn',
                                //'Accept'    => 'application/json, text/javascript, */*; q=0.01',
                                //'Accept-Encoding'   => 'gzip, deflate',
                                //'Accept-Language'   => 'zh-CN,zh;q=0.8,en;q=0.6',
                                //'X-Requested-With'  => 'XMLHttpRequest',
                                //'Cache-Control'     => 'max-age=0',
                                //'Content-Length'    => $content_length,
                            ));

                            if ($res1->getStatus() != 200) {
                                echo "error: " . json_encode($params) . PHP_EOL;
                                return false;
                            }

                            $j++;

                            $extract = new Extractor($res1->getBody(), $record->url);
                            $doc = $extract->document();
                            if (empty($doc)) {
                                echo "content is null: " . $record->url . PHP_EOL;
                                return true;
                            }

                            $overview = $doc->query("//div[@class='main_table']");

                            $htmlFragment = '';
                            if ($overview instanceof DOMNodeList && !empty($overview)) {
                                $overview = $overview->item(0);
                                $doc->formatOutput = true;
                                $htmlFragment = $doc->document->saveHTML($overview);
                                $htmlFragment = preg_replace("#(<!--)|(-->)#", "", $htmlFragment);
                                $pregfind = array("/<script(.*?)>.*<\/script>/siU",'/on(mousewheel|mouseover|click|load|onload|submit|focus|blur)="[^"]*"/i');
                                $pregreplace = array('','');
                                $htmlFragment = preg_replace($pregfind, $pregreplace, $htmlFragment);
                                $htmlFragment = preg_replace("/<iframe.*>.*<\/iframe>/siU", '', $htmlFragment);
                                $c = strip_tags($htmlFragment);
                                echo $c . PHP_EOL;
                            }
                            echo "htmlFragment:\n" . $htmlFragment . PHP_EOL;
                            file_put_contents("dump{$j}.html", $res1->getBody());

                            $records[] = $record;
                        }

                        if (gsettings()->debug) {
                            //var_dump($records);
                            return true;
                        }

                        $this->insert2urls($records);

                        if (!empty($res)) {
                            $request->draw = $res['draw'] + 1;
                            if ($totalPages <= 1) {
                                $totalPages = $res['totalPage'];
                            }
                        }

                        sleep(rand(1,4));

                    } catch(Exception $e) {
                        echo "json_decode exception({$e->getMessage()}): " . json_encode($params) . PHP_EOL;
                    }
                } catch (MongoConnectionException $e) {
                    echo $res1->getBody();
                    if (gsettings()->debug) {
                        return true;
                    }
                }
            }
        }
    }
}