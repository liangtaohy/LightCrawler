<?php

/**
 * http://www.yingkelawyer.com/YKAL/YKALList.aspx?url=251c00e33c31ae78&tN=%E7%9B%88%E7%A7%91%E6%A1%88%E4%BE%8B
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/4
 * Time: AM10:53
 */
define("CRAWLER_NAME", "spider-yingkelawyer.com");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderYingkeLawyerCom
{
    const MAGIC = __CLASS__;
    const GET_MENU_DATA_AJAX_URL = "/Ajax/Nodes/NodesAjax.ashx";
    const HOST = "http://www.yingkelawyer.com";

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.yingkelawyer.com/YKAL/YKALList.aspx?url=251c00e33c31ae78&tN=盈科案例",
    );

    /**
     * SpiderYingkeLawyerCom constructor.
     */
    public function __construct() {}

    protected function getMenuData(array $cookies = array())
    {
        $data = array(
            "_" => SpiderFrame::rand_float(),
            "t" => "get_menu_data",
        );

        try {
            $res = bdHttpRequest::get(self::HOST . self::GET_MENU_DATA_AJAX_URL, $data, $cookies, array("User-Agent" => SpiderFrame::USER_AGENT));
            if ($res->getStatus() == 200) {
                $json = json_decode($res->getBody(), true);
                $menus = array();
                foreach ($json['FirstMenu'] as $item) {
                    if ($item['NodeName'] == '盈科案例') {
                        $menus[] = self::HOST . urldecode($item['UrlName']);
                    }
                }

                if (gsettings()->debug) {
                    var_dump($menus);
                }
                return $menus;
            }
        } catch (Exception $e) {
            return array();
        }

        return array();
    }

    /**
     * Case List
     * 
     * @param $tN
     * @param int $pageIndex
     * @param $location
     * @param array $cookies
     * @return null|stdClass
     */
    protected function caseList($tN, $location, $pageIndex = 1, array $cookies = array())
    {
        $ps = parse_url($location);
        $queries = explode("&", $ps['query']);
        $query_params = array();

        foreach ($queries as $item) {
            $arr = explode("=", $item);
            if (count($arr)==2) {
                $query_params[$arr[0]] = urldecode($arr[1]);
            }
        }

        $caseSearch = '';

        $ajaxCaseList = function () use ($tN, $caseSearch, $pageIndex, $query_params, $location, $cookies) {
            $url = self::HOST . '/Ajax/ArticleAjax/ArticleAjax.ashx';
            $data = array(
                '_'     => SpiderFrame::rand_float(),
                't'     => 'get_article_case_list',
                'title' => $caseSearch,
                'nT'    => $query_params['url'],
                'pageIndex' => $pageIndex,
                'tN'    => $tN,
                'url'   => $location
            );

            return self::ajax($url, $data, $cookies);
        };

        $caseList = $ajaxCaseList();

        if (gsettings()->debug) {
            var_dump($caseList);
        }

        if (!empty($caseList)) {
            foreach ($caseList['table'] as $item) {
                sleep(rand(1,5));
                $this->getContent($item, $cookies);
            }
        }

        if ($caseList['pageIndex'] >= $caseList['pageCount']) {
            return null;
        }

        $page = new stdClass();
        $page->pageIndex = $caseList['pageIndex']++;
        $page->pageCount = $caseList['pageCount'];

        return $page;
    }

    protected function getContent($item, $cookies)
    {
        $url = self::HOST . $item['UrlName'];
        $purl = parse_url($url);
        $query = explode("=", $purl['query']);

        $query_params = array();
        if (count($query)==2) {
            $query_params[$query[0]] = $query[1];
        }

        $res = bdHttpRequest::get($purl['scheme'] . "://" . $purl['host'] . $purl['path'], $query_params, $cookies, array("User-Agent"=>SpiderFrame::USER_AGENT));

        if ($res->getStatus() !== 200) {
            echo 'fatal get ' . $url . 'failed, status ' . $res->getStatus() . PHP_EOL;
            return false;
        }

        $source = $res->getBody();

        if (gsettings()->debug) {
            file_put_contents("dump.html", $url . PHP_EOL);
            file_put_contents("dump.html", $source . PHP_EOL, FILE_APPEND);
        }

        $extract = new Extractor($source, $url);
        $doc = $extract->document();
        $document = $extract->domDocument();
        $view_content = $doc->query("//div[@id='view_content']");

        if (!empty($view_content) && $view_content instanceof DOMNodeList) {
            $source = $document->saveHTML($view_content->item(0));
        }

        $extract1 = new ExtractContent($url, $url, $source);
        $extract1->parse();
        $content = $extract1->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->content = $content;
        $record->title = $item['Title'];
        $record->author = "盈科律师事务所";
        $record->doc_ori_no = '';
        $record->publish_time = strtotime($item['InputTime']);
        $record->tags = "律所实务";
        $record->simhash = '';

        if (empty(gsettings()->debug)) {
            $res = FlaskRestClient::GetInstance()->simHash($c);

            $simhash = '';
            if (isset($res['simhash']) && !empty($res['simhash'])) {
                $simhash = $res['simhash'];
            }

            if (isset($res['repeated']) && !empty($res['repeated'])) {
                echo 'data repeated: ' . $url . ', repeated simhash: ' . $res['simhash1'] .PHP_EOL;
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
        $record->url = $url;
        $record->url_md5 = md5($record->url);

        if (gsettings()->debug) {
            var_dump($record);
            exit(0);
        }

        DaoXlegalLawContentRecord::getInstance()->insert($record);
        return true;
    }

    /**
     * Ajax Request
     * @param $url
     * @param $data
     * @param $cookies
     * @return array|mixed
     */
    public static function ajax($url, $data, $cookies)
    {
        try {
            $res = bdHttpRequest::get($url, $data, $cookies, array("User-Agent" => SpiderFrame::USER_AGENT));
            if ($res->getStatus() == 200) {
                return json_decode($res->getBody(), true);
            }
            return array();
        } catch (Exception $e) {
            return array();
        }
    }

    public function run()
    {
        $res = bdHttpRequest::get("http://www.yingkelawyer.com/", array(), array(), array("User-Agent" => SpiderFrame::USER_AGENT));
        if ($res->getStatus() !== 200) {
            echo "fatal get http://www.yingkelawyer.com/ failed" . PHP_EOL;
            return false;
        }

        $cookies = $res->getCookies();

        $menus = $this->getMenuData($cookies);

        foreach ($menus as $menu) {
            $page = new stdClass();
            $page->pageIndex = 1;
            $page->pageCount = 1;

            $flag = true;

            $purl = parse_url($menu);
            $queries = explode("&", $purl['query']);
            $query_params = array();
            foreach ($queries as $query) {
                $q = explode("=", $query);
                if (count($q) == 2) {
                    $query_params[$q[0]] = $q[1];
                }
            }

            do {
                sleep(rand(1,4));
                $page = $this->caseList($query_params['tN'], $menu, $page->pageIndex, $cookies);
                if (empty($page)) {
                    $flag = false;
                }
            } while ($flag);

        }
    }
}