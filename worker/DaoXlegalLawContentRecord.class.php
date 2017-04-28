<?php

/**
 * Created by PhpStorm.
 * User: xlegal
 * Date: 17/4/14
 * Time: PM12:22
 */
class DaoXlegalLawContentRecord extends DaoBase
{
    const DB_NAME = 'spider';
    const TABLE_NAME = 'law_content';

    const TYPE_HTML = 1;
    const TYPE_JSON = 2;
    const TYPE_TXT = 3;
    const TYPE_DOC = 4;
    const TYPE_DOCX = 5;
    const TYPE_XLS = 6;
    const TYPE_XLSX = 7;
    const TYPE_PDF  = 8;

    protected $_table_name = self::TABLE_NAME;

    private static $inst;

    private $db;

    protected $_table_fields = array(
        self::TABLE_NAME  => array(
            'doc_id'    => 'i', // '文档编号-content的md5',
            'doc_ori_no'    => 'i', // '发布文号(该值唯一，但可以为空)',
            'type'      => 0, // '文档类型',
            'title'     => 'i', // '标题',
            'tags'      => 'i', // '分类标签',
            'content'   => 'i', // '正文',
            'attachment'    => 'i', // '[{doc_id},{doc_name}]',
            'negs'      => 'i', // '命名实体',
            'author'    => 'i', // '颁布单位',
            'index_ori_no'  => 'i', // '原文索引号',
            'publish_time'  => 0, // '发布时间',
            't_valid'       => 0, // '生效时间',
            't_invalid'     => 0, // '失效时间',
            'url'       => 'i', // '原文链接',
            'url_md5'   => 'i', // 'url md5',
            'ctime'     => 0, // '入库时间',
            'simhash'   => 'i', // 'simhash值(去重使用)',
            'status'    => 0, // '待确认'
        )
    );

    /**
     * @return DaoXlegalLawContentRecord
     */
    public static function getInstance()
    {
        if (empty(self::$inst)) {
            self::$inst = new self();
        }
        return self::$inst;
    }

    /**
     * DaoXlegalLawContentRecord constructor.
     */
    public function __construct()
    {
        $this->db = DBProxy::getInstance(self::DB_NAME);
    }

    /**
     * @param array $where
     * @param array $sort
     * @param int $page
     * @param int $pagesize
     * @param array $fields
     * @return array
     */
    public function search(array $where = array(), array $sort = array(), $page = 1, $pagesize = 10, array $fields = array())
    {
        return parent::search_data($where, $sort, $page, $pagesize, $fields, $this->db);
    }

    /**
     *
     */
    public function search_data()
    {
        return $this->db->select("SELECT id, url_md5 FROM `xlegal_law_content` WHERE content like \"电影拍摄制作备案公示表%\"");
    }

    /**
     * @return bool|int
     */
    public function insert($record)
    {
        if (!empty($record->url_md5) && $this->ifUrlMd5Existed($record)) { return true; }
        if (!empty($record->doc_ori_no) && $this->ifDocOriExisted($record)) { return true; }
        if (!empty($record->doc_id) && $this->ifDocIdExisted($record)) { return true; }
        if (!empty($record->simhash) && $this->ifSimHashExisted($record)) { return true; }

        $data = array();

        foreach ($this->_table_fields[self::TABLE_NAME] as $key => $v) {
            $data[$key] = $record->$key;
        }

        try {
            self::FieldLenLimit($data, 'url', 2048);
            self::FieldLenLimit($data, 'doc_id', 32);
            self::FieldLenLimit($data, 'url_md5', 32);
            self::FieldLenLimit($data, 'simhash', 64);
            if (!isset($data['ctime']) || empty($data['ctime'])) {
                $data['ctime'] = Utils::microTime();
            }
            return $this->md_addMeta($data, $this->db);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * @return array|bool
     */
    public function ifDocIdExisted($record)
    {
        return $this->db->queryFirstRow("select id from " . $this->_table_prefix . $this->_table_name . " where doc_id='" . $record->doc_id . "'");
    }

    /**
     * @return array|bool
     */
    public function ifSimHashExisted($record)
    {
        return $this->db->queryFirstRow("select id from " . $this->_table_prefix . $this->_table_name . " where simhash='" . $record->simhash . "'");
    }

    /**
     * @return array|bool
     */
    public function ifUrlMd5Existed($record)
    {
        return $this->db->queryFirstRow("select id from " . $this->_table_prefix . $this->_table_name . " where url_md5='" . $record->url_md5 . "'");
    }

    /**
     * @return array|bool
     */
    public function ifDocOriExisted($record)
    {
        return $this->db->queryFirstRow("select id from " . $this->_table_prefix . $this->_table_name . " where doc_ori_no='" . $record->doc_ori_no . "'");
    }

    /**
     * @param $id
     * @param $meta
     * @throws XdpOpenAPIException
     */
    public function update($id, $meta)
    {
        $this->md_updateMeta($id, $meta, 0, $this->db);
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