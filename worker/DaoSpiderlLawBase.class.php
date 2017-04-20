<?php

/**
 * Created by PhpStorm.
 * User: xlegal
 * Date: 17/3/30
 * Time: AM9:54
 */
class DaoSpiderlLawBase extends DaoBase
{
    const DB_NAME = 'spider';
    const TABLE_NAME = 'law_base';

    const TYPE_HTML = 1;
    const TYPE_JSON = 2;
    const TYPE_TXT = 3;
    const TYPE_DOC = 4;
    const TYPE_DOCX = 5;
    const TYPE_XLS = 6;
    const TYPE_XLSX = 7;
    const TYPE_PDF  = 8;
    const TYPE_HTML_FRAGMENT = 9;

    protected $_table_name = self::TABLE_NAME;

    private static $inst;

    private $db;

    protected $_table_fields = array(
        self::TABLE_NAME  => array(
            'doc_id'   => 'i',
            'type'   => 1,
            'content'  => 'i',
            'url_md5'       => 'i',
            'url'     => 'i',
            'simhash'      => 'i',
            'ctime' => 1,
            'mtime' => 1,
        )
    );

    /**
     * @return DaoSpiderlLawBase
     */
    public static function getInstance()
    {
        if (empty(self::$inst)) {
            self::$inst = new self();
        }
        return self::$inst;
    }

    /**
     * DaoSpiderlLawBase constructor.
     */
    public function __construct()
    {
        $this->db = DBProxy::getInstance(self::DB_NAME);
    }

    /**
     * insert data into table
     * @param $data
     * @return bool
     */
    public function insert($data)
    {
        try {
            self::FieldLenLimit($data, 'url', 2048);
            self::FieldLenLimit($data, 'doc_id', 32);
            self::FieldLenLimit($data, 'url_md5', 32);
            self::FieldLenLimit($data, 'simhash', 64);
            if (!isset($data['ctime']) || empty($data['ctime'])) {
                $data['mtime'] = $data['ctime'] = Utils::microTime();
            }
            return $this->md_addMeta($data, $this->db);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * 批量查询,一次100条
     * @param int $total
     * @return array
     */
    public function queryOneBatch($type, $total = 109464)
    {
        static $x = 1;
        $step = 100;
        $end = $x * $step;
        $start = $end - $step + 1;
        $x++;

        if ($end > $total) {
            $end = $total;
        }

        $sql = "SELECT * FROM `xlegal_law_base` WHERE id>={$start} and id<={$end} and type={$type}";
        echo $sql . PHP_EOL;
        $result = $this->db->query($sql);

        $data = array();
        if ($result) {
            $fields = $result->fetch_fields();
            $fieldInfoR = array();
            foreach ($fields as $index => $field)
                if ($field->type < 10 || $field->type == 246)
                    $fieldInfoR[] = $field->name;

            while ($row = $result->fetch_assoc()) {
                foreach ($fieldInfoR as $key)
                    $row[$key] += 0;
                $data[] = $row;
            }
        }

        return $data;
    }

    /**
     * @param $urlmd5
     * @return array|bool
     */
    public function findOneByUrlMd5($urlmd5)
    {
        return $this->db->queryFirstRow("select url from " . $this->_table_prefix . $this->_table_name . " where url_md5='" . $urlmd5 . "'");
    }

    /**
     * @param $content_md5
     * @return array|bool
     */
    public function findOneByDocId($content_md5)
    {
        return $this->db->queryFirstRow("select url from " . $this->_table_prefix . $this->_table_name . " where doc_id='" . $content_md5 . "'");
    }

    /**
     * @param $urlmd5
     * @param $content_md5
     * @return bool
     */
    public function ifContentExists($urlmd5, $content_md5)
    {
        if ($this->findOneByUrlMd5($urlmd5)) {
            return true;
        }

        if ($this->findOneByDocId($content_md5)) {
            return true;
        }

        return false;
    }

    /**
     * @param $data
     * @param $field
     * @param $len
     */
    protected static function FieldLenLimit(&$data, $field, $len)
    {
        if (isset($data[$field]) && !empty($data[$field])) {
            if (strlen($data[$field]) > $len) {
                $data[$field] = substr($data[$field], 0, $len);
            }
        }
    }
}