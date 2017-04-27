<?php

/**
 * 内容抽取器
 * 标题抽取 (doing) : a标签链接文本,网页title,h{1,4}标签
 * 正文抽取 (done)
 * 索引块抽取 (done)
 * 目录抽取 (plan)
 * User: Liang Tao (liangtaohy@163.com)
 * Date: 17/4/10
 * Time: PM2:40
 */
require_once dirname(__FILE__) . "/../includes/lightcrawler.inc.php";
define("DEBUG", 0);
ini_set("memory_limit", "512M");
class ExtractContent
{
    const MAGIC = __CLASS__;

    public $storage_root = "/mnt/open-xdp/spider";

    public $raw_data_dir = "/raw_data";

    public $skip_td_childs = false;

    /**
     * @var Extractor
     */
    public $extractor;

    public $charset = '';
    public $url = "";
    public $baseurl = "";

    /**
     * title from <title> tag
     * @var string
     */
    public $meta_title = "";

    public $title_texts = array();

    /**
     * title
     * @var string
     */
    public $guess_title = "";

    /**
     * content
     * @var string
     */
    public $content = "";

    /**
     * 发布单位
     * @var string
     */
    public $author = "";

    /**
     * 发布日期
     * @var string
     */
    public $publish_time = "";

    /**
     * 失效日期
     * @var string
     */
    public $t_invalid = "";

    /**
     * 生效日期
     * @var string
     */
    public $t_valid = "";

    /**
     * 发布文号
     * @var string
     */
    public $doc_ori_no = "";

    /**
     * 索引中的文件标题
     * @var string
     */
    public $title = "";

    /**
     * 类型
     * @var string
     */
    public $tags = "";

    /**
     * content text
     * @var array
     */
    public $text = array();

    public $textP = array();

    public $pCharacterLen = 0;
    public $textCharacterLen = 0;

    public $times = array();

    public $cwrq_time = 0;

    /**
     * original url
     * @var string
     */
    public $original_url = '';

    public $attachments = array();

    public $simhash = "";

    /**
     * 名命实体
     * @var array
     */
    public $negs = array();

    public $raw_content = '';

    public $index_blocks = array();

    public static $DefaultSpecialTags = array(
        "script", "style", "link",
    );

    public static $DefaultFooterRules = array(
        "footer", "copyright"
    );

    public static $BadWords = array(
        "关于我们", "法律声明", "联系我们", "版权所有", "ICP备", "地址", "邮编","办文部门"
    );

    public static $DefaultBadWords = array(
        "/\x{7248}\x{6743}\x{6240}\x{6709}[\x{FF1A}:]/u",
        "/\x{6765}\x{6E90}[\x{FF1A}:]/u"
    );

    public static $DefaultDocOriNoPatterns = array(
        "/[\x{FF08}]?([\x{4e00}-\x{9fa5}]{2,20}?)[\[\x{3014}\x{3010}\(]([0-9]+)[\]\x{3015}\x{3011}\)][\x{7B2C}]?([0-9]+)\x{53F7}[\x{FF09}]?/u"
    );

    public static $DefaultNegPatterns = array(
        "/\x{300A}(.*?)\x{300B}/u"  => "negs",
    );

    public static $DefaultSummaryWords = array(
        "author"    => array("发布机构", "发文机关", "发布单位", "发文机构", "办文部门", "颁布单位"),
        "title"     => array("公文名称", "信息名称", "标题", "名称",),
        "tags"      => array("效力级别","所属类别", "主题分类","信息类别","分类"),
        "index_ori_no"  => array("索引号","信息索引"),
        "doc_ori_no"    => array("文号","发文字号"),
        "publish_time"  => array("发文日期","发布日期","发布时间","颁布日期"),
        "cwrq_time" => array("成文日期", "生成日期"),
        "keywords"  => array("主题词"),
        "t_valid"   => array("执行日期", "生效日期", "实施日期"),
        "t_invalid" => array("失效日期", "时效性"),
        //"dump"  => array("分享到","时效性", "免责声明"),
    );

