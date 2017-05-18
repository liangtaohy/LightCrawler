<?php
/**
 * Created by PhpStorm.
 * User: liangtaohy@163.com
 * Date: 17/2/8
 * Time: PM12:42
 */

class Formatter
{
    public static function formaturl($url, $str){
        if (!isset($url) || empty($url)) return $str;

        if (is_array($str)) {
            $return = array();
            foreach ($str as $href) {
                $return[] = self::formaturl($url, $href);
            }
            return $return;
        } else {
            if (stripos($str, 'http://')===0 || stripos($str, 'ftp://')===0) {
                return $str;
            }
            $str = str_replace('\\', '/', $str);
            $parseUrl = parse_url(dirname($url) . '/');
            $scheme = isset($parseUrl['scheme']) ? $parseUrl['scheme'] : 'http';
            if (!isset($parseUrl['host']) || empty($parseUrl['host'])) {
                //echo "formaturl fail\n";
                if (preg_match('/^http:\/\/([a-zA-Z0-9\.]+)/sim', $url, $matches) === 1) {
                    $parseUrl['host'] = $matches[1];
                } else {
                    return null;
                }
            }
            $host = $parseUrl['host'];
            $path = isset($parseUrl['path']) ? $parseUrl['path'] : '';
            $port = isset($parseUrl['port']) ? $parseUrl['port'] : '';

            if (strpos($str, '/')===0) {
                return $scheme.'://'.$host.$str;
            } else {
                $part = explode('/', $path);
                array_shift($part);
                $count = substr_count($str, '../');
                if ($count>0) {
                    for ($i=0; $i<$count; $i++) {
                        $p = array_pop($part);
                        if (empty($p)) {
                            array_pop($part);
                        }
                    }
                }
                $path = implode('/', $part);

                $str = str_replace(array('../','./'), '', $str);
                $path = $path=='' ? '/' : '/'.trim($path,'/').'/';
                return $scheme.'://'.$host.$path.$str;
            }
        }
    }
}

