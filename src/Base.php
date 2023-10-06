<?php

namespace cwkj\ali;

use think\facade\Cache;

class Base {

    public function __construct() {
        $this->reurl = 'http://d01.com/alipay';
        $this->nourl = 'http://d01.com/alipay';
        $this->pid = config('set_alipay.pid');
        $this->appid = config('set_alipay.appid');
        $this->appprikey = config('set_alipay.appprikey');
        $this->new_alipubke = config('set_alipay.alipubkey');
        $this->new_paygateway = 'https://openapi.alipay.com/gateway.do';
        $this->method = 'alipay.trade.page.pay';
        $this->method_h5 = 'alipay.trade.wap.pay';
    }

    public function getStr($arr, $type = 'RSA') {
        //筛选
        if (isset($arr['sign'])) {
            unset($arr['sign']);
        }
        if (isset($arr['sign_type']) && $type == 'RSA') {
            unset($arr['sign_type']);
        }
        //排序
        ksort($arr);
        //拼接
        return $this->getUrl($arr, false);
    }

    //将数组转换为url格式的字符串
    public function getUrl($arr, $encode = true) {
        if ($encode) {
            return http_build_query($arr);
        } else {
            return urldecode(http_build_query($arr));
        }
    }

    //获取签名MD5
    public function getSign($arr) {
        return md5($this->getStr($arr) . $this->key);
    }

    //获取含有签名的数组MD5
    public function setSign($arr) {
        $arr['sign'] = $this->getSign($arr);
        return $arr;
    }

    //获取签名RSA
    public function getRsaSign($arr) {
        return $this->rsaSign($this->getStr($arr), $this->appprikey);
    }

    //获取含有签名的数组RSA
    public function setRsaSign($arr) {
        $arr['sign'] = $this->getRsaSign($arr);
        return $arr;
    }

    //获取签名RSA2
    public function getRsa2Sign($arr) {
        return $this->rsaSign($this->getStr($arr, 'RSA2'), $this->appprikey, 'RSA2');
    }

    //获取含有签名的数组RSA
    public function setRsa2Sign($arr) {
        $arr['sign'] = $this->getRsa2Sign($arr);
        return $arr;
    }

    //2.验证签名
    public function checkSign($arr) {
        $sign = $this->getSign($arr);
        if ($sign == $arr['sign']) {
            return true;
        } else {
            return false;
        }
    }

    //验证是否来之支付宝的通知
    public function isAlipay($arr) {
        $url = 'https://mapi.alipay.com/gateway.do?service=notify_verify&partner=' . $this->pid . '&notify_id=';
        $str = file_get_contents($url . $arr['notify_id']);
        if ($str == 'true') {
            return true;
        } else {
            return false;
        }
    }

    // 4.验证交易状态
    public function checkOrderStatus($arr) {
        if ($arr['trade_status'] == 'TRADE_SUCCESS' || $arr['trade_status'] == 'TRADE_FINISHED') {
            return true;
        } else {
            return false;
        }
    }

    public function checkalipay($postData) {
        if ($postData['app_id'] != $this->appid) {
            return FALSE;
        } else {
            if ($postData['sign_type'] == 'RSA2') {
                if (!$this->rsaCheck($this->getStr($postData), $this->new_alipubke, $postData['sign'], 'RSA2')) {
                    return FALSE;
                } else {
                    // 4.验证交易状态
                    if (!$this->checkOrderStatus($postData)) {
                        return FALSE;
                    } else {
                        return TRUE;
                    }
                }
            } else {
                return FALSE;
            }
        }
    }

//    public function checkalipay($postData) {
//        if ($postData['sign_type'] == 'RSA2') {
//            if (!$this->rsaCheck($this->getStr($postData), $this->new_alipubke, $postData['sign'], 'RSA2')) {
//                return FALSE;
//            } else {
//                //验证是否来自支付宝的请求
//                if (!$this->isAlipay($postData)) {
//                    return FALSE;
//                } else {
//                    // 4.验证交易状态
//                    if (!$this->checkOrderStatus($postData)) {
//                        return FALSE;
//                    } else {
//                        return TRUE;
//                    }
//                }
//            }
//        } else {
//            return FALSE;
//        }
//    }

    /**
     * RSA签名
     * @param $data 待签名数据
     * @param $private_key 私钥字符串
     * return 签名结果
     */
    function rsaSign($data, $private_key, $type = 'RSA') {

        $search = [
            "-----BEGIN RSA PRIVATE KEY-----",
            "-----END RSA PRIVATE KEY-----",
            "\n",
            "\r",
            "\r\n"
        ];

        $private_key = str_replace($search, "", $private_key);
        $private_key = $search[0] . PHP_EOL . wordwrap($private_key, 64, "\n", true) . PHP_EOL . $search[1];
        $res = openssl_get_privatekey($private_key);

        if ($res) {
            if ($type == 'RSA') {
                openssl_sign($data, $sign, $res);
            } elseif ($type == 'RSA2') {
                //OPENSSL_ALGO_SHA256
                openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
            }
            openssl_free_key($res);
        } else {
            exit("私钥格式有误");
        }
        $sign = base64_encode($sign);
        return $sign;
    }

    /**
     * RSA验签
     * @param $data 待签名数据
     * @param $public_key 公钥字符串
     * @param $sign 要校对的的签名结果
     * return 验证结果
     */
    function rsaCheck($data, $public_key, $sign, $type = 'RSA') {
        $search = [
            "-----BEGIN PUBLIC KEY-----",
            "-----END PUBLIC KEY-----",
            "\n",
            "\r",
            "\r\n"
        ];
        $public_key = str_replace($search, "", $public_key);
        $public_key = $search[0] . PHP_EOL . wordwrap($public_key, 64, "\n", true) . PHP_EOL . $search[1];
        $res = openssl_get_publickey($public_key);
        if ($res) {
            if ($type == 'RSA') {
                $result = (bool) openssl_verify($data, base64_decode($sign), $res);
            } elseif ($type == 'RSA2') {
                $result = (bool) openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256);
            }
            openssl_free_key($res);
        } else {
            exit("公钥格式有误!");
        }
        return $result;
    }

}
