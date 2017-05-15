<?php

/**
 * 索引管理器
 * 取队列中的数据,插入到索引中
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/5/11
 * Time: AM11:19
 */

define("CRAWLER_NAME", "index-manager");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";
require_once(APP_PATH . '/../../phplib/PHPWord/bootstrap.php');
require_once(APP_PATH . '/../../phplib/xunsearch/sdk/php/lib/XS.php');

class IndexManager
{
    const MAGIC = __CLASS__;

    const INDEX_CACHE_KEY = "index_wait_q";

    const RAW_DATA_ROOT = "/mnt/open-xdp/spider/raw_data/";

    /**
     * redis句柄
     * @var mixed|NewRedisProxy
     */
    private $redis;

    /**
     * IndexManager constructor.
     */
    private function __construct()
    {
        $this->redis = NewRedisProxy::getInstance();
    }

    /**
     *
     */
    public function run()
    {
        while (true) {
            $message = $this->redis->blPop(array(self::INDEX_CACHE_KEY), 100);

            if (empty($message) || !is_array($message) || !isset($message[1]) || empty($message[1])) {
                continue;
            }

            try {
                $res = json_decode($message[1], true);
                if (!is_array($res)) {
                    $res = json_decode(unserialize($message[1]), true);
                    if (!is_array($res)) {
                        MeLog::debug(self::INDEX_CACHE_KEY . ', is not array: ' . $message[1]);
                        continue;
                    }
                }

                $app = $res['app'];
                $data = $res['data'];

                self::parse_data($data);

                if (gsettings()->debug) {
                    var_dump($data);
                    exit(0);
                }
                $xs = new XS($app);
                $document = new XSDocument($data, 'utf-8');
                $xs->getIndex()->add($document);
            } catch (Exception $e) {

            }
        }
    }

    public static function parse_data(&$data)
    {
        $type = $data['type'];
        $ctime = date("Ymd", $data['ctime']/1000);
        switch ($type) {
            case DaoSpiderlLawBase::TYPE_HTML_FRAGMENT:
                $data['content'] = strip_tags(gzinflate(base64_decode($data['content'])));
                break;
            case DaoSpiderlLawBase::TYPE_HTML:
                $data['content'] = strip_tags($data['content']);
                break;
            case DaoSpiderlLawBase::TYPE_TXT:
                break;
            case DaoSpiderlLawBase::TYPE_PDF:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".pdf";
                if (file_exists($file_name)) {
                    $phpWord = \PhpOffice\PhpWord\IOFactory::load($file_name, "PDFReader");
                    exec("pdftotext -layout {$file_name} -", $output);
                    $data['content'] = implode("\n", $output);
                    if (empty($data['title'])) {
                        $data['title'] = $phpWord->getDocInfo()->getTitle();
                    }
                }
                echo $file_name . PHP_EOL;
                break;
            case DaoSpiderlLawBase::TYPE_DOC:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".doc";
                if (file_exists($file_name)) {
                    $f = fopen($file_name,'r');
                    fgets($f);
                    fgets($f);
                    fgets($f);
                    $raw = "";
                    while($buf = fgets($f)) {
                        $raw .= $buf;
                    }
                    file_put_contents("./dump.doc", $raw);
                    $phpWord = \PhpOffice\PhpWord\IOFactory::load("./dump.doc", "MsDoc");
                    $content = "";
                    exec("/mnt/opbin/antiword-0.37/antiword -f ./dump.doc", $content);
                    $data['content'] = implode("\n", $content);
                    if (empty($data['title'])) {
                        $data['title'] = $phpWord->getDocInfo()->getTitle();
                    }
                }
                echo $file_name . PHP_EOL;
                break;
            case DaoSpiderlLawBase::TYPE_DOCX:
                return false;
            case DaoSpiderlLawBase::TYPE_JSON:
                return false;
        }

        return true;
    }
}