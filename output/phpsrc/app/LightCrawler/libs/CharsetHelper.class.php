<?php
/**
 * @file CharsetHelper.class.php
 * @author LiangTao (liangtaohy@163.com)
 * @date 2015/05/19 17:14:06
 * @brief 
 *  
 **/

/**
 * Charset Helper Functions
 */

class CharsetHelper
{
    /**
     * GBK To UTF-8
     */
    public static function GbkToUtf8($old)
    {
        if (!isset($old) || empty($old)) {
            echo "content is null\n";
            return "";
        }

        $temp = null;
        $wcharset = preg_match("/<meta.+?charset=[^\w]?([-\w]+)/i",$old,$temp) ? strtolower($temp[1]):"";
        echo "charset: $wcharset\n";
        if (isset($wcharset) && !empty($wcharset) && $wcharset != "utf-8") {
            $old1 = mb_convert_encoding($old, 'UTF-8', 'GBK');
            if (isset($old1) && !empty($old1)) $old = $old1;
        }
        $old = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>' . $old;
        return $old;
    }
};

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */
