<?php

namespace Applet\Assemble;

class Weixin
{
    private $orderParam;
    private $orderH5Param;
    private $appid;
    private $secret;
    private $mch_id;
    private $mch_key;
    private $notify_url;
    private $trade_type;
    private $key_pem;
    private $cert_pem;
    private $openid;
    private $codedUrl = 'https://api.weixin.qq.com/sns/jscode2session?';
    private $appCodeUrl = "https://api.weixin.qq.com/sns/oauth2/access_token?";
    private $tokenUrl = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=';
    protected $payUrl = 'https://api.mch.weixin.qq.com/pay/unifiedorder'; //支付
    protected $query = 'https://api.mch.weixin.qq.com/pay/orderquery'; //查询
    protected $refundUrl = 'https://api.mch.weixin.qq.com/secapi/pay/refund'; //退款
    protected $sendMsgUrl = 'https://api.weixin.qq.com/cgi-bin/message/subscribe/send?access_token=';
    protected $payOrder;

    public static function init($config)
    {
        if (empty($config['appid'])) {
            throw new \Exception('not empty appid');
        }
        if (empty($config['secret'])) {
            throw new \Exception('not empty secret');
        }
        $class = new self();
        $class->secret = $config['secret'];
        $class->appid = $config['appid'];
        $class->mch_id = isset($config['mch_id']) ? $config['mch_id'] : "";
        $class->mch_key = isset($config['mch_key']) ? $config['mch_key'] : "";
        $class->notify_url = isset($config['notify_url']) ? $config['notify_url'] : "";
        $class->trade_type = isset($config['trade_type']) ? $config['trade_type'] : "JSAPI";
        $class->key_pem = isset($config['key_pem']) ? $config['key_pem'] : "";
        $class->cert_pem = isset($config['cert_pem']) ? $config['cert_pem'] : "";
        return $class;
    }

