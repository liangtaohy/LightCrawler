<?php

/**
 * http://dy.chinasarft.gov.cn/html/www/catalog/012996c2a84002724028815629965e99.html
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/17
 * Time: PM10:01
 */
define("CRAWLER_NAME", "spider-dy.sarft.gov");

require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";
class SpiderDyChinasarftGov extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://dy.chinasarft.gov.cn/html/www/catalog/012996c2a84002724028815629965e99.html", // 管理动态
        "http://dy.chinasarft.gov.cn/html/www/catalog/012996c02e8902354028815629965e99.html", // 政策法规
        "http://dy.chinasarft.gov.cn/html/www/catalog/0129dffcccb1015d402881cd29de91ec.html", // 备案公示
    );

    protected $ContentHandlers = array(
        "#http://dy.chinasarft.gov.cn/shanty.deploy/catalog.nsp\?id=[0-9a-z]+\&pageIndex=[0-9]+# i" => "handleListPage",
        "#http://dy.chinasarft.gov.cn/shanty.deploy/blueprint.nsp\?id=[0-9a-z]+\&templateId=[0-9a-z]+# i" => 'handleDetailPage',
        "#http://dy.chinasarft.gov.cn/html/www/catalog/(012996c02e8902354028815629965e99|012996c2a84002724028815629965e99|0129dffcccb1015d402881cd29de91ec)([\_0-9]+)?\.html# i"    => 'handleListPage',
        "#http://dy.chinasarft.gov.cn/html/www/article/[0-9]{4}/[0-9a-z]+\.html# i"   => "handleDetailPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderDyChinasarftGov constructor.
     */
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
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $extract->skip_td_childs = true;

        $extract->parse();

        $useRawContent = false;
        foreach ($extract->text as $t) {
            $pos = mb_strpos($t, "电影拍摄制作备案公示表", 0, "UTF-8");
            if ($pos !== false) {
                $useRawContent = true;
            }
        }

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

        if ($useRawContent) {
            $content = trim(implode("", array_filter($extract->text)));
        } else {
            $content = trim($extract->getContent());
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
            //echo implode("", $extract->text) . PHP_EOL;
            var_dump($record);
            exit(0);
        }
        return $record;
    }
}