<?php

/**
 * 索引构建器
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/27
 * Time: PM6:23
 */
ini_set("memory_limit", "512M");
define("CRAWLER_NAME", "index-builder");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";
require_once(APP_PATH . '/../../phplib/PHPWord/bootstrap.php');
require_once(APP_PATH . '/../../phplib/xunsearch/sdk/php/lib/XS.php');

class IndexBuilder
{
    const MAGIC = __CLASS__;

    const JSON_FILE_NAME = "tmp.json";

    public function run()
    {
        $sort = array("id"=>'ASC');

        $page = 1;
        $pagesize = 2000;

        $pages = 1;

        $xs = new XS("xlaw");

        $id = 0;
        if (file_exists("./indexed_id.txt")) {
            $id = intval(file_get_contents("./indexed_id.txt"));
        }

        for ($i=1; $i<=$pages; $i++) {
            $res = DaoXlegalLawContentRecord::getInstance()->search(
                array('id'=>array(
                    'op'    => '>',
                    'value' => $id,
                ),
                    'type'=> array(
                        'op'    => 'IN',
                        'value' => array(DaoSpiderlLawBase::TYPE_TXT, DaoSpiderlLawBase::TYPE_HTML_FRAGMENT)
                    )),
                $sort,
                $i,
                $pagesize);

            if ($pages === 1) {
                $pages = intval($res['pages']);
            }

            if (!empty($res['data']) && is_array($res['data'])) {
                foreach ($res['data'] as $item) {
                    try {
                        if (IndexManager::parse_data($item) === false) {
                            continue;
                        }
                        $record = array();
                        $record['id'] = $item['doc_id'];
                        $record['title'] = $item['title'];
                        $record['content'] = $item['content'];
                        $record['doc_ori_no'] = $item['doc_ori_no'];
                        $record['t_valid'] = $item['t_valid'];
                        $record['t_invalid'] = $item['t_invalid'];
                        $record['publish_time'] = $item['publish_time'];
                        $record['author'] = $item['author'];
                        $record['tags'] = $item['tags'];
                        $record['craw_url'] = $item['url'];

                        if (gsettings()->debug) {
                            var_dump($record);
                            exit(0);
                        }

                        $document = new XSDocument($record, 'utf-8');
                        $xs->getIndex()->add($document);
                        echo "insert {$item['id']}" . PHP_EOL;
                        file_put_contents("./indexed_id.txt", $item['id']);
                    } catch (Exception $e) {
                        echo $e->getMessage() . PHP_EOL;
                        continue;
                    }
                }
            }
        }
    }
}