    public static $DefaultSummaryPatterns = array(
        "/\x{7D22}\x{5F15}\x{53F7}[\x{FF1A}:]\|?(.*?)\|\x{4E3B}\x{9898}\x{5206}\x{7C7B}[\x{FF1A}:]\|?(.*?)\|/u"   => "index_ori_no,tags",
        "/\x{53D1}\x{6587}\x{673A}\x{5173}[\x{FF1A}:]\|?(.*?)\|\x{6210}\x{6587}\x{65E5}\x{671F}[\x{FF1A}:]\|?(.*?)\|/u"  => "author,cwrq",
        "/\x{6807}\x{9898}[\x{FF1A}:]\|?(.*?)\|/u"    => "title",
        "/\x{53D1}\x{6587}\x{5B57}\x{53F7}[\x{FF1A}:]\|?(.*?)\|\x{53D1}\x{5E03}\x{65E5}\x{671F}[\x{FF1A}:]\|?(.*?)\|/u"  => "doc_ori_no,publish_time",
        "/\x{4E3B}\x{9898}\x{8BCD}[\x{FF1A}:]\|?(.*?)\|/u"  => "tags",
        "/\x{81EA}([0-9]{4}\x{5E74}[0-9]+\x{6708}[0-9]+\x{65E5})\x{8D77}\x{65BD}\x{884C}/u" => "t_valid",
        "/\x{516C}\x{6587}\x{540D}\x{79F0}:\|?(.*?)\|/u" => "title",
        "/\x{6587}\x{53F7}[\x{FF1A}:]\|?(.*?)\|/u" => "doc_ori_no",
        "/\x{540D}\x{79F0}[\x{FF1A}:]\|?(.*?)\|/u"  => "title",
        "/\x{53D1}\x{5E03}\x{673A}\x{6784}[\x{FF1A}:]\|?(.*?)\|?/u" => "author",
        "/\x{53D1}\x{6587}\x{65E5}\x{671F}[\x{FF1A}:]\|?(.*?)\|?/u"   => "publish_time",
        "/\x{7D22}\x{5F15}\x{53F7}[\x{FF1A}:]\|?(.*)\|?/u"  => 'index_ori_no',
        "/\x{516C}\x{6587}\x{540D}\x{79F0}[\x{FF1A}:](.*)\|?/u"    => 'title',
        "/\x{529E}\x{6587}\x{90E8}\x{95E8}[\x{FF1A}:](.*)\|?/u"    => 'author',
        "/\x{53D1}\x{6587}\x{65E5}\x{671F}[\x{FF1A}:](.*)\|?/u"    => 'publish_time',
        "/\x{4E3B}\x{9898}\x{5206}\x{7C7B}[\x{FF1A}:](.*)\|?/u"    => 'tags',
        "/\x{5206}\x{7C7B}[\x{FF1A}:](.*)\|?/u"     => 'tags',
    );

    public static $DefaultTOCPatterns = array(
        "/(\x{7B2C}[\x{4E00}\x{4E8C}\x{4E09}\x{56DB}\x{4E94}\x{516D}\x{4E03}\x{516B}\x{4E5D}\x{5341}]+\x{7AE0})/u"  => "zhang",
        "/(\x{7B2C}[\x{4E00}\x{4E8C}\x{4E09}\x{56DB}\x{4E94}\x{516D}\x{4E03}\x{516B}\x{4E5D}\x{5341}]+\x{8282})/u"  => "section",
        "/(\x{7B2C}[\x{4E00}\x{4E8C}\x{4E09}\x{56DB}\x{4E94}\x{516D}\x{4E03}\x{516B}\x{4E5D}\x{5341}]+\x{6761})/u"  => "tiao",
    );

    /**
     * ExtractContent constructor.
     * @param $url
     * @param string $baseurl
     */
    public function __construct($url, $baseurl = "", $raw_content = '')
    {
        if (!file_exists($this->storage_root . $this->raw_data_dir)) {
            MeLog::fatal("data not exists: " . $this->storage_root . $this->raw_data_dir);
            exit(0);
        }

        $this->url = $url;
        $this->baseurl = $baseurl;
        $this->raw_content = $raw_content;
    }

    /**
     * @param $root
     * @param $text
     */
    protected function _toText($root, &$text)
    {
        $child_nodes = $root->childNodes;

        $len = $child_nodes->length;

        for ($i = 0; $i < $len; $i++) {
            $item = $child_nodes->item($i);
            $node_name = strtolower($item->nodeName);
            switch ($item->nodeType) {
                case XML_ELEMENT_NODE:
                    switch ($node_name) {
                        case "br":
                            $text[] = "\n";
                            break;
                        case "strong":
                        case "h1":
                        case "h2":
                        case "h3":
                        case "h4":
                            $this->title_texts[$node_name] = trim($item->nodeValue);
                            break;
                        case "div":
                        case "p":
                        case "tr":
                        case "ul":
                        case "ol":
                        case "li":
                            $text[] = "\n";
                            break;
                    }

                    if (strtolower($item->nodeName) == "head") {
                        break;
                    }

                    if ($node_name == "p") {
                        $this->textP[] = $item->nodeValue;
                        $this->pCharacterLen += mb_strlen($item->nodeValue, "UTF-8");
                    }

                    if ($node_name == "td" || $node_name == "th" || $node_name == "tbody") {
                        $child_nodes1 = $item->childNodes;
                        $len1 = $child_nodes1->length;
                        for($j = 0; $j < $len1; $j++) {
                            $item1 = $child_nodes1->item($j);
                            $node_name1 = strtolower($item1->nodeName);
                            if ($node_name1 == 'table') {
                                $this->skip_td_childs = false;
                            }
                        }
                    }

                    if (($node_name == "td" || $node_name == "th") && empty($this->skip_td_childs)) {
                        $this->_toText($item, $text);
                    } else if ($node_name == "td" || $node_name == "th") {
                        $text[] = trim($item->nodeValue);
                    } else {
                        $this->_toText($item, $text);
                    }

                    if ($node_name == "td" || $node_name == "th") {
                        $text[] = "|";
                    }

                    break;
                case XML_TEXT_NODE:
                    $text[] = trim($item->nodeValue);
                    $this->textCharacterLen += mb_strlen($item->nodeValue, "UTF-8");
                    break;
                default:
                    break;
            }
        }
    }

    /**
     * Levenshtein Distance
     * @param $str1
     * @param $str2
     * @return mixed
     */
    protected function editDistance($str1, $str2)
    {
        $len1 = mb_strlen($str1, "UTF-8");
        $len2 = mb_strlen($str2, "UTF-8");

        $dp = array();

        for ($i = 0; $i <=$len1; $i++) {
            $e = array();
            for ($j = 0; $j <= $len2; $j++) {
                $e[] = 0;
            }
            $dp[] = $e;
        }

        for ($i = 0; $i <= $len1; $i++) {
            $dp[$i][0] = $i;
        }

        for ($j = 0; $j <= $len2; $j++) {
            $dp[0][$j] = $j;
        }

        for ($i = 0; $i < $len1; $i++) {
            $char_i = mb_substr($str1, $i, 1, "UTF-8");
            for ($j = 0; $j < $len2; $j++) {
                $char_j = mb_substr($str2, $j, 1, "UTF-8");

                if ($char_i == $char_j) {
                    $dp[$i + 1][$j + 1] = $dp[$i][$j];
                } else {
                    $replace = $dp[$i][$j] + 1;
                    $insert = $dp[$i][$j + 1] + 1;
                    $delete = $dp[$i + 1][$j] + 1;

                    $dp[$i + 1][$j + 1] = min($replace, $insert, $delete);
                }
            }
        }

        return $dp[$len1][$len2];
    }

    public function parseSummary(&$index_blocks)
    {
        $index_str = implode("\n", $index_blocks);

        $summary = array();

        $reverse = array();

        foreach (self::$DefaultSummaryWords as $field => $needles) {
            foreach ($needles as $needle) {
                if (isset($summary[$field]) && !empty($field)) break;

                $p = mb_strpos($index_str, $needle, 0, "UTF-8");
                if ($p === false) {
                    continue;
                }

                $len = mb_strlen($needle, "UTF-8");
                $t = mb_substr($index_str, $p + mb_strlen($needle, "UTF-8"), 1);

                if ($t == ":" || $t == "：" || $t == "】") {
                    $reverse[] = $p;
                    $summary[$field] = array("pos"=>$p, "len" => $len + 1);
                }
            }
        }

        sort($reverse);

        $len = count($reverse);

        foreach ($summary as $field => $item) {
            for ($i = 0; $i < $len; $i++) {
                if ($item['pos'] == $reverse[$i]) {
                    if ($i < $len - 1) {
                        $this->$field = trim(str_replace("|", "", trim(mb_substr($index_str, $item['pos'] + $item['len'], $reverse[$i + 1] - ($item['pos'] + $item['len'])))));
                    } else {
                        $r = mb_strpos($index_str, "\n", $item['pos'] + $item['len']);
                        if ($r === false) {
                            $this->$field = trim(str_replace("|", "", trim(mb_substr($index_str, $item['pos'] + $item['len']))));
                        } else {
                            $this->$field = trim(str_replace("|", "", trim(mb_substr($index_str, $item['pos'] + $item['len'], $r - ($item['pos'] + $item['len'])))));
                        }
                    }
                    break;
                }
            }
        }

        $this->guessTitle();

        if ($this->doc_ori_no == "无") {
            $this->doc_ori_no = "";
        }

        // doc_ori_no归一化
        if (!empty($this->doc_ori_no)) {
            preg_match(self::$DefaultDocOriNoPatterns[0], $this->doc_ori_no, $matches);
            if (!empty($matches) && count($matches) > 3) {
                $this->doc_ori_no = sprintf("%s(%s)%s号", $matches[1], $matches[2], $matches[3]);
            }
        } else {
            $l = count($this->textP);
            $l = $l < 20 ? $l : 20;

            if (!empty($this->meta_title)) {
                preg_match(self::$DefaultDocOriNoPatterns[0], $this->meta_title, $matches);
                if (!empty($matches) && count($matches) > 3) {
                    $this->doc_ori_no = sprintf("%s(%s)%s号", $matches[1], $matches[2], $matches[3]);
                }
            }

            if (empty($this->doc_ori_no)) {
                for ($i = 0; $i < $l; $i++) {
                    preg_match(self::$DefaultDocOriNoPatterns[0], $this->textP[$i], $matches);
                    if (!empty($matches) && count($matches) > 3) {
                        $this->doc_ori_no = sprintf("%s(%s)%s号", $matches[1], $matches[2], $matches[3]);
                        break;
                    }
                }
            }
        }

        if (!empty($this->publish_time)) {
            $matches = array();
            preg_match("/([0-9]{4})[\x{5E74}\-]([0-9]{1,2})[\x{6708}\-]([0-9]{1,2})[\x{65E5}]?.*/u", $this->publish_time, $matches);
            if (!empty($matches) && count($matches) > 3) {
                $this->publish_time = strtotime(sprintf("%s-%s-%s", $matches[1], $matches[2], $matches[3]));
            }
        }

        if (!empty($this->t_valid)) {
            $matches = array();
            preg_match("/([0-9]{4})[\x{5E74}\-]([0-9]{1,2})[\x{6708}\-]([0-9]{1,2})[\x{65E5}]?.*/u", $this->t_valid, $matches);
            if (!empty($matches) && count($matches) > 3) {
                $this->t_valid = strtotime(sprintf("%s-%s-%s", $matches[1], $matches[2], $matches[3]));
            }
        }

        if (!empty($this->t_invalid)) {
            $matches = array();
            preg_match("/([0-9]{4})[\x{5E74}\-]([0-9]{1,2})[\x{6708}\-]([0-9]{1,2})[\x{65E5}]?.*/u", $this->t_invalid, $matches);
            if (!empty($matches) && count($matches) > 3) {
                $this->t_invalid = strtotime(sprintf("%s-%s-%s", $matches[1], $matches[2], $matches[3]));
            }
        }

        if (!empty($this->cwrq_time)) {
            $matches = array();
            preg_match("/([0-9]{4})[\x{5E74}\-]([0-9]{1,2})[\x{6708}\-]([0-9]{1,2})[\x{65E5}]?.*/u", $this->cwrq_time, $matches);
            if (!empty($matches) && count($matches) > 3) {
                $this->cwrq_time = strtotime(sprintf("%s-%s-%s", $matches[1], $matches[2], $matches[3]));
            }
        }
    }

