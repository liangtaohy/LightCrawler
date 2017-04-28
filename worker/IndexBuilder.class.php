<?php

/**
 * 索引构建器
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/27
 * Time: PM6:23
 */
define("CRAWLER_NAME", "index-builder");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class IndexBuilder
{
    const MAGIC = __CLASS__;

    const JSON_FILE_NAME = "tmp.json";

    public function run()
    {
        $where = array(
            'type' => DaoSpiderlLawBase::TYPE_HTML_FRAGMENT,
        );

        $sort = array("ctime"=>'DESC');

        $page = 1;
        $pagesize = 100;

        // doc_id as id, title,     content, doc_ori_no, t_valid, t_invalid, publish_time, author, tags, url as craw_url

        $pages = 1;
        file_put_contents(self::JSON_FILE_NAME, "");
        for ($i=1; $i<=$pages; $i++) {
            $res = DaoXlegalLawContentRecord::getInstance()->search($where, $sort, $page, $pagesize);
            if ($pages === 1) {
                $pages = $res['pages'];
            }

            if (!empty($res['data']) && is_array($res['data'])) {
                foreach ($res['data'] as $item) {
                    $record = array();
                    $record['id'] = $item['doc_id'];
                    $record['title'] = $item['title'];
                    $record['content'] = strip_tags(gzinflate(base64_decode($item['content'])));
                    $record['doc_ori_no'] = $item['doc_ori_no'];
                    $record['t_valid'] = $item['t_valid'];
                    $record['t_invalid'] = $item['t_invalid'];
                    $record['publish_time'] = $item['publish_time'];
                    $record['author'] = $item['author'];
                    $record['tags'] = $item['tags'];
                    $record['craw_url'] = $item['url'];

                    file_put_contents(self::JSON_FILE_NAME, json_encode($record, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
                }
            }
        }

        exec("/home/work/php/bin/php -c /home/work/php/etc/php.ini /mnt/open-xdp/xunsearch/sdk/php/util/Indexer.php --rebuild --source=json xlaw " . self::JSON_FILE_NAME);
    }
}