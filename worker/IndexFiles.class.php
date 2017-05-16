<?php

/**
 * 索引构建器
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/27
 * Time: PM6:23
 */
ini_set("memory_limit", "512M");
define("CRAWLER_NAME", "index-files");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";
require_once(APP_PATH . '/../../phplib/PHPWord/bootstrap.php');
require_once(APP_PATH . '/../../phplib/xunsearch/sdk/php/lib/XS.php');

class IndexFiles
{
    const MAGIC = __CLASS__;

    const JSON_FILE_NAME = "tmp.json";

    const RAW_DATA_ROOT = "/mnt/open-xdp/spider/raw_data/";

    public function run()
    {
        $sort = array("id"=>'ASC');

        $page = 1;
        $pagesize = 2000;

        $pages = 1;

        $xs = new XS("xlaw");

        for ($i=1; $i<=$pages; $i++) {
            $res = DaoXlegalLawContentRecord::getInstance()->search(
                array(),
                $sort,
                $i,
                $pagesize);

            if ($pages === 1) {
                $pages = intval($res['pages']);
            }

            if (!empty($res['data']) && is_array($res['data'])) {
                foreach ($res['data'] as $item) {
                    try {
                        $file_name = self::parse_data($item);
                        if ($file_name === false) {
                            continue;
                        }
                        echo "$file_name\t" . $item['type'] . PHP_EOL;
                    } catch (Exception $e) {
                        echo $e->getMessage() . PHP_EOL;
                        continue;
                    }
                }
            }
        }
    }

    public static function parse_data(&$data)
    {
        $type = $data['type'];
        $ctime = date("Ymd", $data['ctime']/1000);
        $file_name = "";
        switch ($type) {
            case DaoSpiderlLawBase::TYPE_HTML_FRAGMENT:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".html";
                $data['type'] = DaoSpiderlLawBase::TYPE_HTML;
                break;
            case DaoSpiderlLawBase::TYPE_HTML:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".html";
                $data['type'] = DaoSpiderlLawBase::TYPE_HTML;
                break;
            case DaoSpiderlLawBase::TYPE_TXT:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".html";
                $data['type'] = DaoSpiderlLawBase::TYPE_HTML;
                break;
            case DaoSpiderlLawBase::TYPE_PDF:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".pdf";
                break;
            case DaoSpiderlLawBase::TYPE_DOC:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".doc";
                break;
            case DaoSpiderlLawBase::TYPE_DOCX:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".docx";
                break;
            case DaoSpiderlLawBase::TYPE_JSON:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".html";
                $data['type'] = DaoSpiderlLawBase::TYPE_HTML;
                break;
            case DaoSpiderlLawBase::TYPE_XLS:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".xls";
                break;
            case DaoSpiderlLawBase::TYPE_XLSX:
                $file_name = self::RAW_DATA_ROOT . $ctime . "/" . $data['url_md5'] . ".xlsx";
                break;
        }

        if (file_exists($file_name)) {
            return $file_name;
        }

        return false;
    }
}