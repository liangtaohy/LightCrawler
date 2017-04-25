<?php

/**
 * 国家新闻出版广电总局
 * http://www.gapp.gov.cn/govservice/1959.shtml // 行政审批
 *
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/24
 * Time: PM12:53
 */
define("CRAWLER_NAME", "spider-gap.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderGapGov
{
    const MAGIC = __CLASS__;

    const USER_AGENT = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36";

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.gapp.gov.cn/govservice/1959.shtml", // 行政审批
    );

    protected $ContentHandlers = array();

    protected $cookie = '';

    protected $path = '';
    
    

    /**
     * SpiderGapGov constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param $url
     */
    public function setFeed($url)
    {
        self::$SeedConf = array($url);
    }

    /**
     * @throws Exception
     * @throws bdHttpException
     */
    public function run()
    {
        foreach (self::$SeedConf as $item) {
            $r = bdHttpRequest::get($item, array(), array(),array('User-Agent' => self::USER_AGENT));
            $this->cookie = $r->getHeader("Cookie");

            $body = $r->getBody();

            $path = "/sitefiles/services/wcm/dynamic/output.aspx?publishmentSystemID=35&";

            if (preg_match("#var url = \"(.*?)\"# i", $body, $s) !== false) {
                if (count($s) > 1) {
                    $this->path = $s[1];
                }
            }

            if (empty($this->path)) {
                continue;
            }

            $pages = array();
            $r = preg_match_all("#var pars = \"(.*?)\"# i", $body, $matches);
            if (!empty($r) && count($matches) > 1) {
                foreach ($matches[1] as $match) {
                    $match = $match . Utils::microTime();
                    $q = explode("&", $match);
                    $queries = array();

                    foreach ($q as $item) {
                        $query = explode("=", $item);
                        if (count($query)>1) {
                            $queries[$query[0]] = $query[1];
                        } else {
                            $queries[$query[0]] = '';
                        }
                    }

                    if (isset($queries['ajaxDivID']) && !empty($queries['ajaxDivID'])) {
                        $ajaxDivID = $queries['ajaxDivID'];
                        $x = preg_match("#stlDynamic_" . $ajaxDivID . "\(([0-9]+)\);# i", $body, $m);
                        if (!empty($x) && count($m) > 1) {
                            $pageNum = intval($m[1]);

                            if ($pageNum) {
                                $queries['pageNum'] = $pageNum;
                            }
                        }
                    }

                    $pages[] = $queries;
                }
                $this->requestPages($pages);
            }
        }
    }

    /**
     * @param array $pages
     */
    public function requestPages(array $pages =  array())
    {
        foreach ($pages as $page) {
            $queries = $page;
            try {
                $r = bdHttpRequest::post('http://www.gapp.gov.cn' . $this->path, $queries, empty($this->cookie) ? array() : $this->cookie, array('User-Agent' => self::USER_AGENT));
                $body = $r->getBody();

                $this->parseList($body, 'http://www.gapp.gov.cn' . $this->path);

                sleep(rand(1, 6));
                if (preg_match("/\x{5171}([0-9]+)\x{9875}/u", $body, $pageNums) !== false) {
                    if (count($pageNums) > 1) {
                        $pageNums = intval($pageNums[1]);
                        for ($i=1; $i<=$pageNums; $i++) {
                            $queries['timeStamp'] = Utils::microTime();
                            $queries['pageNum'] = $i;

                            try {
                                $r1 = bdHttpRequest::post('http://www.gapp.gov.cn' . $this->path, $queries, empty($this->cookie) ? array() : $this->cookie, array('User-Agent' => self::USER_AGENT));
                                $body1 = $r1->getBody();

                                $this->parseList($body1, 'http://www.gapp.gov.cn' . $this->path);
                                sleep(rand(1,6));
                            } catch (Exception $e) {
                                sleep(10);
                                continue;
                            }
                        }
                    }
                }

                unset($matches);
            } catch (Exception $e) {
                sleep(10);
                continue;
            }
        }
    }

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
                "linktext" => $record->title,
                "refering_url" => $record->refering_url,
                "url_rebuild" => $record->url,
                "is_redirect_url" => 0,
                "url_link_depth" => 1,
                "spider"    => md5(CRAWLER_NAME),
                "ctime" => $ctime,
                "mtime" => 0,
            );

            echo "detail-url: " . json_encode($value, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            DaoUrlCache::getInstance()->insert($value);
        }
    }

    public function parseList($source, $url)
    {
        $source = '<meta http-equiv="Content-Type" content="text/html; charset=' . 'UTF-8' . '"/>'. "\n" . $source;
        $extract = new Extractor($source, $url);

        $doc = $extract->document();

        $lis = $doc->query("//li/a[@class='kk']");

        file_put_contents("dump.html", $source, FILE_APPEND);

        if (!empty($lis) && $lis instanceof DOMNodeList) {
            $records = array();
            foreach ($lis as $li) {
                if ($li->hasAttribute('href')) {
                    $href = trim($li->getAttribute('href'));
                    $href = Formatter::formaturl($url, $href);
                    $record = new stdClass();
                    $record->url = $href;
                    $record->title = $li->nodeValue;
                    $record->refering_url = $url;
                    $records[] = $record;
                }
            }
            echo 'urls: ' . json_encode($records, JSON_UNESCAPED_UNICODE);
            $this->insert2urls($records);
        }
    }
}