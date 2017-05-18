<?php

/**
 * http://gkml.saic.gov.cn/2086/2087/list.html
 * 工商管理总局信息公开目录
 *
 * Run:
 * /home/work/php/bin/php -c /home/work/php/etc/php.ini WorkerRunner.class.php -tSpiderGkmlSaicGov
 *
 * Run In Debug Mode:
 * /home/work/php/bin/php -c /home/work/php/etc/php.ini WorkerRunner.class.php -tSpiderGkmlSaicGov -d
 *
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/18
 * Time: PM6:02
 */
define("CRAWLER_NAME", "spider-saic.gov.cn");

require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderGkmlSaicGov extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://gkml.saic.gov.cn/2086/2087/list.html",
        "http://www.saic.gov.cn/gkml/2086/2088/2103/list.html",
        "http://www.saic.gov.cn/gkml/2086/2088/2104/list.html",
        "http://www.saic.gov.cn/gkml/2086/2088/2105/list.html",
        "http://www.saic.gov.cn/gkml/2086/2088/2106/list.html",
        "http://www.saic.gov.cn/gkml/2086/2088/2107/list.html",
        "http://www.saic.gov.cn/gkml/2086/2094/list.html",
        "http://www.saic.gov.cn/gkml/2086/2095/list.html",
        "http://www.saic.gov.cn/gkml/2086/2096/list.html",
        "http://www.saic.gov.cn/gkml/2086/2097/list.html",
        "http://www.saic.gov.cn/gkml/2086/2098/list.html",
        "http://www.saic.gov.cn/gkml/2086/2099/list.html",
        "http://www.saic.gov.cn/gkml/2086/2100/list.html",
        "http://www.saic.gov.cn/zw/wjfb/index.html",
        "http://www.saic.gov.cn/fw/bsdt/gg/jzzf/index.html",
        "http://www.saic.gov.cn/fw/bsdt/gg/qymcyh/index.html",
    );

    protected $ContentHandlers = array(
        "#http://www\.saic\.gov\.cn/fw/bsdt/gg/qymcyh/[0-9]{6}/t[0-9]{8}_[0-9]+\.html# i"   => "handleDetailPage",
        "#http://gkml.saic.gov.cn/2086/2087/list([\_0-9]+)?\.html# i" => "handleListPage",
        "#http://www.saic.gov.cn/gkml/[0-9]{4}/[0-9]{4}/[0-9]{4}/list([\_0-9]+)?\.html# i"    => "handleListPage",
        "#http://www.saic.gov.cn/gkml/[0-9]{4}/[0-9]{4}/list([\_0-9]+)?\.html# i"   => "handleListPage",
        "#http://www.saic.gov.cn/zw/wjfb/index(_[0-9]+)?.html# i" => "handleListPage",
        "#http://www.saic.gov.cn/fw/bsdt/gg/jzzf/index(_[0-9]+)?.html# i"   => "handleListPage",
        "#http://gkml.saic.gov.cn/auto[0-9]+/auto[0-9]+/[0-9]+/t[0-9]+_[0-9]+\.html# i"   => "handleDetailPage",
        "#http://www.saic.gov.cn/gkml/auto[0-9]+/auto[0-9]+/[0-9]+/t[0-9]+_[0-9]+\.html# i"    => "handleDetailPage",
        "#/[0-9]{6}/t[0-9]+_[0-9]+\.html# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderDyChinasarftGov constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function computePages(PHPCrawlerDocumentInfo $DocInfo)
    {
        $totalPatterns = array(
            "#var m_nRecordCount = [\"]?([0-9]+)[\"]?;# i",
        );

        $pagesizePatterns = array(
            "#var m_nPageSize = [\"]?([0-9]+)[\"]?;# i",
        );

        $pagesPatterns = array(
            "#var countPage = [\"]?([0-9]+)[\"]?# i",
        );

        $total = 0;
        $pagesize = 0;
        $pages = 0;

        foreach ($pagesPatterns as $pagesPattern) {
            $result = preg_match($pagesPattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $pages = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        if (!empty($pages)) {
            $res = array(
            );
            $res['pages'] = $pages;
            return $res;
        }

        unset($result);
        unset($matches);
        foreach ($totalPatterns as $totalPattern) {
            $result = preg_match($totalPattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $total = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        if (empty($total)) {
            echo "FATAL get total page failed: " . $DocInfo->url . PHP_EOL;
            return true;
        }

        unset($result);
        unset($matches);

        foreach ($pagesizePatterns as $pagesizePattern) {
            $result = preg_match($pagesizePattern, $DocInfo->source, $matches);
            if (!empty($result) && !empty($matches) && is_array($matches)) {
                $pagesize = intval($matches[1]);
                break;
            }
            unset($matches);
        }

        if (empty($pagesize)) {
            echo "FATAL get pagesize failed: " . $DocInfo->url . PHP_EOL;
            return array(
                'total' => $total,
            );
        }

        $res = array(
            'total' => $total,
        );
        $total = ceil($total / $pagesize);
        $res['pages'] = $total;
        $res['pagesize'] = $pagesize;

        return $res;
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return array
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $pager = $this->computePages($DocInfo);
        $sPageName = "list";
        $sPageExt = "html";

        $r = preg_match("#index(_[0-9]+)?.html# i", $DocInfo->url);

        if (!empty($r)) {
            $sPageName = "index";
        }

        $p = strrpos($DocInfo->url, "/");
        $prefix = substr($DocInfo->url, 0, $p + 1);

        $pages = array();
        for ($i = 1; $i <= $pager['pages']; $i++)
        {
            if($i == 1){
                $url = $sPageName . "." . $sPageExt;
            }else{
                $url = $sPageName . "_" . ($i-1) . "." . $sPageExt;
            }
            $pages[] = $prefix . $url;
        }

        return $pages;
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);
        $extract->skip_td_childs = true;
        $extract->parse();

        $extract->parseSummary($extract->text);

        $content = $extract->getContent();
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
        $record->url_md5 = md5($extract->url);

        if (gsettings()->debug) {
            echo "raw: " . implode("\n", $extract->text) . PHP_EOL;
            $index_blocks = $extract->indexBlock($extract->text);
            echo implode("\n", $index_blocks) . PHP_EOL;
            var_dump($record);
            return false;
        }
        echo "insert data: " . $record->doc_id . PHP_EOL;
        return $record;
    }
}