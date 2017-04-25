<?php

/**
 * 国家新闻出版广电总局
 * http://www.gapp.gov.cn/zongshu/serviceContent1.shtml?ID=64761
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/24
 * Time: PM4:39
 */
define("CRAWLER_NAME", md5("spider-gap.gov.cn"));
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderDetailGapGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    const USER_AGENT = "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/49.0.2623.87 Safari/537.36";

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        //"http://www.gapp.gov.cn/zongshu/serviceContent1.shtml?ID=64761", // 行政审批
    );

    protected $ContentHandlers = array(
        "#http://www\.gapp\.gov\.cn/zongshu/serviceContent[0-9]+\.shtml\?ID=[0-9]+# i"  => "handleDetailPage",
        "#http://www\.gapp\.gov\.cn/govservice/[0-9]+/[0-9]+\.shtml# i"   => "handleDetailPage",
    );

    protected $path = '';
    /**
     * SpiderDetailGapGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $urlinfo = parse_url($DocInfo->url);

        $source = $DocInfo->source;

        if (preg_match("#var url = \"(.*?)\"# i", $source, $s) !== false) {
            if (count($s) > 1) {
                $this->path = $s[1];
            }
        }

        if (empty($this->path)) {
            return false;
        }

        $r = preg_match("#var pars = \"(.*?)\"# i", $source, $matches);
        $queries = array();
        if (!empty($r) && count($matches) > 1) {
            $pars = $matches[1];
            $q = explode("&", $pars);

            foreach ($q as $item) {
                $query = explode("=", $item);
                if (count($query)>1) {
                    $queries[$query[0]] = $query[1];
                } else {
                    $queries[$query[0]] = '';
                }
            }
        }

        $query = isset($urlinfo['query']) ? $urlinfo['query'] : '';

        $url = $this->path . $query;

        $r = bdHttpRequest::post('http://www.gapp.gov.cn' . $url, $queries, array(), array('User-Agent' => self::USER_AGENT));
        $body = $r->getBody();

        $extract = new ExtractContent('http://www.gapp.gov.cn' . $url, 'http://www.gapp.gov.cn' . $url, $body);

        $extract->parse();

        //$content = $extract->getContent();
        $content = implode("", $extract->text);
        preg_match("/[\x{FF08}]?([\x{4e00}-\x{9fa5}]{2,20}?)[\[\x{3014}\x{3010}\(]([0-9]+)[\]\x{3015}\x{3011}\)][\x{7B2C}]?([0-9]+)[\s]?\x{53F7}[\x{FF09}]?/u", $content, $matches);

        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($extract->title) ? $extract->title : $extract->guessTitle();
        $record->author = $extract->author;
        $record->content = base64_encode(gzdeflate($body));
        $record->doc_ori_no = $extract->doc_ori_no;
        $record->publish_time = $extract->publish_time;
        $record->t_valid = $extract->t_valid;
        $record->t_invalid = $extract->t_invalid;
        $record->negs = implode(",", $extract->negs);
        $record->tags = $extract->tags;
        $record->simhash = '';
        if (count($matches) > 3) {
            $record->doc_ori_no = sprintf("%s(%s)%s号", $matches[1], $matches[2], $matches[3]);
        }
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


        $record->type = DaoSpiderlLawBase::TYPE_HTML_FRAGMENT;
        $record->status = 1;
        $record->url = $extract->baseurl;
        $record->url_md5 = md5($extract->url);

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