<?php

/**
 * 中国银行业协会
 * http://www.china-cba.net/list.php?fid=44
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/11
 * Time: PM5:01
 */
define("CRAWLER_NAME", "spider-china-cba.net");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderChinaCbaNet extends SpiderFrame
{
    const MAGIC = __CLASS__;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://www.china-cba.net/list.php?fid=44&page=1",
        "http://www.china-cba.net/list.php?fid=89&page=1",
        "http://www.china-cba.net/list.php?fid=92&page=1",
        "http://www.china-cba.net/list.php?fid=95&page=1",
        "http://www.china-cba.net/list.php?fid=98&page=1",
        "http://www.china-cba.net/list.php?fid=101&page=1",
        "http://www.china-cba.net/list.php?fid=104&page=1",
        "http://www.china-cba.net/list.php?fid=107&page=1",
        "http://www.china-cba.net/list.php?fid=110&page=1",
        "http://www.china-cba.net/list.php?fid=113&page=1",
        "http://www.china-cba.net/list.php?fid=116&page=1",
        "http://www.china-cba.net/list.php?fid=163&page=1",
        "http://www.china-cba.net/list.php?fid=208&page=1",
        "http://www.china-cba.net/list.php?fid=215&page=1",
        "http://www.china-cba.net/list.php?fid=219&page=1",
        "http://www.china-cba.net/list.php?fid=226&page=1",
        "http://www.china-cba.net/list.php?fid=249&page=1",
        "http://www.china-cba.net/list.php?fid=277&page=1",
        "http://www.china-cba.net/list.php?fid=283&page=1",
        "http://www.china-cba.net/list.php?fid=288&page=1",
        "http://www.china-cba.net/list.php?fid=297&page=1",
        "http://www.china-cba.net/list.php?fid=304&page=1"
    );

    /**
     * @var array
     */
    protected $ContentHandlers = array(
        "#http://www\.china-cba\.net/list\.php\?fid=(44|101|89|92|95|98|104|107|110|113|116|163|208|215|219|226|249|277|283|288|297|304)&page=[0-9]+# i"  => "handleListPage",
        "#http://www\.china-cba\.net/bencandy\.php\?fid=(44|101|89|92|95|98|104|107|110|113|116|163|208|215|219|226|249|277|283|288|297|304)&id=[0-9]+# i"    => "handleDetailPage",
        "#/.*\.(pdf|docx|doc|txt|xls)# i"   => "handleAttachment",
    );

    protected $author = "中国银行业协会";
    protected $tag = "行业规定";

    /**
     * SpiderChinaCbaNet constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function _handleDetailPage(PHPCrawlerDocumentInfo $DocInfo)
    {
        $source = $DocInfo->source;

        $extract = new ExtractContent($DocInfo->url, $DocInfo->url, $source);

        $extract->parse();

        $content = $extract->getContent();
        $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
        $record = new XlegalLawContentRecord();
        $record->doc_id = md5($c);
        $record->title = !empty($extract->title) ? $extract->title : $extract->guessTitle();
        $record->author = $extract->author;
        $record->content = $content;
        $record->doc_ori_no = '';
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


        if (!empty($this->author)) {
            $record->author = $this->author;
        }

        if (!empty($this->tag)) {
            $record->tags = $this->tag;
        }

        $record->type = DaoSpiderlLawBase::TYPE_TXT;
        $record->status = 1;
        $record->url = $extract->baseurl;
        $record->url_md5 = md5($extract->url);

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