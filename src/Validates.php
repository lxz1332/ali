<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace cwkj\ali;

use think\Validate;

/**
 * Description of Validate
 *
 * @author dr
 */
class Validates extends Validate {

    protected $regex = ['money' => '(^[1-9](\d+)?(\.\d{1,2})?$)|(^0$)|(^\d\.\d{1,2}$)'];
    protected $rule = [
        'total_amount' => 'require|regex:money|max:6', //金额
        'sn' => 'require', //订单号
        'user_id|用户id' => 'require',
        'reurl|跳转地址' => 'require',
        'style|支付端口' => 'require|in:1,2,3', //1:pc网页/2:wap网页/3:APP小程序等
    ];
    protected $message = [
        'total_amount.require' => '缺少参数money',
        'total_amount.regex' => 'money非法',
        'total_amount.max' => 'money最大999999',
        'sn.require' => '缺少参数sn',
    ];
    protected $scene = [
        'add' => ['total_amount', 'reurl', 'user_id', 'style'],
        'find' => ['sn'],
    ];

}
