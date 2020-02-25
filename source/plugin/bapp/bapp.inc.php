<?php
if (!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

$bapp = $_G['cache']['plugin']['bapp'];

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

if (!$action || $_SERVER['REQUEST_METHOD'] != 'POST') {
    return;
}

$money = $_POST['money'];
if (!$money || $money < 0) {
    echo json_encode(array('code' => 400, 'msg' => '请输入正确的金额'));
    return;
}

function get_client_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function get_sign($appSecret, $orderParam)
{
    $signOriginStr = '';
    ksort($orderParam);
    foreach ($orderParam as $key => $value) {
        if (empty($key) || $key == 'sign') {
            continue;
        }
        $signOriginStr = $signOriginStr . $key . "=" . $value . "&";
    }
    return strtolower(md5($signOriginStr . "app_secret=" . $appSecret));
}

function http_request($url, $method = 'GET', $params = [])
{
    $curl = curl_init();
    if ($method == 'POST') {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        $jsonStr = json_encode($params);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $jsonStr);
    } else if ($method == 'GET') {
        $url = $url . "?" . http_build_query($params, '', '&');
    }
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    $output = curl_exec($curl);
    if (curl_errno($curl) > 0) {
        return [];
    }
    curl_close($curl);
    $json = json_decode($output, true);
    return $json;
}

function add_order($arr)
{
    global $_G, $bapp;
    $data = array(
        'orderid' => $arr['order_id'],
        'status' => 1,
        'uid' => $_G['uid'],
        'amount' => $arr["amount"] / 100 * $bapp['integral_proportion'],
        'price' => $arr["amount"] / 100,
        'submitdate' => time(),
        'ip' => $_SERVER['REMOTE_ADDR'],
    );

    C::t('forum_order')->insert($data);
}

$order_id = date('YmdHis') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);

$reqParam = array(
    'order_id' => $order_id,
    'amount' => (int)($money * 100),
    'body' => 'WP-' . $order_id,
    'notify_url' => trim($_G['siteurl'] . 'source/plugin/bapp/notify.php'),
    'return_url' => trim($_G['siteurl'] . 'home.php?mod=spacecp&ac=credit&op=base'),
    'extra' => '',
    'order_ip' => get_client_ip(),
    'amount_type' => 'CNY',
    'time' => time() * 1000,
    'app_key' => $bapp['appkey']
);

$sign = get_sign($bapp['appsecret'], $reqParam);
$reqParam['sign'] = $sign;

$res = http_request('https://bapi.app/api/v2/pay', 'POST', $reqParam);

if ($res && $res['code'] == 200) {
    add_order($reqParam);
    echo json_encode($res);
} else {
    echo json_encode(array('code' => 500, 'msg' => '网络异常'));
}