    /**
     * 索引块提取
     * 规则定义
     * 所有不以标点符号结尾,且中间含有:的
     * 行开头到第一个:间的文字长度通常在4个以内
     */
    public function indexBlock(&$text_lines)
    {
        if (!empty($this->index_blocks)) {
            return $this->index_blocks;
        }

        $index_blocks = array();
        foreach ($text_lines as $text_line) {
            $text_line = preg_replace("/[\s\x{3000}\x{3010}]+/u", "", trim($text_line));
            if (!empty($text_line)) {
                preg_match("/^([\x{4e00}-\x{9fa5}\\s+]{2,8})[\x{FF1A}\x{3011}:].*/ui", $text_line, $matches) ? $index_blocks[] = $text_line : null;
            }
        }

        $this->index_blocks = $index_blocks;

        return $index_blocks;
    }

    /**
     * 猜测标题
     * @return string
     */
    public function guessTitle()
    {
        $dom = $this->extractor->domDocument();
        if (empty($dom)) return "";

        $titleTag = $dom->getElementsByTagName("title")->item(0);
        $title = !empty($titleTag) ? $titleTag->nodeValue : "";

        if (mb_strpos($title, "国家新闻出版广电总局", 0, "UTF-8") === 0) {
            return $this->meta_title = $title;
        }

        if (!empty($title)) {
            $c = self::multiexplode(array("_","-"), $title);
            if (!empty($c) && is_array($c)) {
                $this->meta_title = trim($c[0]);
            } else {
                $this->meta_title = trim($title);
            }
        } else {
            $this->meta_title = "";
        }

        return $this->meta_title;
    }

    private static function multiexplode ($delimiters,$string) {

        $ready = str_replace($delimiters, $delimiters[0], $string);
        $launch = explode($delimiters[0], $ready);
        return  $launch;
    }

    /**
     * get all date info
     * @param $text_lines
     */
    protected function getAllTimes(&$text_lines)
    {
        foreach ($text_lines as $text_line) {
            preg_match_all("/([0-9]{4}\x{5E74}[0-9]+\x{6708}[0-9]+\x{65E5})/u", $text_line, $matches);
            if (!empty($matches) && count($matches) > 1) {
                for ($i=1; $i < count($matches); $i++) {
                    $this->times[] = $matches[$i];
                }
            }
        }
    }

    /**
     * 获取生效时间（也是施行时间）
     * 规则:
     * 自2017年5月1日起施行
     * "/\x{81EA}([0-9]{4}\x{5E74}[0-9]+\x{6708}[0-9]+\x{65E5})\x{8D77}\x{65BD}\x{884C}/u"
     */
    protected function getTvalidTime(&$text_lines)
    {
        foreach ($text_lines as $text_line) {
            preg_match("/\x{81EA}([0-9]{4}\x{5E74}[0-9]+\x{6708}[0-9]+\x{65E5})\x{8D77}\x{65BD}\x{884C}/u", $text_line, $matches);
            !empty($matches) && count($matches) > 1 ? $this->t_valid = $matches[1] : "";
            if (!empty($this->t_valid))
                return true;
        }
        return false;
    }

    /**
     * 获取正文
     * @return string
     */
    public function getContent()
    {
        if (empty($this->textCharacterLen)) {
            return "";
        }

        $ratio = $this->pCharacterLen / $this->textCharacterLen;
        echo "ratio: " . $ratio . PHP_EOL;
        if ($ratio >= 0.01) {
            return implode("\n", $this->textP);
        }

        return $this->lineBlockDensityExtracting();
    }

    /**
     * 基于行密度分布的正文提取
     *
     * @param $raw
     * @param int $blocksize
     * @return string
     */
    protected function lineBlockDensityExtracting($raw = '', $blocksize = 3)
    {
        $textLines = array();
        $textBlockLens = array();

        $raw = implode("", $this->text);

        $lines = array();
        if (!empty($raw)) {
            $lines = explode("\n", $raw);
        }

        $len = count($lines);
        for($i = 0; $i < $len; $i++) {
            $textLines[] = preg_replace("#\s+# i", "", trim($lines[$i]));
        }

        $textLinesCnt = count($textLines);

        $blockLen = 0;
        $mblocksize = min($textLinesCnt, $blocksize);

        for ($i = 0; $i < $mblocksize; $i++) {
            $blockLen += mb_strlen($textLines[$i], "UTF-8");
        }

        $textBlockLens[] = $blockLen;

        if ($mblocksize == $blocksize) {
            for ($i=1; $i <= $textLinesCnt - $mblocksize; $i++) {
                $textBlockLens[$i] = $textBlockLens[$i - 1] + mb_strlen($textLines[$i -1 + $mblocksize]) - mb_strlen($textLines[$i - 1]);
            }
        }

        $i = $maxTextLen = 0;
        $blocksCnt = count($textBlockLens);
        $curTextLen = 0;
        $part = '';

        $text = '';
        while($i < $blocksCnt) {
            if ($textBlockLens[$i] > 0) {
                if (!empty($textLines[$i])) {
                    $part .= $textLines[$i] . "\n";
                    $curTextLen += mb_strlen($textLines[$i]);
                }
            } else if ($textBlockLens[$i] == 0) {
                $curTextLen = 0;
                $part = '';
            }

            if ($curTextLen > $maxTextLen) {
                $text = $part;
                $maxTextLen = $curTextLen;
            }
            $i++;
        }

        return $text;
    }

