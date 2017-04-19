<?php
/***************************************************************************
 *
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 *
 **************************************************************************/



/**
 * @file libs/FileReader.class.php
 * @author work(sumeru-engine@baidu.com)
 * @date 2015/05/16 18:08:00
 * @brief
 *
 **/

class FileReader
{
    private $handle;
    private $_file;
    private $_pattern_max_line_size;

    /**
     * $file - file name
     * $bufsize - buffer size in byte
     */
    public function __construct($file, $bufsize)
    {
        $this->_file = $file;
        $this->_pattern_max_line_size = $bufsize;
        $this->open();
    }

    public function __destruct()
    {
        $this->close();
    }

    public function open()
    {
        // do nothing
    }

    public function close()
    {
        if (isset($this->handle)) fclose($this->handle);
    }

    /**
     * Get a line from a specified file into \a $buffer
     * The max of buffer size is configed by url_pattern_max_line_size setting.
     * @return: true if line is existed or false-end of file. And if file is not existed or
     * unreadable, the process will be quit. So, MAKE SURE file be existed!!!!
     */
    /**
     * Get a line from a specified file into \a $buffer
     * The max of buffer size is configed by url_pattern_max_line_size setting.
     * @param $buffer
     * @return bool true if line is existed or false-end of file. And if file is not existed or
     *      unreadable, the process will be quit. So, MAKE SURE file be existed!!!!
     */
    public function getline(&$buffer)
    {
        if (!isset($this->_file) && empty($this->_file)) {
            echo "FATAL expected a file" . PHP_EOL;
            exit();
        }

        if (@is_readable($this->_file)) {
            if (isset($this->handle) && !empty($this->handle)) {
                $res = ($buffer = fgets($this->handle, $this->_pattern_max_line_size)) !== false;
                return $res;
            } else {
                $handle = @fopen($this->_file, "r");
                if ($handle) {
                    $this->handle = $handle;
                    $res = ($buffer = fgets($this->handle, $this->_pattern_max_line_size)) !== false;
                    return $res;
                } else {
                    echo "FATAL open file failed: " . $this->_file . PHP_EOL;
                    exit(0);
                }
            }
        } else {
            echo "FATAL LineReader.getline failed because of file " . $this->_file . " unreadable!!" . PHP_EOL;
            exit(0);
        }
    }
};

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */