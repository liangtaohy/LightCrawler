<?php

/**
 * 单次抓取处理
 * http://www.gov.cn/zhengce/zc_bmgz.htm
 *
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/5
 * Time: PM1:08
 */

require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderGovCnZhengceOnceTask
{
    const MAGIC = __CLASS__;

    public $storage_root = "/mnt/open-xdp/spider";

    private $raw_data_dir = "/raw_data";

    private $skip_line;

    /**
     * Seed Conf
     * @var array
     */
    static $SeedConf = array(
        "http://sousuo.gov.cn/list.htm?q=&n=15&p=0&t=paper&sort=pubtime&childtype=&subchildtype=&pcodeJiguan=&pcodeYear=&pcodeNum=&location=&searchfield=&title=&content=&pcode=&puborg=&timetype=timeqb&mintime=&maxtime=",
        "http://new.sousuo.gov.cn/list.htm?sort=pubtime&advance=true&t=paper&n=15",
    );

    static $ContentHandlers = array(
        "#http://sousuo.gov.cn/list.htm\?q=&n=[0-9]+&p=[0-9]+&t=paper&sort=pubtime&childtype=&subchildtype=&pcodeJiguan=&pcodeYear=&pcodeNum=&location=&searchfield=&title=&content=&pcode=&puborg=&timetype=timeqb&mintime=&maxtime=# i" => "handleListPage",
        "#http://www.gov.cn/zhengce/content/[0-9]+\-[0-9]+/[0-9]+/content_[0-9]+\.htm# i"   => "handleDetailPage",
        "#http://www.gov.cn/zhengce/[0-5]+\-[0-9]+/[0-9]+/[0-9}+/files/[0-9a-zA-Z]+\.doc# i" => "handleDocAttatchment"
    );

    public function __construct($skip_line = 2)
    {
        $this->skip_line = $skip_line;
    }

    public function setSkipLine($skip_line)
    {
        $this->skip_line = $skip_line;
    }

    /**
     * @param $files
     */
    public function run($files)
    {
        foreach ($files as $file) {
            $DocInfo = $this->loadLocalFiles($file);

            $this->handleDetailPage($DocInfo);
        }
    }

    /**
     * load raw data from local file
     * @param $local_file
     * @return bool|string
     */
    public function loadLocalFiles($local_file)
    {
        $local_file = trim($local_file);
        if (!file_exists($local_file)) {
            echo "FATAL {$local_file} not exists\n";
            exit(0);
        }

        $f = fopen($local_file, "r");
        if ($f) {
            $url = fgets($f);
            fgets($f);

            $content = [];
            while ($buf = fgets($f)) {
                $content[] = $buf;
            }

            $DocInfo = new stdClass();
            $DocInfo->source = implode("", array_filter($content));
            $DocInfo->url = trim($url);
            $DocInfo->url_raw = $local_file;

            return $DocInfo;
        }

        return false;
    }

    /**
     * @param $DocInfo
     * @return bool
     */
    public function handleDetailPage($DocInfo)
    {
        // remove \r\n (^M character)
        $patterns = array(
            chr(13),
            '<BR>',
            '<br />',
            '<br>',
            '<BR />',
            '<br/>',
        );

        $replaces = array(
            "\n",
            "\n",
            "\n",
            "\n",
            "\n",
            "\n"
        );

        $source = str_replace($patterns, $replaces, $DocInfo->source);
        $extractor = new Extractor($source, $DocInfo->url);
        $doc = $extractor->document();

        if (empty($doc)) {
            echo "content is null: " . $DocInfo->url . PHP_EOL;
            return;
        }

        $content = $doc->query("//div[@class='wrap']/table");

        $raw = array();
        if ($content instanceof DOMNodeList && !empty($content)) {
            foreach ($content as $t) {
                $raw[] = $t->nodeValue;
            }
        }

        $document = array();
        $document['ctime'] = $document['mtime'] = Utils::microTime();
        $document['craw_url'] = $DocInfo->url;

        $document['content'] = implode("",$raw);

        if (!empty($raw)) {
            $summary = $this->parseSummary($document['content']);
            $document = array_merge($document, $summary);
        }

        if (empty($document['content'])) {
            echo "FATAL get content failed: " . $DocInfo->url_raw . ", " . $DocInfo->url . PHP_EOL;
            return true;
        }

        $pos = mb_strpos($document['content'], "var gwdshare");

        $document['content'] = mb_substr($document['content'], 0, $pos);

        // If exists, drop it begin
        $document['id'] = ContentHelper::GenContentMd5($document['content']);

        $urlmd5 = md5($document['craw_url']);

        if (DaoSpiderlLawBase::getInstance()->ifContentExists($urlmd5, $document['id'])) {
            echo "data exsits: urlmd5-{$urlmd5}, doc_id-{$document['id']}, " . $DocInfo->url;
            return true;
        }

        $res = FlaskRestClient::GetInstance()->simHash($document['content']);

        $simhash = '';
        if (isset($res['simhash']) && !empty($res['simhash'])) {
            $simhash = $res['simhash'];
        }

        if (isset($res['repeated']) && !empty($res['repeated'])) {
            echo 'data repeated: ' . $DocInfo->url . PHP_EOL;
            //return true;
        }

        // If exists end

        $data = array(
            'doc_id'    => $document['id'],
            'type'  => DaoSpiderlLawBase::TYPE_JSON,
            'url'   => $document['craw_url'],
            'url_md5'   => $urlmd5,
            'content'   => json_encode($document, JSON_UNESCAPED_UNICODE),
            'simhash'   => $simhash,
        );
        
        DaoSpiderlLawBase::getInstance()->insert($data);
        return true;
    }

    private function parseSummary($text)
    {
        $needles = array(
            "title"     => "标　　题：",
            "doc_ori_no"  => "发文字号：",
            "publish_time"     => "发布日期：",
            "t_valid"    => "成文日期：",
            "t_invalid"  => 0,
            "tags"      => "主题分类：",
            "author"    => "发文机关：",
            "keywords"  => "主  题  词：",
        );

        $summary = array();

        foreach ($needles as $key => $needle) {
            $p = mb_strpos($text, $needle, 0, "UTF-8");
            $summary[$key] = $p;
        }

        $summary['tags'] = trim(mb_substr($text, $summary['tags'] + 5, $summary['author'] - ($summary['tags'] + 5)));
        $summary['author'] = trim(mb_substr($text, $summary['author'] + 5, $summary['t_valid'] - ($summary['author'] + 5)));
        $summary['t_valid'] = trim(mb_substr($text, $summary['t_valid'] + 5, $summary['title'] - ($summary['t_valid'] + 5)));
        $summary['title'] = trim(mb_substr($text, $summary['title'] + 5, $summary['doc_ori_no'] - ($summary['title'] + 5)));
        $summary['doc_ori_no'] = trim(mb_substr($text, $summary['doc_ori_no'] + 5, $summary['publish_time'] - ($summary['doc_ori_no'] + 5)));
        $summary['publish_time'] = trim(mb_substr($text, $summary['publish_time'] + 5, $summary['keywords'] - ($summary['publish_time'] + 5)));

        unset($summary['keywords']);

        $res = preg_match("#([0-9]{4})年([0-9]{2})月([0-9]{2})日# i", $summary['t_valid'], $matches);

        if (!empty($res)) {
            if ($matches) {
                $summary['t_valid'] = strtotime($matches[1] . "-" . $matches[2] . "-" . $matches[3]);
            }
        }

        $res = preg_match("#([0-9]{4})年([0-9]{2})月([0-9]{2})日# i", $summary['publish_time'], $matches);

        if (!empty($res)) {
            if ($matches) {
                $summary['publish_time'] = strtotime($matches[1] . "-" . $matches[2] . "-" . $matches[3]);
            }
        }

        /*
                if (!empty($summary['publish_time'])) {
                    $summary['publish_time'] = strtotime($summary['publish_time']);
                }

                if (!empty($summary['t_valid'])) {
                    $summary['t_valid'] = strtotime($summary['t_valid']);
                }

                if (!empty($summary['t_invalid'])) {
                    $summary['t_invalid'] = strtotime($summary['t_invalid']);
                }
        */

        return $summary;
    }
}

$LOG_FILE = "/home/work/xdp/phpsrc/app/LightCrawler/worker/nohup.out";

$result = exec("cat nohup.out | grep \"FATAL get content failed:\" | awk -F':' '{print $2}' | awk -F',' '{print $1}'", $output);

if (empty($output)) {
    echo "data empty" . PHP_EOL;
    exit(0);
}

$spider = new SpiderGovCnZhengceOnceTask();
$spider->run($output);