    public function toText()
    {
        if (!empty($this->text)) {
            return implode("", $this->text);
        }

        $dom = $this->extractor->domDocument();
        $root = $dom->documentElement;

        $text = array();

        $this->_toText($root, $text);

        $l = count($this->textP);
        $matched = false;
        for ($i = $l; $i > $l - 10; $i--) {
            for ($j = 0; $j<count(self::$DefaultBadWords); $j++) {
                $r = preg_match(self::$DefaultBadWords[$j], $this->textP[$i]);
                if (!empty($r)) {
                    $matched = true;
                    break;
                }
            }
            if ($matched) {
                break;
            }
        }

        unset($this->textP[$i]);
        $this->text = $text;
        return implode("", $text);
    }

    public function parseAttachments()
    {
        $doc = $this->getExtractor()->extractor->document();

        $links = $doc->query("//a");

        if ($links instanceof DOMNodeList && !empty($links)) {
            foreach ($links as $element) {
                if (strtolower($element->nodeName) === 'a') {
                    if ($element->hasAttribute('href')) {
                        $href = $element->getAttribute('href');
                        $href = Formatter::formaturl($this->url, $href);
                        $r = preg_match("#/[0-9a-zA-Z_]+\.(doc|pdf|txt|xls)# i", $href);
                        if (!empty($r)) {
                            $attachment = array(
                                'title' => trim($element->nodeValue),
                                'url'  => $href,
                            );

                            $this->attachments[] = $attachment;
                        }
                    }
                }
            }
        }
    }

    public function readComments()
    {
        $dom = $this->extractor->domDocument();

        $comments = array();
        if ($dom instanceof DOMDocument) {
            $remove_childs = array();
            $this->traverseTree($dom, $remove_childs);
            if (!empty($remove_childs)) {
                foreach ($remove_childs as $remove_child) {
                    $comments[] = $remove_child->nodeValue;
                }
            }
        }

        return $comments;
    }

    public function parse()
    {
        // get raw text
        $raw1 = $this->getExtractor()->removeSpecialTags()->toText();

        // get blocks
        $text_lines = explode("\n", $raw1);

        $text_lines = $this->indexBlock($text_lines);

        $this->getTvalidTime($this->text);
        $this->parseSummary($text_lines);

        $raw = $this->getContent();

        foreach (self::$DefaultNegPatterns as $defaultNegPattern => $field) {
            preg_match_all($defaultNegPattern, $raw, $matches);
            if (!empty($matches) && count($matches) > 1) {
                foreach ($matches[1] as $match) {
                    $this->negs[] = $match;
                }
            }
        }

        $this->negs = array_unique($this->negs);

        $this->parseAttachments();

        if (defined("DEBUG") && DEBUG) {
            echo "发布文号: " . implode(",", array_unique(array_filter(explode(",",($this->doc_ori_no))))) . PHP_EOL;
            echo "发布单位: " . implode(",", array_unique(array_filter(explode(",",($this->author))))) . PHP_EOL;
            echo "类型:" . implode(",", array_unique(array_filter(explode(",",($this->tags))))) . PHP_EOL;
            echo "发布时间: " . implode(",", array_unique(array_filter(explode(",",($this->publish_time))))) . PHP_EOL;
            echo "生效时间: " . implode(",", array_unique(array_filter(explode(",",($this->t_valid))))) . PHP_EOL;
            echo "公文名称: " . implode(",", array_unique(array_filter(explode(",",($this->title))))) . PHP_EOL;

            // title
            echo "标题: " . $this->guessTitle() . PHP_EOL;

            echo "NEGS: " . implode(",", $this->negs) . PHP_EOL;

            // contents
            // @TODO parse contents
            echo "正文: " . PHP_EOL . $raw . PHP_EOL;

            echo "附件: " . json_encode($this->attachments, JSON_UNESCAPED_UNICODE) . PHP_EOL;

            //echo "原文: " . PHP_EOL . $raw1 . PHP_EOL;
        }
    }

    /**
     * to HTML
     * @return mixed
     */
    public function toHTML()
    {
        $this->extractor->domDocument()->preserveWhiteSpace = false;
        $this->extractor->domDocument()->formatOutput = true;
        return $this->extractor->domDocument()->saveHTML();
    }

    /**
     * remove style, script, comment, cdata tags and its all children together
     * @return $this|bool
     */
    public function removeSpecialTags()
    {
        if (empty($this->extractor)) {
            MeLog::warning("extractor empty");
            return $this;
        }

        $dom = $this->extractor->domDocument();

        if ($dom instanceof DOMDocument) {
            $delete = array();

            foreach (self::$DefaultSpecialTags as $defaultSpecialTag) {
                $tags = $dom->getElementsByTagName($defaultSpecialTag);

                foreach ($tags as $tag) {
                    $delete[] = $tag;
                }
            }

            $remove_childs = array();
            $this->traverseTree($dom, $remove_childs);

            foreach ($remove_childs as $remove_child) {
                $delete[] = $remove_child;
            }

            foreach ($delete as $item) {
                $item->parentNode->removeChild($item);
            }
        }

        return $this;
    }

    protected function traverseTree($root, &$removechilds)
    {
        if ($root->hasChildNodes()) {
            $list = $root->childNodes;
            foreach ($list as $item) {
                if ($item->nodeType == XML_CDATA_SECTION_NODE || $item->nodeType == XML_COMMENT_NODE) {
                    $removechilds[] = $item;
                } else {
                    $this->traverseTree($item, $removechilds);
                }
            }
        }
    }

    protected function deleteNode($node) {
        $this->deleteChildren($node);
        $parent = $node->parentNode;
        $oldnode = $parent->removeChild($node);
    }

    protected function deleteChildren($node) {
        while (isset($node->firstChild)) {
            $this->deleteChildren($node->firstChild);
            $node->removeChild($node->firstChild);
        }
    }

    /**
     * @return $this
     * @throws bdHttpException
     */
    public function getExtractor()
    {
        if (isset($this->extractor)) return $this;

        if (!empty($this->raw_content)) {
            $this->extractor = new Extractor($this->raw_content, $this->url);
            return $this;
        }

        $raw = '';
        $pos = strpos($this->url, "http://");

        if ($pos === 0) {
            $baseurl = $this->url;
            $raw = bdHttpRequest::get($this->url);
            $content_type = $raw->getHeader("Content-Type");
            $prefix = "";
            if (!empty($content_type)) {
                preg_match("/[;]?charset=[^\w]?([-\w]+)/i", strtolower($content_type), $matches);
                if (!empty($matches) && is_array($matches) && count($matches) > 1) {
                    $charset = $matches[1];
                    $prefix = '<meta http-equiv="Content-Type" content="text/html; charset=' . $charset . '"/>'. "\n";
                }
            }
            $raw = $prefix . $raw->getBody();
        }

        $pos = strpos($this->url, "/");

        if ($pos === 0) {
            $raw = $this->loadFromFile($this->url);
        }

        $this->extractor = new Extractor($raw, $this->baseurl);
        return $this;
    }

    /**
     * load raw data from file
     * @param $filename
     * @return string
     */
    protected function loadFromFile($filename, $skiplines = 2)
    {
        if (!file_exists($filename)) {
            echo "content not exist: {$filename}" . PHP_EOL;
            return "";
        }

        $f = fopen($filename, "r");

        $this->url = $this->baseurl = fgets($f);

        $p = strpos($this->url, "http://www.cbrc.gov.cn/govView_");

        if ($p === 0) {
            $this->charset = "UTF-8";
        }

        fgets($f);

        $raw = "";

        if (!empty($this->charset)) {
            $raw = '<meta http-equiv="Content-Type" content="text/html; charset=' . $this->charset . '"/>'. "\n";
        }

        while($buf = fgets($f)) {
            $raw .= $buf;
        }

        fclose($f);

        return $raw;
    }
}

//$extract = new ExtractContent("http://www.gov.cn/zhengce/content/2016-12/14/content_5147959.htm", "http://www.gov.cn/zhengce/content/2016-12/14/content_5147959.htm");
//$extract = new ExtractContent("http://www.npc.gov.cn/npc/xinwen/2017-03/15/content_2018721.htm", "http://www.npc.gov.cn/npc/xinwen/2017-03/15/content_2018721.htm");
//$extract = new ExtractContent("http://www.gov.cn/zhengce/content/2017-02/23/content_5170264.htm", "http://www.gov.cn/zhengce/content/2017-02/23/content_5170264.htm");
//$extract = new ExtractContent("http://www.csrc.gov.cn/pub/zjhpublic/G00306225/201704/t20170411_314977.htm", "http://www.csrc.gov.cn/pub/zjhpublic/G00306225/201704/t20170411_314977.htm");
//$extract = new ExtractContent("http://www.cbrc.gov.cn/govView_9209A638DB7E4708A3EC79A2F3B151E7.html", "http://www.cbrc.gov.cn/govView_9209A638DB7E4708A3EC79A2F3B151E7.html");
//$extract = new ExtractContent("http://www.npc.gov.cn/npc/xinwen/2017-03/15/content_2018912.htm", "http://www.npc.gov.cn/npc/xinwen/2017-03/15/content_2018912.htm");
//$extract = new ExtractContent("/mnt/open-xdp/spider/raw_data/20170410/c6635b75c6ba2c2d1c2ac62e9efdf67e.html", "http://www.circ.gov.cn/web/site0/tab5241/info4051366.htm");
//$extract = new ExtractContent("http://www.npc.gov.cn/npc/xinwen/2016-11/07/content_2001584.htm", "http://www.npc.gov.cn/npc/xinwen/2016-11/07/content_2001584.htm");
//$extract = new ExtractContent("http://www.circ.gov.cn/web/site0/tab5241/info222000.htm", "http://www.circ.gov.cn/web/site0/tab5241/info222000.htm");
//$extract = new ExtractContent("/mnt/open-xdp/spider/raw_data/20170410/db9db90642342df87c79479ea9e55e2f.html");
//$extract = new ExtractContent("http://dy.chinasarft.gov.cn/shanty.deploy/blueprint.nsp?id=015b7b22322b33a2402881a659a4b9fa&templateId=0129f8148f650065402881cd29f7df33", "http://dy.chinasarft.gov.cn/shanty.deploy/blueprint.nsp?id=015b7b22322b33a2402881a659a4b9fa&templateId=0129f8148f650065402881cd29f7df33");

//$extract->skip_td_childs = true;
//$extract->parse();

function loadDyChinasarftFromDB()
{
    $data = DaoXlegalLawContentRecord::getInstance()->search_data();

    foreach ($data as $item) {
        if (!empty($item)) {
            $id = $item['id'];
            $url_md5 = $item['url_md5'];

            $file = "/mnt/open-xdp/spider/raw_data/20170418/" . $url_md5 . ".html";
            if (!file_exists($file)) {
                $file = "/mnt/open-xdp/spider/raw_data/20170419/" . $url_md5 . ".html";
                if (!file_exists($file)) {
                    echo "file not exists: {$url_md5}" . PHP_EOL;
                    continue;
                }
            }

            $extract = new ExtractContent($file);

            $extract->skip_td_childs = true;

            $extract->parse();

            $useRawContent = false;
            foreach ($extract->text as $t) {
                if (mb_strpos($t, "电影拍摄制作备案公示表", 0, "UTF-8") >= 0) {
                    $useRawContent = true;
                }
            }

            if ($useRawContent) {
                if (empty($extract->doc_ori_no)) {
                    $x = explode("\n", implode("", array_filter($extract->text)));
                    $l = count($x);
                    $l = $l < 20 ? $l : 20;
                    for ($i = 0; $i < $l; $i++) {
                        preg_match(ExtractContent::$DefaultDocOriNoPatterns[0], $x[$i], $matches);
                        if (!empty($matches) && count($matches) > 3) {
                            $extract->doc_ori_no = sprintf("%s(%s)%s号", $matches[1], $matches[2], $matches[3]);
                            break;
                        }
                    }
                }
            }

            if ($useRawContent) {
                $content = trim(implode("", array_filter($extract->text)));
            } else {
                $content = $extract->getContent();
            }

            $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
            $record = new XlegalLawContentRecord();
            $record->doc_id = md5($c);
            $record->title = !empty($extract->title) ? $extract->title : $extract->guessTitle();
            $record->author = $extract->author;
            $record->content = $content;
            $record->doc_ori_no = $extract->doc_ori_no;
            $record->publish_time = $extract->publish_time;
            $record->t_valid = $extract->t_valid;
            $record->t_invalid = $extract->t_invalid;
            $record->negs = implode(",", $extract->negs);
            $record->tags = $extract->tags;
            if (!empty($extract->attachments)) {
                $record->attachment = json_encode($extract->attachments, JSON_UNESCAPED_UNICODE);
            }

            if (empty(gsettings()->debug)) {
                $res = FlaskRestClient::GetInstance()->simHash($c);

                $simhash = '';
                if (isset($res['simhash']) && !empty($res['simhash'])) {
                    $simhash = $res['simhash'];
                }

                if (isset($res['repeated']) && !empty($res['repeated'])) {
                    echo 'data repeated: ' . $file . ', repeated simhash: ' . $res['simhash1'] .PHP_EOL;
                }

                $record->type = DaoSpiderlLawBase::TYPE_TXT;
                $record->status = 1;
                $record->url = trim($extract->baseurl);
                $record->url_md5 = md5($extract->url);
                $record->simhash = $simhash;
            }

            if (false) {
                echo implode("", $extract->text) . PHP_EOL;
                var_dump($record);
                return false;
            }

            DaoXlegalLawContentRecord::getInstance()->update($id, json_decode(json_encode($record, JSON_UNESCAPED_UNICODE), true));
        }
    }
}

//loadDyChinasarftFromDB();

function loadJSONFromDB()
{
    $data = DaoSpiderlLawBase::getInstance()->queryOneBatch(DaoSpiderlLawBase::TYPE_JSON);
    $id = 1;
    while ($id <= 109464) {
        if (!empty($data)) {
            foreach ($data as $item) {
                if (empty($item['content'])) {
                    continue;
                }

                $content = json_decode($item['content'], true);

                if (empty($content)) {
                    continue;
                }

                $record = new XlegalLawContentRecord();
                $record->url = $item['url'];
                $record->url_md5 = $item['url_md5'];
                $record->title = $content['title'];
                $record->doc_ori_no = isset($content['doc_ori_no']) ? $content['doc_ori_no'] : '';
                $record->publish_time = isset($content['publish_time']) ? intval($content['publish_time']) : 0;
                $record->t_valid = isset($content['t_valid']) ? intval($content['t_valid']) : 0;
                $record->author = isset($content['author']) ? $content['author'] : '';
                $record->tags = isset($content['tags']) ? $content['tags'] : '';

                $negs = array();
                foreach (ExtractContent::$DefaultNegPatterns as $defaultNegPattern => $field) {
                    preg_match_all($defaultNegPattern, $content['content'], $matches);
                    if (!empty($matches) && count($matches) > 1) {
                        foreach ($matches[1] as $match) {
                            $negs[] = $match;
                        }
                    }
                }

                $record->negs = implode(",", array_unique($negs));

                // doc_ori_no归一化
                if (!empty($record->doc_ori_no)) {
                    preg_match(ExtractContent::$DefaultDocOriNoPatterns[0], $record->doc_ori_no, $matches);
                    if (!empty($matches) && count($matches) > 3) {
                        $record->doc_ori_no = sprintf("%s(%s)%s号", $matches[1], $matches[2], $matches[3]);
                    }
                }

                $c = preg_replace("/[\s\x{3000}]+/u", "", $content['content']);
                $res = FlaskRestClient::GetInstance()->simHash($c);

                $simhash = '';
                if (isset($res['simhash']) && !empty($res['simhash'])) {
                    $simhash = $res['simhash'];
                }

                if (isset($res['repeated']) && !empty($res['repeated'])) {
                    echo 'data repeated: ' . $item['url'] .  " " . $simhash . PHP_EOL;
                    continue;
                }

                $record->doc_id = md5($c);
                $record->type = DaoSpiderlLawBase::TYPE_TXT;
                $record->status = 1;
                $record->simhash = $simhash;
                $record->content = $content['content'];

                DaoXlegalLawContentRecord::getInstance()->insert($record);
            }
        }

        $id += 100;
        $data = DaoSpiderlLawBase::getInstance()->queryOneBatch(DaoSpiderlLawBase::TYPE_JSON);
    }
}

