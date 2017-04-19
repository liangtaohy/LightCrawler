<?php

/**
 * @file libs/UrlFilterManager.class.php
 * @author LiangTao (liangtaohy@163.com)
 * @date 2015/05/16 18:46:26
 * @brief
 *
 **/
require_once dirname(__FILE__) . "/CrawlerSettings.class.php";
require_once dirname(__FILE__) . "/FileReader.class.php";

class UrlFilterManager
{
    const BUFSIZE = 4096;
    const COMMENT_TAG = '@';
    private $_file;
    private $_settings;
    public $_filterrules;

    public static function instance()
    {
        static $ins = null;
        if (!isset($ins) || empty($ins))
            $ins = new UrlFilterManager();
        return $ins;
    }

    public function __construct()
    {
        $this->_settings = gsettings();
        $this->_file = $this->_settings->urlfilter_rules;
        $this->_filterrules = array();
        $this->loadconf();
    }

    protected function loadconf()
    {
        $buffer = "";
        $fr = new FileReader($this->_file, self::BUFSIZE);
        $fr->open();
        while($fr->getline($buffer) !== false) {
            trim($buffer);
            if ($buffer[0] === self::COMMENT_TAG) continue;
            if (empty($buffer)) continue;
            $this->_filterrules[] = $buffer;
        }
        $fr->close();
    }
};

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */
?>
