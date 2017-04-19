<?php
/***************************************************************************
 * 
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 * 
 **************************************************************************/
 
 
 
/**
 * @file libs/DSKVStorage.class.php
 * @author liangtao01(sumeru-engine@baidu.com) LiangTao (liangtaohy@163.com)
 * @date 2015/05/19 20:12:16
 * @brief 
 *  
 **/

require_once dirname(__FILE__) . "/../interface/ikvstorage.class.php";
require_once dirname(__FILE__) . "/CrawlerSettings.class.php";

class KVStorageFactory
{
    const KV_FILE = "file";
    const KV_LEVELDB = "leveldb";
    const KV_REDIS = "redis";

    /**
     * @param $type
     * @return FileKVStorage|LeveDBStorage|null
     */
    public static function create($type)
    {
        $kvs = null;
        if ($type == self::KV_FILE) {
            $kvs = new FileKVStorage();
        } else if ($type == self::KV_LEVELDB) {
            $kvs = new LeveDBStorage(10,10);
        } else if ($type == self::KV_REDIS) {
        }
        return $kvs;
    }
};

/**
 * LevelDB K/V Storage
 * Default connect timeout value is 2s.
 */
class LeveDBStorage implements ikvstorage
{
    public function __construct($connect_timeout=0, $readtimeout=0)
    {
        // unimplemented
    }

    public function __destruct()
    {
        // unimplemented
    }

    public function set($key, $v)
    {
        // unimplemented
    }

    public function get($key)
    {
        // unimplemented
    }

    public function del($key)
    {
        // unimplemented
    }

    public function type() { return "leveldb"; }

    private function executeCommand($commands)
    {
        // unimplemented
    }

    private function getResponse()
    {
        // unimplemented
    }

    /** 
     * Read http response
     * @FIXME: Don't support gzip mode
     */
    private function readResponseContent(&$error_code, &$error_string)
    {
        // unimplemented
    }

    private function readResponseChunk(&$receive_completed, &$error_code, &$error_string)
    {
        // unimplemented
    }

    /**
     * try to read http headers from stream
     * @return null - if error occured or header string
     */
    private function readHttpHeaders(&$error_code, &$error_string)
    {
        // unimplemented
    }

    private function getConnection()
    {
        // unimplemented
    }

    private function getError($response)
    {
        // unimplemented
    }
};

/**
 * Key-Value Storage IN Local File
 *
 * Class FileKVStorage
 */
class FileKVStorage implements ikvstorage
{
    const DELIMETER = "\t";
    const CR = "\n";
    private $_file;
    private $_handle;

    /**
     * FileKVStorage constructor.
     * @param string $file
     */
    public function __construct($file = "")
    {
        if (empty($file)) {
            $file = gsettings()->working_space_path . "kv_db" . getmypid() . ".db";
        }
        if (!file_exists(gsettings()->working_space_path)) {
            mkdir(gsettings()->working_space_path);
        }
        $this->_file = $file;
        $this->connection();
    }

    /**
     * type
     * @return string
     */
    public function type()
    {
        return KVStorageFactory::KV_FILE;
    }

    /**
     * set <key,value> pair into storage
     * @param $key
     * @param $value
     * @return $this
     */
    public function set($key, $value)
    {
        if (isset($value) && !empty($value)) {
            if (!is_string($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            
            $datetime = time();
            //$value = urlencode($value);
            $line = $key . self::DELIMETER . $datetime . self::DELIMETER . $value . self::CR;
            $rv = fwrite($this->_handle, $line);
            if ($rv === false) {
                echo "WARNING write failed" . PHP_EOL;
            }
        }
        return $this;
    }

    /**
     * get op
     * @param $key
     */
    public function get($key)
    {
        // empty
    }

    /**
     * del op
     * @param $key
     */
    public function del($key)
    {
        // empty
    }

    /**
     * Connection
     * @return $this
     */
    protected function connection()
    {
        $handle = @fopen($this->_file, "w+");
        if ($handle) $this->_handle = $handle;
        else {
            echo "WARNING failed to connect to " . $this->_file;
        }
        return $this;
    }

    /**
     *
     */
    public function __destruct()
    {
        if ($this->_handle) @fclose($this->_handle);
        unset($this->_handle);
    }
};

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */
?>