//loadJSONFromDB();

function loadHTMLfromdb()
{
    $data = DaoSpiderlLawBase::getInstance()->queryOneBatch(DaoSpiderlLawBase::TYPE_HTML);
    $id = 1;
    while($id<=109464) {
        if (!empty($data)) {
            foreach ($data as $item) {
                $url = trim($item['url']);
                $url_md5 = md5($url);
                $id = $item['id'];
                DaoXlegalLawContentRecord::getInstance()->update($id, array(
                    'url'   => $url,
                    'url_md5'   => $url_md5
                ));

                file_put_contents("./test_tmp.html", $item['url'] . "\n");
                file_put_contents("./test_tmp.html", "text/html\n", FILE_APPEND);
                file_put_contents("./test_tmp.html", $item['content'] . "\n", FILE_APPEND);

                $extract = new ExtractContent("/home/work/xdp/phpsrc/app/LightCrawler/worker/test_tmp.html");
                $extract->parse();
                $content = $extract->getContent();
                $c = preg_replace("/[\s\x{3000}]+/u", "", $content);
                $record = new XlegalLawContentRecord();
                $record->doc_id = md5($c);
                $record->title = !empty($extract->title) ? $extract->title : $extract->guessTitle();
                $record->author = $extract->author;
                $record->content = $content;
                $record->doc_ori_no = $extract->doc_ori_no;
                $record->publish_time = $extract->publish_time;
                $record->t_valid = $extract->t_valid;
                $record->t_invalid = $extract->t_invalid;
                $record->negs = implode(",", $extract->negs);
                $record->tags = $extract->tags;

                $res = FlaskRestClient::GetInstance()->simHash($c);

                $simhash = '';
                if (isset($res['simhash']) && !empty($res['simhash'])) {
                    $simhash = $res['simhash'];
                }

                if (isset($res['repeated']) && !empty($res['repeated'])) {
                    echo 'data repeated: ' . $item['url'] . PHP_EOL;
                    continue;
                }
                $record->type = DaoSpiderlLawBase::TYPE_TXT;
                $record->status = 1;
                $record->url = $extract->baseurl;
                $record->url_md5 = md5($extract->url);
                $record->simhash = $simhash;

                DaoXlegalLawContentRecord::getInstance()->insert($record);
            }
        }
        $id += 100;
        $data = DaoSpiderlLawBase::getInstance()->queryOneBatch(DaoSpiderlLawBase::TYPE_HTML);
    }
}
//loadHTMLfromdb();
function test()
{
    $dirs = explode(",", "20170401,20170403,20170404,20170405,20170406,20170407,20170408,20170409,20170410,20170411,20170412");

    $root = "/mnt/open-xdp/spider/raw_data";

    $data = array();
    foreach ($dirs as $dir) {
        $file = $root . "/" . $dir;

        $files = dir($file);

        while ($f = $files->read()) {
            if ($f == "." || $f == "..") continue;
            $filename = $file . "/" . $f;

            $extract = new ExtractContent($filename);
            $extract->parse();
            $c = preg_replace("/[\s\x{3000}]+/u", "", $extract->getContent());
            $r = array();
            $r["filename"] = $f;
            $r["doc_ori_no"] = !empty($extract->doc_ori_no) ? $extract->doc_ori_no : 'NULL';
            $r["title"] = !empty($extract->title) ? $extract->title : $extract->guessTitle();
            $r["doc_id"] = md5($c);
            $r['simhash'] = "NULL";
            $r['repeated'] = 0;

            if (mb_strpos($r["title"], "无标题", 0, "UTF-8") !== false
                || mb_strpos($r["title"], "列表页", 0, "UTF-8") !== false) {
                continue;
            }
            $res = FlaskRestClient::GetInstance()->simHash($c);

            $simhash = '';
            if (isset($res['simhash']) && !empty($res['simhash'])) {
                $r['simhash'] = $res['simhash'];
            }

            if (isset($res['repeated']) && !empty($res['repeated'])) {
                $r['repeated'] = $res['repeated'];
            }
            $str = implode("\t", $r);
            file_put_contents("result.txt", $str . PHP_EOL, FILE_APPEND);
            unset($extract);
        }
    }
}

//test();