<?php

namespace cwkj\ali;

use think\facade\Cache;

class Base {

    public function __construct() {
        $this->token = config('set_wechat.token');
        $this->appid = config('set_wechat.appid');
        $this->appsecret = config('set_wechat.appsecret');
        $this->wx_mchid = config('set_wechat.mchid');
        $this->wx_key = config('set_wechat.key');
    }

   
    
    public function a($) {
       return 1;
    }

}
