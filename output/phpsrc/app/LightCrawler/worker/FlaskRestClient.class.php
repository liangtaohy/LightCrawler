<?php

/**
 * Flask REST Client
 * User: liangtaohy@163.com
 * Date: 17/4/1
 * Time: PM12:16
 */
class FlaskRestClient
{
    const TAG = __CLASS__;

    /**
     * @var null
     */
    private static $_inst = null;

    /**
     * @return FlaskRestClient|null
     */
    public static function GetInstance()
    {
        if (empty(self::$_inst)) {
            self::$_inst = new self();
        }

        return self::$_inst;
    }

    /**
     * FlaskRestClient constructor.
     */
    private function __construct()
    {
        //
    }

    /**
     * @return string
     */
    public static function GetFlaskRestHost()
    {
        return "http://127.0.0.1:5000";
    }

    /**
     * @param $c
     * @return bool
     */
    public static function simHash($c)
    {
        return self::call('post', "/simhash/generate", array('document' => $c), false);
    }

    /**
     * @param $method
     * @param $api
     * @param $params
     * @param bool $need_sign
     * @return bool
     */
    protected static function call($method, $api, $params, $need_sign = true)
    {
        $now = Utils::microTime();

        $host = self::GetFlaskRestHost();
        MeLog::notice(sprintf('upstream[%s] host[%s] api[%s] params[%s] start[%d]'
            ,self::TAG
            ,$host
            ,$api
            ,serialize($params)
            ,$now
        ));

        $ret = self::request($method, $api, $params, $need_sign);
        if ($ret['code'] != 0) {
            $end = Utils::microTime();
            MeLog::notice(sprintf('upstream[%s] host[%s] api[%s] params[%s] end[%d] cost[%d] errmsg[couponput failed] response[%s]'
                ,self::TAG
                ,$host
                ,$api
                ,serialize($params)
                ,$end
                ,$end - $now
                ,serialize($ret)
            ), $ret['code']);
            return false;
        }

        if (!isset($ret['content']) || empty($ret['content'])) {
            $end = Utils::microTime();
            MeLog::notice(sprintf('upstream[%s] host[%s] api[%s] params[%s] end[%d] cost[%d] errmsg[no content] response[%s]'
                ,self::TAG
                ,$host
                ,$api
                ,serialize($params)
                ,$end
                ,$end - $now
                ,serialize($ret)
            ), XDPAPI_EC_DATA_OBJECT_NOT_FOUND);
            return false;
        }

        $end = Utils::microTime();
        MeLog::notice(sprintf('upstream[%s] host[%s] api[%s] params[%s] end[%d] cost[%d] response[%s]'
            ,self::TAG
            ,$host
            ,$api
            ,serialize($params)
            ,$end
            ,$end - $now
            ,serialize($ret['content'])
        ));

        return $ret['content'];
    }

    /**
     * http 请求
     * @param $method
     * @param $api
     * @param $params
     * @param bool $need_sign
     * @return bdHttpResponse|mixed
     */
    private static function request($method, $api, $params, $need_sign = true)
    {
        $api = self::GetFlaskRestHost() . $api;

        $ret = array();
        $res = array();
        try{
            do {
                switch ($method) {
                    case 'post':
                        $res = bdHttpRequest::post($api, $params);
                        $ret = json_decode($res->getBody(), true);
                        break;
                    case 'get':
                        $res = bdHttpRequest::get($api, $params);
                        $ret = json_decode($res->getBody(), true);
                        break;
                }
            } while (0);
        } catch (Exception $e) {
            MeLog::warning(sprintf('request api[%s] errno[%d] errmsg[%s] response[%s]', $api, $e->getCode(), $e->getMessage(), json_encode($res)));
            return false;
        }
        MeLog::debug(json_encode($res));
        return $ret;
    }
}