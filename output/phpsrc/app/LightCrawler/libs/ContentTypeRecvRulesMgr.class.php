<?php
/***************************************************************************
 *
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 *
 **************************************************************************/



/**
 * @file libs/ContentTypeRecvRulesMgr.class.php
 * @author LiangTao (liangtaohy@163.com)
 * @date 2015/05/18 14:46:26
 * @brief
 *
 **/
require_once dirname(__FILE__) . "/CrawlerSettings.class.php";
require_once dirname(__FILE__) . "/FileReader.class.php";

class ContentTypeRecvRulesMgr
{
    const BUFSIZE = 4096;
    const COMMENT_TAG = '@';
    private $_file;
    private $_settings;
    public $_rules;

    public static function instance()
    {
        static $ins = null;
        if (!isset($ins) || empty($ins))
            $ins = new ContentTypeRecvRulesMgr();
        return $ins;
    }

    public function __construct()
    {
        $this->_settings = gsettings();
        $this->_file = $this->_settings->content_type;
        $this->_rules = array();
        $this->loadconf();
    }

    protected function loadconf()
    {
        $buffer = "";
        echo $this->_file . "\n";
        $fr = new FileReader($this->_file, self::BUFSIZE);
        $fr->open();
        while($fr->getline($buffer) !== false) {
            trim($buffer);
            if ($buffer[0] === self::COMMENT_TAG) continue;
            if (empty($buffer)) continue;
            $this->_rules[] = $buffer;
        }
        $fr->close();
    }
};

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */