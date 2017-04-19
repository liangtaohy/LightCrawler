<?php

/**
 * http://dy.chinasarft.gov.cn/html/www/catalog/012996c2a84002724028815629965e99.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/17
 * Time: PM10:01
 */
define("CRAWLER_NAME", md5("spider-dy.sarft.gov"));

require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";
class SpiderDyChinasarftGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://dy.chinasarft.gov.cn/shanty.deploy/blueprint.nsp?id=01357b335a421b2e402881a635606056&templateId=012a2e051030004740284c812a2d62df",
        //"http://dy.chinasarft.gov.cn/html/www/catalog/012996c2a84002724028815629965e99.html", // 管理动态
        //"http://dy.chinasarft.gov.cn/html/www/catalog/012996c02e8902354028815629965e99.html", // 政策法规
        //"http://dy.chinasarft.gov.cn/html/www/catalog/0129dffcccb1015d402881cd29de91ec.html", // 备案公示
    );

    protected $ContentHandlers = array(
        //"#http://dy.chinasarft.gov.cn/shanty.deploy/catalog.nsp\?id=[0-9a-z]+\&pageIndex=[0-9]+# i" => "handleListPage",
        "#http://dy.chinasarft.gov.cn/shanty.deploy/blueprint.nsp\?id=[0-9a-z]+\&templateId=[0-9a-z]+# i" => 'handleDetailPage',
        //"#http://dy.chinasarft.gov.cn/html/www/catalog/(012996c02e8902354028815629965e99|012996c2a84002724028815629965e99|0129dffcccb1015d402881cd29de91ec)([\_0-9]+)?\.html# i"    => 'handleListPage',
        //"#http://dy.chinasarft.gov.cn/html/www/article/[0-9]{4}/[0-9a-z]+\.html# i"   => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderDyChinasarftGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        return array();
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $charset = $DocInfo->responseHeader->content_encoding;
        $source = $DocInfo->source;
        if (!empty($charset)) {
            $source = '<meta http-equiv="Content-Type" content="text/html; charset=' . $charset . '"/>'. "\n" . $source;
        }

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $extract->skip_td_childs = true;

        $extract->parse();

        $useRawContent = false;
        foreach ($extract->text as $t) {
            if (mb_strpos($t, "电影拍摄制作备案公示表", 0, "UTF-8") >= 0) {
                $useRawContent = true;
            }
        }

        if ($useRawContent) {
            if (empty($extract->doc_ori_no)) {
                $x = explode("\n", implode("", array_filter($extract->text)));
                $l = count($x);
                $l = $l < 20 ? $l : 20;
                for ($i = 0; $i < $l; $i++) {
                    preg_match(ExtractContent::$DefaultDocOriNoPatterns[0], $x[$i], $matches);
                    if (!empty($matches) && count($matches) > 3) {
                        $extract->doc_ori_no = sprintf("%s(%s)%s号", $matches[1], $matches[2], $matches[3]);
                        break;
                    }
                }
            }
        }

        if ($useRawContent) {
            $content = trim(implode("", array_filter($extract->text)));
        } else {
            $content = $extract->getContent();
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
                return false;
            }

            $record->type = DaoSpiderlLawBase::TYPE_TXT;
            $record->status = 1;
            $record->url = $extract->baseurl;
            $record->url_md5 = md5($extract->url);
            $record->simhash = $simhash;
        }

        if (gsettings()->debug) {
            echo implode("", $extract->text) . PHP_EOL;
            var_dump($record);
            return false;
        }
        return $record;
    }
}

$pid = posix_getpid();

file_put_contents('spider_' . SpiderDyChinasarftGov::MAGIC . '.pid', $pid);
gsettings()->debug = true;
if (gsettings()->debug == true) {
    gsettings()->url_cache_type = URL_CACHE_IN_MEMORY;
    gsettings()->enable_resume = false;
    gsettings()->number_of_process = 1;
}


$spider = new SpiderDyChinasarftGov();

$spider->setFeed(SpiderDyChinasarftGov::$SeedConf[0]);

for ($i=1;$i<count(SpiderDyChinasarftGov::$SeedConf); $i++) {
    $spider->addStartingUrls(SpiderDyChinasarftGov::$SeedConf[$i]);
}

$spider->run();