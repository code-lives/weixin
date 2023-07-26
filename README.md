<p align="center">
<a href="https://packagist.org/packages/code-lives/weixin" target="_blank"><img src="https://img.shields.io/packagist/v/code-lives/weixin?include_prereleases" alt="GitHub forks"></a>
<a href="https://packagist.org/packages/code-lives/weixin" target="_blank"><img src="https://img.shields.io/github/stars/code-lives/weixin?style=social" alt="GitHub forks"></a>
<a href="https://github.com/code-lives/weixin/fork" target="_blank"><img src="https://img.shields.io/github/forks/code-lives/weixin?style=social" alt="GitHub forks"></a>

</p>

|                                      第三方                                      | token | openid | 支付 | 回调 | 退款 | 订单查询 | 解密手机号 | 分账 | 模版消息 |
| :------------------------------------------------------------------------------: | :---: | :----: | :--: | :--: | :--: | :------: | :--------: | :--: | :------: |
| [微信小程序](https://pay.weixin.qq.com/wiki/doc/api/wxa/wxa_api.php?chapter=9_1) |   ✓   |   ✓    |  ✓   |  ✓   |  ✓   |    ✓     |     ✓      |  x   |    ✓     |
|                                     微信 h5                                      |   x   |   x    |  ✓   |  ✓   |  ✓   |    ✓     |     x      |  x   |    x     |
|                                     微信 APP                                     |   x   |   ✓    |  ✓   |  ✓   |  ✓   |    ✓     |     x      |  x   |    x     |
|                                    微信公众号                                    |   x   |   x    |  ✓   |  ✓   |  ✓   |    ✓     |     x      |  x   |    ✓     |

## 安装

```php
composer require code-lives/weixin
```

### ⚠️ 注意

> 金额单位分 100=1 元

> 返回结果 array 由开发者自行判断

# 预下单

```php

//引入命名空间
use Applet\Assemble\Weixin;

// 微信小程序
$pay= Weixin::init($config)->set("订单号","金额","描述","描述")->getParam();

// 微信公众号【appid 和secret 换成公众号的】
$pay= Weixin::init($config)->set("订单号","金额","描述","openid")->getParam();

// 微信H5【appid 和secret 换成公众号的】
$pay= Weixin::init($config)->set("订单号","金额","描述")->getH5Param();

// 微信APP (没有openid)
$pay= Weixin::init($config)->set("订单号","金额","描述")->getParam();

```

### Config

| 参数名字   | 类型   | 必须 | 说明                                                     |
| ---------- | ------ | ---- | -------------------------------------------------------- |
| appid      | int    | 是   | 小程序 appid                                             |
| secret     | int    | 是   | 小程序 secret                                            |
| mch_id     | string | 是   | 商户 mch_id                                              |
| mch_key    | string | 是   | 商户 mch_key                                             |
| notify_url | string | 是   | 异步地址                                                 |
| cert_pem   | string | 是   | cert_pem 证书                                            |
| key_pem    | string | 是   | key_pem 证书                                             |
| trade_type | string | 是   | 默认为：JSAPI。MWEB：代表微信 H5 、JSAPI：公众号或小程序 |

### Token

```php
$data= Weixin::init($config)->getToken();
```

| 返回参数     | 类型   | 必须 | 说明                   |
| ------------ | ------ | ---- | ---------------------- |
| expires_in   | string | 是   | 凭证有效时间，单位：秒 |
| access_token | string | 是   | 获取到的凭证           |

### Openid

```php
$code="";
$data= Weixin::init($config)->getOpenid($code);
```

| 返回参数    | 类型   | 必须 | 说明        |
| ----------- | ------ | ---- | ----------- |
| session_key | string | 是   | session_key |
| openid      | string | 是   | 用户 openid |
| unionid     | string | 是   | unionid     |

### 解密手机号

```php
$data= Weixin::init($config)->decryptPhone($session_key, $iv, $encryptedData);
echo $phone['phoneNumber'];
```

### 订单查询

```php
$data = Weixin::init($config)->findOrder("订单号");
```

### 退款

| 参数名字      | 类型   | 必须 | 说明         |
| ------------- | ------ | ---- | ------------ |
| out_trade_no  | string | 是   | 平台订单号   |
| out_refund_no | string | 是   | 自定义订单号 |
| refund_fee    | int    | 是   | 退款金额     |
| total_fee     | int    | 是   | 订单金额     |
| refund_desc   | string | 是   | 退款原因     |

```php
$order = [
        'out_trade_no' => '123',
        'total_fee' => 0.01,
        'out_refund_no' => time(),
        'refund_fee' => 0.01,
    ];
$data= Weixin::init($config)->applyOrderRefund($order);
```

### 模版消息

```php
$data = [
    "touser" => "",
    "template_id" => "",
    "page" => "pages/index/index",
    "miniprogram_state" => "developer",
    "lang" => "zh_CN",
    "data" => [
        'thing6' => ['value' => "第一个参数{{thing6.DATA}}"],
        'thing7' => ['value' => "第二个参数{{thing7.DATA}}"],
        'time8' =>  ['value' => "第三个参数{{time8.DATA}}"],
],
$data= Weixin::init($config)->sendMsg($data,$token);
$data=[
    "errcode" => 0
    "errmsg" => "ok"
    "msgid" => 123456
]
```

## 支付回调

```php
$pay = Weixin::init($config);
$status = $pay->notifyCheck();//验证
if($status){
    $order = $pay->getNotifyOrder();//订单数据
    //$order['out_trade_no']//平台订单号
    //$order['transaction_id']//微信订单号
    echo 'success';exit;
}
```
