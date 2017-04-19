<?php
/***************************************************************************
 *
 * Copyright (c) 2015 Baidu.com, Inc. All Rights Reserved
 *
 **************************************************************************/



/**
 * @file CrawlerConst.class.php
 * @author liangtao01(sumeru-engine@baidu.com)
 * @date 2015/05/12 20:48:51
 * @brief
 *
 **/

// Follow Mode
define('FOLLOW_MODE_ANY', 0);         ///< follow any links found
define('FOLLOW_MODE_DOMAIN', 1);          ///< follow the same domain links found
define('FOLLOW_MODE_HOST', 2);        ///< follow the same host links found
define('FOLLOW_MODE_PATH', 3);        ///< follow the same path links found

// Url Cache Type
define('URL_CACHE_IN_MEMORY', 1);         ///< url in-memory-cache
define('URL_CACHE_IN_SQLITE', 2);         ///< url sqllite-database-cache
define('URL_CACHE_IN_MYSQL', 3); ///< url in mysql cache

// User Agent Type
define('UA_DEFAULT', "default");
define('UA_ANDROID', "android");
define('UA_IPHONE', "iphone");
/* vim: set expandtab ts=4 sw=4 sts=4 tw=100: */