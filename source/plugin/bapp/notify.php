<?php

require '../../class/class_core.php';
require '../../function/function_forum.php';
$discuz = C::app();
$discuz->init();
loadcache('plugin');

$bapp = $_G['cache']['plugin']['bapp'];

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

$jsonStr = file_get_contents('php://input');
$notifyData = (array)json_decode($jsonStr);
$calcSign = get_sign($bapp['appsecret'], $notifyData);
if ($calcSign != $notifyData['sign']) {
    echo 'SIGN ERROR';
    die();
}

$orderid = $notifyData['order_id'];

$order = DB::fetch_first("select * from " . DB::table('forum_order') . " where orderid='" . $orderid . "' and status=1");
if (!$order) {
    echo 'SUCCESS';
    die();
}

$data = array('status' => 2, 'confirmdate' => time());
$where = array('orderid' => $orderid);
DB::update('forum_order', $data, $where);

updatemembercount($order['uid'], array($_G['setting']['creditstrans'] => $order['amount']), true, '', 1, '', '积分充值');

notification_add($order['uid'], 'system', 'addfunds', array(
    'orderid' => $order['orderid'],
    'price' => $order['price'],
    'from_id' => 0,
    'from_idtype' => 'buycredit',
    'value' => $_G['setting']['extcredits'][$_G['setting']['creditstrans']]['title'] . ' ' . $order['amount'] . ' ' . $_G['setting']['extcredits'][$_G['setting']['creditstrans']]['unit'],
), 1);

echo 'SUCCESS';


