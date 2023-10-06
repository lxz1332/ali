<?php

namespace cwkj\ali;

use cwkj\ali\Base;
use cwkj\ali\Validates as Validates;
use think\facade\Db;
use cwkj\money\Money;
use cwkj\order\Order;

class AliPay extends Base {

    const method = 'alipay.trade.page.pay';
    const method_h5 = 'alipay.trade.wap.pay';
    const new_paygateway = 'https://openapi.alipay.com/gateway.do';

    //订单支付
    public function Pay($post) {
        if (array_key_exists('paystyle', $post)) {
            $paystyle = $post['paystyle'];
        } else {
            $paystyle = 1;
        }
        //公共参数
        $pub_params = [
            'app_id' => $this->appid,
            'format' => 'JSON', //目前仅支持json
            'return_url' => $post['reurl'], //同步返回地址
            'charset' => 'utf-8',
            'sign_type' => 'RSA2', //签名方式
            'sign' => '', //签名
            'timestamp' => date('Y-m-d H:m:s'), //发送时间 格式0000-00-00 00:00:00
            'version' => '1.0', //固定为1.0
            'notify_url' => $_SERVER["REQUEST_SCHEME"] . '://' . $_SERVER["SERVER_NAME"] . '/index/pay/alipay_return', //异步通知地址
            'biz_content' => '', //业务请求参数的集合
        ];

        //判断支付端口
        if ($post['style'] == 1) {
            $pub_params['method'] = self::method;
        } elseif ($post['style'] == 2) {
            $pub_params['method'] = self::method_h5;
        } elseif ($post['style'] == 3) {
            $pub_params['method'] = self::method;
        } else {
            return ['code' => 0, 'msg' => '未定义支付'];
        }

        //业务参数
        $api_params = [
            'out_trade_no' => $post['out_trade_no'], //商户订单号
            'product_code' => 'FAST_INSTANT_TRADE_PAY', //销售产品码 固定值
            'total_amount' => floatval($post['total_amount']), //总价 单位为元
            'subject' => '支付订单：' . $post['out_trade_no'], //订单标题
            'qr_pay_mode' => 2
        ];
//        $api_params['passback_params'] = 'style=' . $paystyle . '&jifen=' . $post['use_jifen'];
        $pub_params['biz_content'] = json_encode($api_params); //json_unescaped_unicode
        $pub_params = $this->setrsa2sign($pub_params);
        $url = self::new_paygateway . '?' . $this->geturl($pub_params);
        return ['code' => 1, 'msg' => 'ok', 'data' => ['url' => $url, 'sn' => $post['out_trade_no']]];
    }

    //在线充值
    public function Paychongzhi($post) {
        $validate = new Validates();
        if (!$validate->scene('add')->check($post)) {
            return ['code' => 0, 'msg' => $validate->getError()];
        }
        //创建充值记录
        $out_trade_no = date("ymdHis") . $post['user_id'] . rand(10000000, 99999999);
        $data = [
            'sn' => $out_trade_no,
            'user_id' => $post['user_id'],
            'money' => $post['total_amount'],
            'pay_cate' => 2,
            'time_add' => time()
        ];
        if (!Db::name('chongzhi')->insert($data)) {
            return ['code' => 0, 'msg' => '系统繁忙稍后再试'];
        }
        //公共参数
        $pub_params = [
            'app_id' => $this->appid,
            'format' => 'JSON', //目前仅支持json
            'return_url' => $post['reurl'], //同步返回地址
            'charset' => 'UTF-8',
            'sign_type' => 'RSA2', //签名方式
            'sign' => '', //签名
            'timestamp' => date('Y-m-d H:m:s'), //发送时间 格式0000-00-00 00:00:00
            'version' => '1.0', //固定为1.0
            'notify_url' => $_SERVER["REQUEST_SCHEME"] . '://' . $_SERVER["SERVER_NAME"] . '/api/alipay', //异步通知地址
            'biz_content' => '', //业务请求参数的集合
        ];
        //判断支付端口
        if ($post['style'] == 1) {
            $pub_params['method'] = $this->method;
        } elseif ($post['style'] == 2) {
            $pub_params['method'] = $this->method_h5;
        } elseif ($post['style'] == 3) {
            $pub_params['method'] = $this->method;
        } else {
            return ['code' => 0, 'msg' => '未定义APP支付'];
        }
        //业务参数
        $api_params = [
            'out_trade_no' => $out_trade_no, //商户订单号
            'product_code' => 'FAST_INSTANT_TRADE_PAY', //销售产品码 固定值
            'total_amount' => $post['total_amount'], //总价 单位为元
            'subject' => '商品支付订单：' . $out_trade_no, //订单标题
        ];
        $passback_params = array('style' => 2);
        if (array_key_exists('passback_params', $post)) {
            if ($post['passback_params']) {
                $passback_params = array_merge(array('style' => 2), $post['passback_params']);
            }
        }
        $api_params['passback_params'] = http_build_query($passback_params);
        $pub_params['biz_content'] = json_encode($api_params); //json_unescaped_unicode
        $pub_params = $this->setrsa2sign($pub_params);
        $url = $this->new_paygateway . '?' . $this->geturl($pub_params);
        if ($post['style'] < 3) {
            return ['code' => 1, 'msg' => 'ok', 'data' => ['url' => $url]];
        }
        return ['code' => 1, 'msg' => 'ok', 'data' => $pub_params];
    }

    //订单返回
    public function pay_return_order($postData) {
        $map[] = ['order_out_trade_no', '=', $postData['out_trade_no']];
        $map[] = ['order_status', '=', 1];
        $pay = Db::name("goods_order")->where($map)->field('order_id,order_sn,user_id,order_out_trade_no,order_status')->find();
        if ($pay) {
            $Order = new Order();
            $Order->pay_success($pay['order_id'], $postData['total_amount'], 3, 0, $postData['trade_no']);
            $passback_params = json_decode(urldecode($postData['passback_params']), true);
//            trace($passback_params, 'error');
            if (array_key_exists('jifen', $passback_params) && $passback_params['jifen'] > 0) {
                $Money = new Money();
                if (!$Money->add($pay['user_id'], 2, '-' . $passback_params['jifen'], '订单' . $pay['order_sn'], 10)) {
                    trace($pay['order_id'] . '积分扣除失败,应扣除:' . $passback_params['jifen'], 'error');
                }
                //更新订单使用积分
                if (!Db::name("goods_order")->where('order_id', $pay['order_id'])->update(array('order_jifen_user' => $passback_params['jifen']))) {
                    trace($pay['order_id'] . '订单更新使用积分数量失败', 'error');
                }
            }
            return 'success';
        }
    }

    //在线充值返回
    public function pay_return_chongzhi($postData) {
        $map[] = ['sn', '=', $postData['out_trade_no']];
        $map[] = ['status', '=', 0];
        $pay = Db::name("chongzhi")->where($map)->find();
        if ($pay) {
            if (Db::name("chongzhi")->where($map)->update(['status' => 1, 'transaction_id' => $postData['trade_no']])) {
                $Money = new Money();
                if (!$Money->add($pay['user_id'], 1, $pay['money'], '支付宝充值' . $pay['sn'], 4)) {
                    trace($pay['sn'] . '充值失败', 'error');
                }
                return 'success';
            }
        }
    }

}
