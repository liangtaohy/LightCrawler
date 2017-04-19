<?php
/**
 * @file XPathDom.lib.php
 * @author LiangTao (liangtaohy@163.com)
 * @date 2015/05/19 16:41:12
 * @brief
 *
 **/

require_once dirname(__FILE__) . "/CharsetHelper.class.php";

class XPathDom
{
    private $_doc;
    private $_xpath;

    public function __construct($c)
    {
        $c = CharsetHelper::GbkToUtf8($c);
        if (!isset($c) || empty($c)) {
            $this->_doc = null;
            $this->_xpath = null;
            return;
        }
        $this->_doc = new DOMDocument();
        $this->_doc->recover = true;
        $this->_doc->strictErrorChecking = false;
        libxml_use_internal_errors(true);
        $this->_doc->loadHTML($c);
        $this->_doc->normalize();
        $this->_xpath = new DOMXpath($this->_doc);
    }

    public function doc()
    {
        return $this->_xpath;
    }

    public function document()
    {
        return $this->_doc;
    }
};

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */