<?php

/**
 * 全国法院列表
 * User: liangtaohy@163.com
 * Date: 17/3/31
 * Time: PM4:33
 */
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class GlobalCountiesList
{
    /**
     * http://www.gpai.net/sf/courtlist.do
     */
    const SEED_URL = "courtlist.txt";

    private static $_Inst = null;

    private static $StartParseT = 0;

    /**
     * @return GlobalCountiesList|null
     */
    public static function getInstance()
    {
        if (!isset(self::$_Inst) || empty(self::$_Inst)) {
            self::$_Inst = new self();
        }

        return self::$_Inst;
    }

    /**
     * GlobalCountiesList constructor.
     */
    private function __construct()
    {
        //
    }

    public function parse()
    {
        $ctime = Utils::microTime();

        $content = file_get_contents(self::SEED_URL);

        $extractor = new Extractor($content, self::SEED_URL);

        $doc = $extractor->document();

        if (empty($doc)) {
            echo "FATAL get content failed: " . self::SEED_URL . PHP_EOL;
            exit(0);
        }

        $court_list = $doc->query("//div[@class='court_list']/div[@class='auto']/div[@class='list-mod clearfix']/ul[@class='list']/li[@class='item J_Item clearfix']");

        if ($court_list instanceof DOMNodeList && !empty($court_list)) {
            foreach ($court_list as $element) {
                $abbrevList = $doc->query("./div[@class='letter-btn']/span[@class='letter']/text()", $element);
                $abbrev = $this->getTextFromNodeList($abbrevList);

                if (empty($abbrev) || $abbrev == 'HOT') {
                    continue;
                }

                $provinceList = $doc->query("./div[@class='letter-btn']/span[@class='iconfont-sf']/text()", $element);
                $province = $this->getTextFromNodeList($provinceList);

                $cities = $doc->query("./div[@class='provinces clearfix']/dl[@class='city']", $element);

                if ($cities instanceof DOMNodeList && !empty($cities)) {
                    foreach ($cities as $city) {
                        $nameList = $doc->query("./dt[@class='name']/a/text()", $city);
                        $name = $this->getTextFromNodeList($nameList);

                        $len = mb_strpos($name, " ", 0, "UTF-8");

                        $name = trim(mb_substr($name, 0, $len));
                        
                        $counties = $doc->query("./dd[@class='countys']/span[@class='county']/a/text()", $city);

                        $countiesArr = array();

                        if ($counties instanceof DOMNodeList && !empty($counties)) {
                            foreach ($counties as $county) {
                                $countiesArr[] = trim($county->nodeValue);
                            }
                        }

                        $counties_str = implode(",", $countiesArr);

                        echo $abbrev . "|" . $province . "|" . $name . "|" . $counties_str . PHP_EOL;

                        $sql = sprintf("INSERT INTO xlegal_countys SET abbrev='%s', province='%s', city='%s', countys='%s', ctime=%d;\n", $abbrev, $province, $name, $counties_str, $ctime);
                        file_put_contents("xlegal_countys_data.sql", $sql, FILE_APPEND);
                    }
                }
            }
        }
    }

    /**
     * @param $nodeList
     * @return string
     */
    public static function getTextFromNodeList($nodeList)
    {
        $text = '';
        foreach ($nodeList as $item) {
            $text .= trim($item->nodeValue);
        }
        return $text;
    }
}

GlobalCountiesList::getInstance()->parse();