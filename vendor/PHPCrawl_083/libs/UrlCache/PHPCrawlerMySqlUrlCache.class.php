<?php

/**
 * Created by PhpStorm.
 * User: liangtaohy@163.com
 * Date: 17/3/30
 * Time: PM5:56
 */
class PHPCrawlerMySqlUrlCache extends PHPCrawlerURLCacheBase
{
    protected $_handle;
    protected $_config;
    protected $_spidername;

    /**
     * PHPCrawlerMySqlUrlCache constructor.
     * @param $config
     */
    public function __construct($config)
    {
        if (defined("CRAWLER_NAME") && !empty(CRAWLER_NAME)) {
            $this->_spidername = CRAWLER_NAME;
        } else {
            $this->_spidername = "";
        }
        $this->openConnection($config);
    }

    /**
     * 连接数据库
     * @param $config
     *      array(
     *          'host' => 'xxx.xxx.xxx.xxx',
     *          'port'  => 1234,
     *          'user'  => 'lt',
     *          'password'  => '123a232',
     *          'dbname'    => 'testdb',
     *          'connect_timeout'   => 1,
     *      )
     * @return int
     */
    protected function openConnection($config)
    {
        $this->_config = $config;
        if (is_array($config) && empty($config)) {
            echo 'config is needed!!' . PHP_EOL;
            return false;
        }

        $mysqli = $this->_handle = mysqli_init();

        if (!$mysqli) {
            echo 'mysql[mysqli_init] result[failed]' . PHP_EOL;
            return false;
        }

        if (!$mysqli->options(MYSQLI_INIT_COMMAND, 'SET AUTOCOMMIT = ' . $this->_config['autocommit'])) {
            echo 'options[autocommit] expected[' . $this->_config['autocommit'] . '] result[failed]' . PHP_EOL;
            return false;
        }

        if (!$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, $this->_config['connect_timeout'])) {
            echo 'options[connect_timeout] expected[' . $this->_config['connect_timeout'] . '] result[failed]' . PHP_EOL;
            return false;
        }

        if ($mysqli->real_connect($this->_config['host'], $this->_config['user'], $this->_config['password'], $this->_config['dbname'], $this->_config['port']) === false) {
            echo 'connect[' . $this->_config['dbname'] . '] config[' . json_encode($this->_config) . '] errmsg[' . mysqli_connect_error() . ']' . PHP_EOL;
            return false;
        }

        $this->setCharset(isset($config['charset']) ? $config['charset'] : 'utf8');

