<?php

/**
 * Created by PhpStorm.
 * User: xlegal
 * Date: 17/4/14
 * Time: PM4:30
 */
class XlegalLawContentRecord
{
    public $doc_id = '';
    public $doc_ori_no = ''; // '发布文号(该值唯一，但可以为空)',
    public $type = 0; // '文档类型',
    public $title = ''; // '标题',
    public $tags = ''; // '分类标签',
    public $content = ''; // '正文',
    public $attachment = ''; // '[{doc_id},{doc_name}]',
    public $negs = ''; // '命名实体',
    public $author = ''; // '颁布单位',
    public $index_ori_no = ''; // '原文索引号',
    public $publish_time = 0; // '发布时间',
    public $t_valid = 0; // '生效时间',
    public $t_invalid = 0; // '失效时间',
    public $url = ''; // '原文链接',
    public $url_md5 = ''; // 'url md5',
    public $ctime = 0; // '入库时间',
    public $simhash = ''; // 'simhash值(去重使用)',
    public $status = 0; // '待确认'
}