<?php

/**
 * 工业与信息化部
 * http://xxgk.miit.gov.cn/gdnps/wjfbindex.jsp
 * User: xlegal
 * Date: 17/4/25
 * Time: PM10:17
 */
define("CRAWLER_NAME", "spider-miit.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";
class SpiderMiitGov
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    const USER_AGENT = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36";

    static $Queries = '{"goPage":3,"orderBy":[{"orderBy":"publishTime","reverse":true},{"orderBy":"orderTime","reverse":true}],"pageSize":10,"queryParam":[{},{},{"shortName":"fbjg","value":"/1/29/1146295/1652858/1652930"}]}';

    const JQUERY_CALLBACK = "jQuery111105347650917390574_1493129706977";
    const JQUERY_SEARCH_INDEX = 'http://xxgk.miit.gov.cn/gdnps/searchIndex.jsp';
    const DETAIL_PREFIX = "http://xxgk.miit.gov.cn/gdnps/wjfbContent.jsp?id=";
    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://xxgk.miit.gov.cn/gdnps/wjfbindex.jsp",
    );

    protected $ContentHandlers = array(
    );

    /**
     * SpiderMiitGov constructor.
     */
    public function __construct()
    {
    }

    public function getPageList()
    {
        self::$Queries = json_decode(self::$Queries);
        $r = bdHttpRequest::get(self::$SeedConf[0], array(), array(), array('User-Agent'=>self::USER_AGENT));
        $cookies = $r->getCookies();

        $pages = 1;
        for ($i = 1; $i <= self::MAX_PAGE; $i++) {
            self::$Queries->goPage = $i;
            $post_params = array(
                "params"    => json_encode(self::$Queries),
                "callback"  => self::JQUERY_CALLBACK,
                "_" => Utils::microTime(),
            );

            $c = bdHttpRequest::get(self::JQUERY_SEARCH_INDEX, $post_params, $cookies, array('User-Agent'=>self::USER_AGENT));

            sleep(rand(1, 5));

            $cookies = array_merge($cookies, $c->getCookies());
            preg_match("#" . self::JQUERY_CALLBACK . "\((.*)\)# i", $c->getBody(), $matches);
            if (!empty($matches) && count($matches) > 1) {
                $data = json_decode($matches[1], true);
                foreach ($data['resultMap'] as $item) {
                    $url = self::DETAIL_PREFIX . $item['id'];
                    $record = new XlegalLawContentRecord();

                    $extract = new ExtractContent($url, $url, $item['htmlContent']);

                    $extract->parse();

                    $content = $extract->getContent();

                    $c = preg_replace("/[\s\x{3000}]+/u", "", $content);

                    $record->doc_ori_no = $item['wh'];

                    preg_match(ExtractContent::$DefaultDocOriNoPatterns[0], $record->doc_ori_no, $matches);
                    if (!empty($matches) && count($matches) > 3) {
                        $record->doc_ori_no = sprintf("%s(%s)%s号", $matches[1], $matches[2], $matches[3]);
                    }

                    $record->author = $item['fwjg'];
                    $record->publish_time = strtotime(sprintf("%s-%s-%s", substr($item['publishTime'], 0, 4), substr($item['publishTime'], 4, 2), substr($item['publishTime'], 6, 2)));
                    $record->tags = $item['ztfl'] . ',' . $item['gwzl'];
                    $record->title = $item['title'];
                    $record->content = base64_encode(gzdeflate($item['htmlContent']));
                    $record->doc_id = md5($c);

                    if (!empty($extract->attachments)) {
                        $record->attachment = json_encode($extract->attachments, JSON_UNESCAPED_UNICODE);
                    }

                    if (!empty($extract->negs)) {
                        $record->negs = implode(",", $extract->negs);
                    }

                    $record->url = $url;
                    $record->url_md5 = md5($url);
                    $record->type = DaoSpiderlLawBase::TYPE_HTML_FRAGMENT;
                    $record->status = 1;
                    $record->t_valid = $record->publish_time;

                    //echo json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL;
                    DaoXlegalLawContentRecord::getInstance()->insert($record);
                }

                if ($pages === 1) {
                    $pages = $data['totalPageNum'];
                }
                if (gsettings()->debug) {
                    exit(0);
                }
            }
        }
    }

    public function run()
    {
        $this->getPageList();
    }
}