<?php

/**
 * 中国银监会
 * User: Liang Tao(liangtaohy@163.com)
 * Date: 17/4/7
 * Time: PM6:38
 */

define("CRAWLER_NAME", "spider-cbrc");

require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderDepartmentCBRC extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.cbrc.gov.cn/forwardToXZXKPage2.html",
        "http://www.cbrc.gov.cn/searchFen.do?year=&type=null&number=null&docTitle=null&indexNo=null&startDate=null&endDate=null&agencyType=null&interViewType=null&zjgxflag=true&current=1",
    );

    protected $ContentHandlers = array(
        "#http://www.cbrc.gov.cn/zhuanti/xzxk/get2and3LevelXZXKDocListDividePage//[0-9]+\.html(\?current=[0-9]+)?# i"  => "void",
        "#http://www.cbrc.gov.cn/zhuanti/xzxk/getZongHuiXzxkDocList/1/[0-9]+\.html(\?current=[0-9]+)?# i"   => "void",
        "#http://www\.cbrc\.gov\.cn/chinese/home/docView/[a-z0-9A-Z_]+\.html# i"   => "handleDetailPage",
        "#http://www\.cbrc\.gov\.cn/searchFen\.do\?year=&type=null&number=null&docTitle=null&indexNo=null&startDate=null&endDate=null&agencyType=null&interViewType=null&zjgxflag=true&current=[0-9]+# i"   => "void",
        "#http://www\.cbrc\.gov\.cn/govView_[0-9A-Za-z]+\.html# i"  => "handleDetailPage",
        "#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i" => "handleAttachment",
    );

    /**
     * SpiderDepartment constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool
     */
    protected function _handleListPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        return array();
    }

    /**
     * @param PHPCrawlerDocumentInfo $DocInfo
     * @return bool
     */
    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $charset = $DocInfo->responseHeader->charset;
        $source = $DocInfo->source;
        if (!empty($charset)) {
            $source = '<meta http-equiv="Content-Type" content="text/html; charset=' . $charset . '"/>'. "\n" . $source;
        }

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $extract->parse();

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
        $record->url_md5 = md5($extract->baseurl);

        if (gsettings()->debug) {
            //echo "raw: " . implode("\n", $extract->text) . PHP_EOL;
            //$index_blocks = $extract->indexBlock($extract->text);
            //echo implode("\n", $index_blocks) . PHP_EOL;
            var_dump($record);
            exit(0);
        }
        echo "insert data: " . $record->doc_id . PHP_EOL;
        return $record;
    }
}