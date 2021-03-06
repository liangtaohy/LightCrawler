<?php

/**
 * 网信办
 * http://www.cac.gov.cn/fl.htm
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/17
 * Time: PM4:23
 */
define("CRAWLER_NAME", "spider-cac.gov.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderCacGovCn extends SpiderFrame
{
    const MAGIC = __CLASS__;
    const MAX_PAGE = 10;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.cac.gov.cn/fl.htm",
        "http://www.cac.gov.cn/qwfb.htm",
        "http://search.cac.gov.cn/was5/web/search?channelid=246506&searchword=extend5%3D%27%251184063%25%27&prepage=36&list=&page=1"
    );

    protected $ContentHandlers = array(
        "#http://search\.cac\.gov\.cn/was5/web/search\?channelid=[0-9]+&searchword=.*&prepage=36&list=&page=[0-9]+# i"  => "handleListPage",
        "#http://www\.cac\.gov\.cn/(fl|xzfg|bmgz|sfjs|gfxwj|zcwj|qwfb)\.htm# i"  => "handleListPage",
        "#http://www\.cac\.gov\.cn/[0-9]{4}-[0-9]{2}/[0-9]{2}/c_[0-9]+\.htm# i" => "handleDetailPage",
        "#/.*\.(doc|docx|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderBjrtXZXK constructor.
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

        DaoUrlCache::getInstance()->pergeCacheByIds($ids);
        if (gsettings()->debug) {
            var_dump($ids);
            exit(0);
        }
    }

    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        if (stripos($DocInfo->url, "http://search.cac.gov.cn/was5/") !== false) {
            /*
             * var curpage=1;
							  var perpage=36;
							  var recordnum = 4517;
             */
            $curpage = 1;
            $perpage = 36;
            $recordnum = 1;
            preg_match("#var curpage=([0-9]+);# i", $DocInfo->source, $m_curpage);
            preg_match("#var perpage=([0-9]+);# i", $DocInfo->source, $m_perpage);
            preg_match("#var recordnum = ([0-9]+);# i", $DocInfo->source, $m_recordnum);

            if (count($m_curpage) > 1) {
                $curpage = intval($m_curpage[1]);
            }
            if (count($m_perpage) > 1) {
                $perpage = intval($m_perpage[1]);
            }
            if (count($m_recordnum) > 1) {
                $recordnum = intval($m_recordnum[1]);
            }

            $pagenum = ceil($recordnum / $perpage);

            $extract = new Extractor($DocInfo->source, $DocInfo->url);
            $document = $extract->document();

            $formParams = array();
            $forms = $document->query("//form[@name='searchform']");
            $pages = array();
            if ($curpage < $pagenum) {
                if (!empty($forms) && $forms instanceof DOMNodeList) {
                    $form = $forms->item(0);
                    $childs = $form->childNodes;
                    foreach ($childs as $child) {
                        if ($child->nodeName == 'input') {
                            $name = $child->getAttribute('name');
                            $value = $child->getAttribute('value');
                            $formParams[$name] = $value;
                        }
                    }
                    $formParams['page'] = $curpage + 1;
                    $pages[] = "http://search.cac.gov.cn/was5/web/search?" . http_build_query($formParams);
                }
            }

            if (gsettings()->debug) {
                var_dump($pages);
                exit(0);
            }
            return $pages;
        }

        return array();
    }
}