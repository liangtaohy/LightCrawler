<?php

/**
 * Created by PhpStorm.
 * User: xlegal
 * Date: 17/4/21
 * Time: PM5:52
 */
define("CRAWLER_NAME", "spider-pbc.cn");
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class SpiderPbcParser
{
    const ROOT = "/home/work/xdp/phpsrc/app/LightCrawler/worker/htmls/";

    static $Patterns = array(
        "/\x{8BB8}\x{53EF}\x{8BC1}\x{7F16}\x{53F7}\|(.*?)\|/u" => "doc_ori_no",
        "/\x{516C}\x{53F8}\x{540D}\x{79F0}\|(.*?)\|/u" => "company",
        "/\x{6CD5}\x{5B9A}\x{4EE3}\x{8868}\x{4EBA}\x{FF08}\x{8D1F}\x{8D23}\x{4EBA}\x{FF09}(\s+)?\|(.*?)\|/u"  => "legal_person",
        "/\x{4E1A}\x{52A1}\x{7C7B}\x{578B}(\s+)?\|(.*?)\|/u"  => "buseness_type",
        "/\x{4E1A}\x{52A1}\x{8986}\x{76D6}\x{8303}\x{56F4}(\s+)?\|(.*?)\|/u"  => "area",
        "/\x{53D1}\x{8BC1}\x{65E5}\x{671F}(\s+)?\|(.*?)\|/u"  => "publish_time",
        "/\x{6709}\x{6548}\x{671F}\x{81F3}(\s+)?\|(.*?)\|/u"  => "valid_time",
    );

    public static function run()
    {
        $files = dir(self::ROOT);

        while ($f = $files->read()) {
            if ($f == "." || $f == "..") continue;
            $filename = self::ROOT . $f;

            $p = strpos($f, ".");
            $url_md5 = substr($f, 0, $p);

            $content = new stdClass();
            $content->doc_ori_no = array();
            $content->company = array();
            $content->legal_person = array();
            $content->buseness_type = array();
            $content->area = array();
            $content->publish_time = array();
            $content->valid_time = array();

            $source = file_get_contents($filename);
            $source = preg_replace("#(<p>)|(</p>)# i", "", $source);
            $extract = new ExtractContent('', '', $source);
            $extract->skip_td_childs = true;
            $extract->parse();
            $raw = implode("", $extract->getExtractor()->text);
            $textLines = explode("\n", $raw);

            $len = count($textLines);
            for ($i = 0; $i < $len; $i++) {
                $textLine = $textLines[$i];
                foreach (self::$Patterns as $pattern    => $field) {
                    $matches = array();
                    $r = preg_match($pattern, $textLine, $matches);
                    if (!empty($r) && !empty($matches) && count($matches) > 1) {
                        $l = count($matches);
                        array_push($content->$field, trim($matches[$l - 1]));
                    }
                    unset($matches);
                }
            }

            var_dump($content);
            $content->doc_ori_no = $content->doc_ori_no[0];
            $content->company = $content->company[0];
            $content->legal_person = $content->legal_person[0];
            $content->area = $content->area[0];
            $content->valid_time = $content->valid_time[0];
            $publish_time = $content->publish_time[0];
            $content->publish_time = implode(",", $content->publish_time);
            $content->buseness_type = $content->buseness_type[0];

            $content1 = json_encode($content, JSON_UNESCAPED_UNICODE);
            $record = new XlegalLawContentRecord();
            $record->doc_id = md5($content1);
            $record->title = "支付业务许可证";
            $record->author = "中国人民银行";
            $record->content = $content1;
            $record->doc_ori_no = $content->doc_ori_no;
            $record->publish_time = strtotime($publish_time);
            $record->t_valid = strtotime($publish_time);
            $record->t_invalid = 0;
            $record->tags = "行政许可,支付牌照,许可证";
            $record->type = DaoSpiderlLawBase::TYPE_JSON;
            $record->status = 1;
            $record->url = $filename;
            $record->url_md5 = $url_md5;
            var_dump($record);
            DaoXlegalLawContentRecord::getInstance()->insert(
                $record
            );
        }
    }
}

SpiderPbcParser::run();