    public function create_nonce_str($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public function getParam()
    {
        return $this->orderParam;
    }
    public function orderH5Param()
    {
        return $this->orderH5Param;
    }
    public function getNotifyOrder()
    {
        $xml = file_get_contents("php://input");
        return $this->xmlToArray($xml);
    }

    /**
     * 设置订单号 金额 描述
     * @param string $rder_no 平台订单号
     * @param int $money 订单金额
     * @param string $title 描述
     * @param string $openid 描述
     *
     */
    public function set($out_trade_no, $total_fee, $title, $openid = '')
    {
        $order['appid'] = $this->appid;
        $order['mch_id'] = $this->mch_id;
        $order['trade_type'] = $this->trade_type;
        $order['nonce_str'] = $this->create_nonce_str();
        $order['body'] = $title;
        $order['out_trade_no'] = $out_trade_no;
        $order['total_fee'] = $total_fee;
        $order['notify_url'] = $this->notify_url;
        if ($this->trade_type == "JSAPI") {
            $order['openid'] = $openid; //设置用户openid
        }
        $order['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];
        $order['sign'] = $this->sign($order);
        $data = $this->arrayToXml($order);
        $xml_data = $this->curl_post($this->payUrl, $data);
        $prepay_id = $this->xmlToArray($xml_data);
        if ($this->trade_type == "MWEB") {
            $this->orderH5Param = $prepay_id;
        } else {
            $this->orderParam = array(
                'appId' => $this->appid, //小程序ID
                'timeStamp' => '' . time() . '', //时间戳
                'nonceStr' => $this->create_nonce_str(), //随机串
                'package' => 'prepay_id=' . $prepay_id['prepay_id'], //数据包
                'signType' => 'MD5', //签名方式
            );
            $this->orderParam['paySign'] = $this->sign($this->orderParam);
        }
        return $this;
    }
    public function get_client_ip()
    {
        $cip = "unknown";
        if ($_SERVER['REMOTE_ADDR']) {
            $cip = $_SERVER['REMOTE_ADDR'];
        } else if (getenv("REMOTE_ADDR")) {
            $cip = getenv("REMOTE_ADDR");
        }
        return $cip;
    }
    /**
     * 将xml转为array
     */
    public function xmlToArray($xml)
    {
        //将XML转为array
        $array_data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $array_data;
    }

    /**
     * @param array $map
     * @return string
     */
    public function sign(array $map)
    {
        foreach ($map as $k => $v) {
            $Parameters[$k] = $v;
        }
        //签名步骤一：按字典序排序参数
        ksort($Parameters);
        $String = $this->formatBizQueryParaMap($Parameters, false);
        //签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $this->mch_key;
        //签名步骤三：MD5加密
        $String = md5($String);
        //签名步骤四：所有字符转为大写
        $result_ = strtoupper($String);
        return $result_;
    }

    /**
     *    作用：格式化参数，签名过程需要使用
     */
    public function formatBizQueryParaMap($paraMap, $urlencode)
    {
        $buff = "";
        ksort($paraMap);
        foreach ($paraMap as $k => $v) {
            if ($urlencode) {
                $v = urlencode($v);
            }
            //$buff .= strtolower($k) . "=" . $v . "&";
            $buff .= $k . "=" . $v . "&";
        }
        $reqPar = '';
        if (strlen($buff) > 0) {
            $reqPar = substr($buff, 0, strlen($buff) - 1);
        }
        return $reqPar;
    }

    /**
     *    作用：array转xml
     */
    public function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 获取token
     */
    public function getToken()
    {
        $url = $this->tokenUrl . $this->appid . "&secret=" . $this->secret;
        return json_decode($this->curl_get($url), true);
    }

    /**
     * 获取openid
     * @param string $code
     * @return array 成功返回数组 失败为空
     */
    public function getOpenid($code)
    {
        $url = $this->codedUrl . "appid=" . $this->appid . "&secret=" . $this->secret . "&js_code=" . $code . "&grant_type=authorization_code";
        return json_decode($this->curl_get($url), true);
    }
    /**
     * 获取微信app openid
     * @param  [type] $code
     * @return void
     */
    public function getAppOpenid($code)
    {
        $url = $this->appCodeUrl . "appid=" . $this->appid . "&secret=" . $this->secret . "&code=" . $code . "&grant_type=authorization_code";
        return json_decode($this->curl_get($url), true);
    }
    /**
     * 发送模版消息
     * @param  [type] $data
     * @param  [type] $token
     * @return void
     */
    public function sendMsg($data, $token)
    {
        return json_decode($this->curl_post($this->sendMsgUrl . $token, json_encode($data)), true);
    }
    /**
     * 异步回调
     * @param array $order 回调数据
     * @return bool true   验签通过|false 验签不通过
     */
    public function notifyCheck()
    {
        $order = $this->getNotifyOrder();
        $uorder = $order;
        unset($uorder['sign']);
        $sign = $this->sign($uorder); //本地签名
        if ($order['sign'] == $sign) {
            if (isset($order['return_code']) && $order['return_code'] == "SUCCESS" && $order['result_code'] && $order['result_code'] = "SUCCESS") {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * 申请退款
     * @param array $order
     * @param string $out_trade_no 平台订单号
     * @param int $total_fee 订单金额
     * @param string $out_refund_no 自定义退款单号
     * @parma int @refund_fee 退款金额
     */
    public function applyOrderRefund($order)
    {
        $order['appid'] = $this->appid;
        $order['mch_id'] = $this->mch_id;
        $order['nonce_str'] = $this->create_nonce_str();
        $order['sign'] = $this->sign($order);
        $xml_data = $this->arrayToXml($order);
        $data = $this->curl_post_ssl($this->refundUrl, $xml_data);
        return $this->xmlToArray($data);
    }

    /**
     * 订单查询
     * @param string $out_trade_no 订单号
     * @return array 订单信息
     */
    public function findOrder($out_trade_no)
    {
        $order['out_trade_no'] = $out_trade_no;
        $order['appid'] = $this->appid;
        $order['mch_id'] = $this->mch_id;
        $order['nonce_str'] = $this->create_nonce_str();
        $order['sign'] = $this->sign($order);
        $xml_data = $this->arrayToXml($order);
        $data = $this->curl_post_ssl($this->query, $xml_data);
        return $this->xmlToArray($data);
    }

    protected static function curl_get($url)
    {
        $headerArr = array("Content-type:application/x-www-form-urlencoded");
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArr);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        if (!$output) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);
        return $output;
    }

    /**
     * @desc post 用于退款
     */
    protected static function curl_post($url, $data)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml'));
        $output = curl_exec($ch);
        if (!$output) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);
        return $output;
    }

    public function curl_post_ssl($url, $vars, $second = 30, $aHeader = array())
    {

        $ch = curl_init();
        //超时时间
        curl_setopt($ch, CURLOPT_TIMEOUT, $second);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLCERT, $this->cert_pem);
        curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
        curl_setopt($ch, CURLOPT_SSLKEY, $this->key_pem);

        if (count($aHeader) >= 1) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $vars);
        $data = curl_exec($ch);
        if ($data) {
            curl_close($ch);
            return $data;
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            return false;
        }
    }

    /**
     * 解密手机号
     * @param string $session_key 前端传递的session_key
     * @param string $iv 前端传递的iv
     * @param string $encryptedData 前端传递的encryptedData
     */
    public function decryptPhone($session_key, $iv, $encryptedData)
    {
        if (strlen($session_key) != 24) {
            return false;
        }
        $aesKey = base64_decode($session_key);

        if (strlen($iv) != 24) {
            return false;
        }
        $aesIV = base64_decode($iv);
        $aesCipher = base64_decode($encryptedData);
        $result = openssl_decrypt($aesCipher, "AES-128-CBC", $aesKey, 1, $aesIV);
        $dataObj = json_decode($result);
        if ($dataObj == null) {
            return false;
        }
        if ($dataObj->watermark->appid != $this->appid) {
            return false;
        }
        return json_decode($result, true);
    }
}
