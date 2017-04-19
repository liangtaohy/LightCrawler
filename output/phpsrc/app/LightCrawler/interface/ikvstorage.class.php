<?php
/***************************************************************************
 * 
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 * 
 **************************************************************************/
 
 
 
/**
 * @file interface/ikvstorage.itf.php
 * @author liangtao01(sumeru-engine@baidu.com)
 * @date 2015/05/17 22:26:07
 * @brief 
 *  
 **/

/**
 * K/V db interface
 */
interface ikvstorage
{
    public function type();
    public function get($key);
    public function set($key, $value);
    public function del($key);
};

/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */
?>
