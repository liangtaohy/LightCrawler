<?php

/**
 * 法律案由归一化处理及入库
 * User: liangtaohy@163.com
 * Date: 17/3/31
 * Time: AM10:13
 */

require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";

class LawCauseOfAction
{
    const COA_FORMAT        = "%d.%d.%d.%d";
    const COA_INSERT_SQL    = "INSERT INTO xlegal_cause_of_action SET coa_no='%s', coa_name='%s', ctime=%d;\n";

    const TYPE_CIVIL_COA    = 1; // 民事案由
    const TYPE_CRIMINAL_COA = 2; // 刑事案由

    const COA_TYPE_UNKNOWN       = 0; // 未知类型
    const COA_TYPE1         = 1; // 类型1
    const COA_TYPE2         = 2; // 类型2
    const COA_TYPE3         = 3; // 类型3

    const TYPE1_PATTERN = "#(^[一二三四五六七八九十]+、)# i";
    const TYPE2_PATTERN = "#(^[1234567890]+、)# i";
    const TYPE3_PATTERN = "/^([\x{FF08}1234567890\x{FF09}]+)/u";

    const TYPE4_PATTERN = "/(^第[一二三四五六七八九十]+章)/";
    const TYPE5_PATTERN = "/(^第[一二三四五六七八九十]+节)/";

    const COA_SQL_FILE_NAME = 'coa.sql';

    private static $_Inst = null;

    private static $StartParseT = 0;

    public static function getInstance()
    {
        if (!isset(self::$_Inst) || empty(self::$_Inst)) {
            self::$_Inst = new self();
        }

        return self::$_Inst;
    }

    private function __construct()
    {
        //
    }

    public function run($file, $ptype = self::TYPE_CIVIL_COA)
    {
        if (!file_exists($file)) {
            echo 'FATAL ' . $file . ' not exists' . PHP_EOL;
            return false;
        }

        echo 'generate sql for coa begin' . PHP_EOL;
        self::$StartParseT = Utils::microTime();
        $f = fopen($file, "r");

        $data = array();
        if ($f) {
            while($buf = fgets($f, 2048)) {
                $buf = trim($buf);
                if (empty($buf)) {
                    continue;
                }

                $data[] = trim($buf);
            }
            fclose($f);
        }

        $this->parse($data, $ptype);
    }

    public function parse(array $data, $ptype = self::TYPE_CIVIL_COA)
    {
        $typeStack = array(0,0,0);
        $type1 = 0;
        $type2 = 0;
        $type3 = 0;

        $ctime = Utils::microTime();

        foreach ($data as $item) {
            $type = '';
            if ($ptype === self::TYPE_CIVIL_COA) {
                $type = $this->guessType($item);
            } else if ($ptype === self::TYPE_CRIMINAL_COA) {
                $type = $this->guessTypeCriminal($item);
                $pos = mb_strrpos($item, " ", null, "UTF-8");
                $item = mb_substr($item, $pos+1, null, "UTF-8");
            }

            echo "{$item}:{$type}" . PHP_EOL;
            switch ($type) {
                case self::COA_TYPE1:
                    $type1++;
                    $typeStack[0] = $type1;
                    $typeStack[1] = 0;
                    $typeStack[2] = 0;
                    $coa_no = sprintf(self::COA_FORMAT, $ptype, $type1, 0, 0);
                    break;
                case self::COA_TYPE2:
                    $type2++;
                    $typeStack[1] = $type2;
                    $typeStack[2] = 0;
                    $coa_no = sprintf(self::COA_FORMAT, $ptype, $type1, $type2, 0);
                    break;
                case self::COA_TYPE3:
                    $type3++;
                    $typeStack[2] = $type3;
                    $coa_no = sprintf(self::COA_FORMAT, $ptype, $type1, $type2, $type3);
                    break;
                case self::COA_TYPE_UNKNOWN:
                    echo "invalid item: " . $item . PHP_EOL;
                    continue;
            }

            if ($ptype == self::TYPE_CRIMINAL_COA) {
                $coa_no = sprintf(self::COA_FORMAT, $ptype, $typeStack[0], $typeStack[1], $typeStack[2]);
            }

            if ($type != self::COA_TYPE_UNKNOWN) {
                $coa_sql = sprintf(self::COA_INSERT_SQL, $coa_no, $item, $ctime);
                file_put_contents(self::COA_SQL_FILE_NAME, $coa_sql, FILE_APPEND);
            }
        }

        $time_used = Utils::microTime() - self::$StartParseT;

        echo 'generate sql for coa finished: ' . self::COA_SQL_FILE_NAME . ', time used ' . $time_used . 'ms' . PHP_EOL;
    }

    protected function guessTypeCriminal(&$item)
    {
        if (empty($item)) {
            return self::COA_TYPE_UNKNOWN;
        }

        //mbregex_encoding('UTF-8');
        $result = preg_match(self::TYPE4_PATTERN, $item);

        if (!empty($result)) {
            return self::COA_TYPE1;
        }

        $result = preg_match(self::TYPE5_PATTERN, $item);

        if (!empty($result)) {
            return self::COA_TYPE2;
        }

        return self::COA_TYPE3;
    }

    protected function guessType(&$item)
    {
        $result = preg_match(self::TYPE1_PATTERN, $item, $matches);

        if (!empty($result)) {
            if (!empty($matches) && is_array($matches)) {
                $item = mb_substr($item, mb_strlen($matches[1], 'UTF-8'), 1000, 'UTF-8');
            }
            return self::COA_TYPE1;
        }

        $result = preg_match(self::TYPE2_PATTERN, $item, $matches);
        if (!empty($result)) {
            if (!empty($matches) && is_array($matches)) {
                $item = mb_substr($item, mb_strlen($matches[1], 'UTF-8'), 1000, 'UTF-8');
            }
            return self::COA_TYPE2;
        }

        $result = preg_match(self::TYPE3_PATTERN, $item, $matches);
        if (!empty($result)) {
            if (!empty($matches) && is_array($matches)) {
                $item = mb_substr($item, mb_strlen($matches[1], 'UTF-8'), 1000, 'UTF-8');
            }
            return self::COA_TYPE3;
        }

        return self::COA_TYPE_UNKNOWN;
    }
}

LawCauseOfAction::getInstance()->run('coa_criminal.txt', LawCauseOfAction::TYPE_CRIMINAL_COA);
