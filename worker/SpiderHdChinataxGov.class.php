<?php

/**
 * 国家税务总局
 * http://hd.chinatax.gov.cn/guoshui/main.jsp
 *
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/25
 * Time: PM12:41
 */
define("CRAWLER_NAME", "spider-hd.chinatax.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderHdChinataxGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    const AUTH_BASIC  = 'basic';
    const AUTH_DIGEST = 'digest';
    const MAX_PAGE = 10;

    protected $config = array(
        'adapter'			=> 'curl',
        'connect_retry'		=> 3,
        'connect_timeout'   => 10000,
        'timeout'           => 20000,
        'follow_redirects'	=> true,
        'max_redirects'		=> 3,
        'max_response_size'	=> 51200000,

        'protocol_version'  => '1.1',
        'buffer_size'       => 16384,

        'proxy_host'        => '',
        'proxy_port'        => '',
        'proxy_user'        => '',
        'proxy_password'    => '',
        'proxy_auth_scheme' => self::AUTH_BASIC,

        'ssl_verify_peer'   => false,
        'ssl_verify_host'   => false,
        'ssl_cafile'        => null,
        'ssl_capath'        => null,
        'ssl_local_cert'    => null,
        'ssl_passphrase'    => null,

        'use_brackets'		=> true,
        'strict_redirects'	=> false,
    );

    private $form = array(
        "rtoken"    =>  'fgk',
        "shuizhong" => '全部法规',
        "articleRole"   => '',
        "articleField08"    => '',
        "articleField09"    => '',
        "articleField10"    => '',
        "articleField11"    => '',
        "articleField12"    => '',
        "articleField13"    => '',
        "articleField14"    => '',
        "articleField18"    => '否',
        "intvalue"          => "-1",
        "intvalue1"         => 1,
        "initFlag"          => 0,
        "articleField01"    => '',
        "articleField03"    => '',
        "articleField04"    => '',
        "articleField05"    => '',
        "articleField06"    => '',
        "articleField07_s"  => '',
        "articleField07_d"  => '',
        "cPage"             => '',
    );

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        //"http://hd.chinatax.gov.cn/guoshui/main.jsp",
    );

    protected $ContentHandlers = array(
        "#http://hd.chinatax.gov.cn/guoshui/main.jsp# i"    => "handleListPage",
        "#http://hd.chinatax.gov.cn/guoshui/action/GetArticleView1.do\?id=[0-9]+\&flag=1# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    public function __construct()
    {
        parent::__construct();
        $this->_pergecache();
    }

    protected function _pergecache()
    {
        $page = 1;
        $pagesize = 10000;

        $where = array(
            "spider"    => md5(CRAWLER_NAME),
            "processed" => 1,
            "in_process"    => 0,
        );

        $sort = array(
            "id" => "ASC"
        );

        $fields = array(
            "id",
            "url_rebuild",
            "distinct_hash",
        );

        $res = $url_cache = DaoUrlCache::getInstance()->search_data($where, $sort, $page, $pagesize, $fields);

        $pages = $res['pages'];

        $lists = array();
        foreach ($res['data'] as $re) {
            $url = $re['url_rebuild'];
            foreach ($this->ContentHandlers as $pattern => $contentHandler) {
                if ($contentHandler === "handleListPage" || $contentHandler === "void") {
                    if (preg_match($pattern, $url)) {
                        if (!isset($lists[$pattern])) {
                            $lists[$pattern] = array();
                        }

                        $lists[$pattern][] = $re;
                    }
                }
            }
        }

        $ids = array();
        foreach ($lists as $pattern => $list) {
            $total = ceil(count($list) / 3);
            if ($total > self::MAX_PAGE) {
                $total = self::MAX_PAGE;
            }

            for ($i = 0; $i < $total; $i++) {
                $u = $list[$i];
                $ids[] = $u['id'];
            }
        }

        if (gsettings()->debug) {
            var_dump($ids);
            exit(0);
        }
        DaoUrlCache::getInstance()->pergeCacheByIds($ids);
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $extract = new Extractor($DocInfo->source, $DocInfo->url);
        $doc = $extract->document();
        $rtoken = $doc->query("//input[@id='rtoken']");
        if (!empty($rtoken) && $rtoken instanceof DOMNodeList) {
            foreach ($rtoken as $item) {
                $item->hasAttribute("value") ? $this->form['rtoken'] = trim($item->getAttribute("value")) : NULL;
                break;
            }
        }

        $prefix = "http://hd.chinatax.gov.cn/guoshui/action/InitNewArticle.do";

        //echo "post enter\n";
        $cookies = array();

        foreach ($DocInfo->responseHeader->cookies as $item) {
            $cookies[$item->name] = $item->value;
        }

        //var_dump($cookies);
        $response = bdHttpRequest::post($prefix, $this->form,
            array(), array("User-Agent"=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36",
                "Referer" =>"http://hd.chinatax.gov.cn/guoshui/action/InitNewArticle.do",
                "Cookie"    => "ArticleField08=%E5%85%A8%E6%96%87%E5%A4%B1%E6%95%88; ArticleField18=%E5%90%A6; NDo08=notEqual; ArticleRole=0000000; qd80-cookie=qdyy34-80; qd80-cookie=qdyy34-80; JSESSIONID=iC-j7JsJ-ArKD69sp9k5tT6eXMGs6vY14EZKgeiYriQ9M6CNhQ8z!-1125243851; taxCode=localtax; _gscu_12313885=93104053qyk0dr47; _gscs_12313885=93104053pso7xq47|pv:2; _gscbrs_12313885=1; _gscs_627338063=931040542mn18l15|pv:3; _gscbrs_627338063=1; _gscu_627338063=91740436jvh3ga15"));
        //echo "post leave\n";
        $body = $response->getBody();
        $cookies1 = $response->getCookies();

        if ($cookies1) {
            $cookies = array_merge($cookies, $cookies1);
        }

        $this->parseListPage($body, $prefix);

        preg_match("/\x{67E5}\x{8BE2}\x{7ED3}\x{679C}([0-9]+)\x{9875}/ui", $body, $matches);

        $total = 0;

        if (!empty($matches) && count($matches) > 1) {
            $total = intval($matches[1]);
        }

        $total = $total > self::MAX_PAGE ? self::MAX_PAGE : $total;

        for ($i = 2; $i <= $total; $i++) {
            $this->form['cPage'] = $i;
            $response = bdHttpRequest::post($prefix, $this->form,
                array(), array("User-Agent"=> "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36",
                    "Referer" =>"http://hd.chinatax.gov.cn/guoshui/action/InitNewArticle.do",
                    "Cookie"    => "ArticleField08=%E5%85%A8%E6%96%87%E5%A4%B1%E6%95%88; ArticleField18=%E5%90%A6; NDo08=notEqual; ArticleRole=0000000; qd80-cookie=qdyy34-80; qd80-cookie=qdyy34-80; JSESSIONID=iC-j7JsJ-ArKD69sp9k5tT6eXMGs6vY14EZKgeiYriQ9M6CNhQ8z!-1125243851; taxCode=localtax; _gscu_12313885=93104053qyk0dr47; _gscs_12313885=93104053pso7xq47|pv:2; _gscbrs_12313885=1; _gscs_627338063=931040542mn18l15|pv:3; _gscbrs_627338063=1; _gscu_627338063=91740436jvh3ga15"),
                $this->config);
            sleep(rand(1,4));
            $body = $response->getBody();
            $cookies1 = $response->getCookies();
            if ($cookies1) {
                $cookies = array_merge($cookies, $cookies1);
            }
            echo "cPage=" . $i . PHP_EOL;
            $this->parseListPage($body, $prefix);
        }
    }

    private function parseListPage($source, $url)
    {
        $extract = new Extractor($source, $url);
        $doc = $extract->document();

        $hrefs = $doc->query("//a[@class='a_left2']");

        $urls = array();
        if (!empty($hrefs)) {
            foreach ($hrefs as $href) {
                $url_raw = trim($href->nodeValue);
                $urls[] = Formatter::formaturl($url, $url_raw);
            }
        }

        $records = array();

        foreach ($hrefs as $li) {
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

        $this->insert2urls($records);
    }

    protected function insert2urls(array $records)
    {
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

            //echo "detail-url: " . json_encode($value, JSON_UNESCAPED_UNICODE) . PHP_EOL;
            DaoUrlCache::getInstance()->insert($value);
        }
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

        $doc = $extract->extractor->document();

        $whs = $doc->query("//div[@id='wh']");

        if (!empty($whs)) {
            foreach ($whs as $wh) {
                $extract->title = preg_replace("/[\s\x{3000}\x{3010}]+/u", "", trim($wh->nodeValue));
                preg_match("/[\x{FF08}]?([\x{4e00}-\x{9fa5}]{2,20}?)[\[\x{3014}\x{3010}\(]([0-9]+)[\]\x{3015}\x{3011}\)][\x{7B2C}]?([0-9]+)\x{53F7}[\x{FF09}]?/u", $extract->title, $matches);
                if (!empty($matches) && count($matches) > 3) {
                    $extract->doc_ori_no = sprintf("%s(%s)%s号", $matches[1], $matches[2], $matches[3]);
                }
                break;
            }
        }

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