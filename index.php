<?php
include_once("../dataserve/conn_mysql.php");
include "./lib/SendMsm.class.php";
// 发送普通短信
$rcvnumber='18671530061';
// $rcvnumber='13761633599';
// $username='18671530062';
$username='13797808338';

// 普通短信要是一个可以发送短信的站点和用户账号，微信要做限制！
$stationid='weixin1234567890';

$identify=rand(1000,9999);

$content="验证码：".$identify." 递易（上海）智能科技有限公司";
$res = SendMsm::sendOneSMSforBox($rcvnumber, $content, $username, '', '','',$stationid);
echo $res;