        return true;
    }

    /**
     * 设置默认编码
     * @param string $charset
     * @return bool
     */
    public function setCharset($charset = 'utf8')
    {
        if (!$this->_handle || !is_string($charset) || empty($charset)) {
            echo 'mysql[set_charset] expected[' . $charset . '] result[failed]' . PHP_EOL;
            return false;
        }

        if (!$this->_handle->set_charset($charset)) {
            echo 'mysql[set_charset] expected[' . $charset . '] result[failed]' . PHP_EOL;
            return false;
        }
        $this->_config['charset'] = $charset;
        return true;
    }

    public function getUrlCount()
    {
        $sql = "SELECT count(id) AS sum FROM urls WHERE processed = 0";

        if (!empty($this->_spidername)) {
            $sql .= " AND spider='" . $this->_spidername . "'";
        }

        $Result = $this->_handle->query($sql);
        $row = $Result->fetch_assoc();
        if (empty($row)) {
            return 0;
        }
        $Result->free();
        return intval($row["sum"]);
    }

    /**
     * Returns the next URL from the cache that should be crawled.
     *
     * @return PhpCrawlerURLDescriptor
     */
    public function getNextUrl()
    {
        PHPCrawlerBenchmark::start("fetching_next_url_from_sqlitecache");

        // require table lock
        $this->_handle->query("lock table urls write");

        // Get row with max priority-level
        $sql = "SELECT max(priority_level) AS max_priority_level FROM urls WHERE in_process = 0 AND processed = 0";
        if (!empty($this->_spidername)) {
            $sql .= " AND spider='" . $this->_spidername . "'";
        }
        $Result = $this->_handle->query($sql);

        if (empty($Result)) {
            $this->_handle->query("unlock tables");
            return null;
        }

        $row = $Result->fetch_assoc();

        if ($row["max_priority_level"] == null)
        {
            $Result->free();
            $this->_handle->query("unlock tables");
            return null;
        }

        $sql = "SELECT * FROM urls WHERE priority_level = ".$row["max_priority_level"]." and in_process = 0 AND processed = 0";
        if (!empty($this->_spidername)) {
            $sql .= " AND spider='" . $this->_spidername . "'";
        }
        $sql .= " LIMIT 1";
        $Result = $this->_handle->query($sql);
        $row = $Result->fetch_assoc();

        $fields = $Result->fetch_fields();
        $fieldInfoR = array();
        foreach ($fields as $index => $field)
            if ($field->type < 10 || $field->type == 246)
                $fieldInfoR[] = $field->name;

        foreach ($fieldInfoR as $key)
            $row[$key] += 0;

        $Result->free();

        // Update row (set in process-flag)
        $sql = "UPDATE urls SET in_process = 1 WHERE id = ".$row["id"];

        $this->_handle->query($sql);

        PHPCrawlerBenchmark::stop("fetching_next_url_from_sqlitecache");

        // release table lock
        $this->_handle->query("unlock tables");

        // Return URL
        return new PHPCrawlerURLDescriptor($row["url_rebuild"], $row["link_raw"], $row["linkcode"], $row["linktext"], $row["refering_url"], $row["url_link_depth"]);
    }

    /**
     * Returns all URLs currently cached in the URL-cache.
     *
     * @return array Numeric array containing all URLs as PHPCrawlerURLDescriptor-objects
     */
    public function getAllURLs()
    {

    }

    /**
     * Removes all URLs and all priority-rules from the URL-cache.
     */
    public function clear()
    {
        //
    }

    private static function microTime() {
        $temp = explode(" ", microtime());
        return intval(bcadd($temp[0], $temp[1], 6) * 1000);
    }

    /**
     * Adds an URL to the url-cache
     *
     * @param PHPCrawlerURLDescriptor $UrlDescriptor
     */
    public function addURL(PHPCrawlerURLDescriptor $UrlDescriptor)
    {
        if ($UrlDescriptor == null) return;

        // Hash of the URL
        $map_key = md5($UrlDescriptor->url_rebuild);

        $s = "SELECT id FROM `xlegal_law_content` WHERE url_md5='{$map_key}'";

        // Check if url has existed in finished url queue
        $r = $this->_handle->query($s);
        if (!empty($r)) {
            $arrResult = $r->fetch_assoc();
            if ($arrResult) {
                return;
            }
        }

        // Get priority of URL
        $priority_level = $this->getUrlPriority($UrlDescriptor->url_rebuild);

        $ctime = self::microTime();
        $value = array("priority_level" => $priority_level,
            "distinct_hash" => $map_key,
            "link_raw" => $UrlDescriptor->link_raw,
            "linkcode" => $UrlDescriptor->linkcode,
            "linktext" => $UrlDescriptor->linktext,
            "refering_url" => $UrlDescriptor->refering_url,
            "url_rebuild" => $UrlDescriptor->url_rebuild,
            "is_redirect_url" => intval($UrlDescriptor->is_redirect_url),
            "url_link_depth" => intval($UrlDescriptor->url_link_depth),
            "spider"    => $this->_spidername,
            "ctime" => $ctime,
            "mtime" => 0,
        );

        $vs = array();
        foreach ($value as $key => $item) {
            $vs[] = "{$key}='" . $item . "'";
        }

        $sql = "INSERT IGNORE INTO urls SET " . implode(",", $vs);

        $result = $this->_handle->query($sql);
    }

    /**
     * Adds an bunch of URLs to the url-cache
     *
     * @param array $urls  A numeric array containing the URLs as PHPCrawlerURLDescriptor-objects
     */
    public function addURLs($urls)
    {
        PHPCrawlerBenchmark::start("adding_urls_to_sqlitecache");

        $cnt = count($urls);
        for ($x=0; $x<$cnt; $x++)
        {
            if ($urls[$x] != null)
            {
                $this->addURL($urls[$x]);
            }

            // Commit after 1000 URLs (reduces memory-usage)
            if ($x%1000 == 0 && $x > 0)
            {
                // do nothing
            }
        }

        PHPCrawlerBenchmark::stop("adding_urls_to_sqlitecache");
    }

    /**
     * Gets the priority-level of the given URL
     */
    protected function getUrlPriority($url)
    {
        $cnt = count($this->url_priorities);
        for ($x=0; $x<$cnt; $x++)
        {
            if (preg_match($this->url_priorities[$x]["match"], $url))
            {
                return $this->url_priorities[$x]["level"];
            }
        }

        return 0;
    }

    /**
     * Checks whether there are URLs left in the cache or not.
     *
     * @return bool
     */
    public function containsURLs()
    {
        PHPCrawlerBenchmark::start("checking_for_urls_in_cache");

        $sql = "SELECT id FROM urls WHERE (processed = 0 OR in_process = 1) ";
        if (!empty($this->_spidername)) {
            $sql .= " AND spider='" . $this->_spidername . "'";
        }
        $sql .= " LIMIT 1";
        $Result = $this->_handle->query($sql);

        if (empty($Result)) {
            return false;
        }

        $has_columns = $Result->fetch_assoc();

        $Result->free();

        PHPCrawlerBenchmark::stop("checking_for_urls_in_cache");

        if ($has_columns != false)
        {
            return true;
        }
        else return false;
    }

    /**
     * Marks the given URL in the cache as "followed"
     *
     * @param PHPCrawlerURLDescriptor $UrlDescriptor
     */
    public function markUrlAsFollowed(PHPCrawlerURLDescriptor $UrlDescriptor)
    {
        PHPCrawlerBenchmark::start("marking_url_as_followes");
        $hash = md5($UrlDescriptor->url_rebuild);
        $mtime = self::microTime();
        $sql = "UPDATE urls SET processed = 1, in_process = 0, mtime={$mtime} WHERE distinct_hash = '".$hash."'";

        $this->_handle->query($sql);
        PHPCrawlerBenchmark::stop("marking_url_as_followes");
    }

    /**
     * Do cleanups after the cache is not needed anymore
     */
    public function cleanup()
    {
        if (empty($this->_spidername)) return true;

        return true;
        // require table lock
        //$this->_handle->query("lock table urls write");

        $sql = "DELETE FROM urls WHERE spider='" . $this->_spidername . "'";

        echo "$sql" . PHP_EOL;
        $this->_handle->query($sql);

        // release table lock
        //$this->_handle->query("unlock tables");

        return true;
    }

    /**
     * Cleans/purges the URL-cache from inconsistent entries.
     */
    public function purgeCache()
    {
        $sql = "UPDATE urls SET in_process = 0";
        if (!empty($this->_spidername)) {
            $sql .= " AND spider='" . $this->_spidername . "'";
        }
        $this->_handle->query($sql);
    }
}