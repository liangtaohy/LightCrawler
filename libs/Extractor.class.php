<?php
/**
 * @file libs/Extractor.class.php
 * @author LiangTao (liangtaohy@163.com)
 * @date 2015/05/19 19:12:42
 * @brief
 *
 **/

require_once dirname(__FILE__) . "/XPathDom.class.php";

class Extractor
{
    const TYPE_NUMBER = "number";
    const TYPE_LINK = "link";
    private $doc;
    private $dom;
    private $currentUrl;

    /**
     * Extractor constructor.
     * @param $c
     * @param $baseurl
     */
    public function __construct($c, $baseurl)
    {
        $this->dom = new XPathDom($c);
        $this->currentUrl = $baseurl;
        $this->doc = $this->dom->doc();
    }

    /**
     * @return DOMXpath
     */
    public function document() { return $this->doc; }

    /**
     * @return DOMDocument
     */
    public function domDocument() { return $this->dom->document(); }

    public function reset($c, $baseurl)
    {
        unset($this->dom);
        unset($this->currentUrl);
        unset($this->doc);
        $this->dom = new XPathDom($c);
        $this->currentUrl = $baseurl;
        $this->doc = $this->dom->doc();
    }
};

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */