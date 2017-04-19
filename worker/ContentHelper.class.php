<?php

/**
 * Content Helper
 * User: liangtaohy@163.com
 * Date: 17/4/1
 * Time: AM11:38
 */
class ContentHelper
{
    public static function GenContentMd5($c)
    {
        $c = mbereg_replace("[ 。，（）；、\n\r\t\0\x0B”<>《》：\-\)\(\^\*%$\#\@\!\`\~\'\"\?\/,\.;:\[\]]+", "", $c);
        return md5($c);
    }
}