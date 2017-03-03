<?php
// 单独测试
include_once("../dataserve/conn_mysql.php");
include_once("./lib/Smshandle.class.php");
include_once("./lib/Dahan.class.php");
include_once("./lib/Zhutong.class.php");
include_once("./lib/SendWx.class.php");
include_once("./lib/mysql.class.php");
include_once("./lib/Log.class.php");

// set_time_limit(60); 
// 定时程序
// 分发消息
$msm  = new SmsHandle();
$msm->handle();
// 开始发送大汉和助通的短信
$db = new mysql();
$config = array(
	'dbhost'=>'rm-m5e3xn7k26i026e75.mysql.rds.aliyuncs.com',
	'dbuser'=>'dy816816',
	'dbpsw'=>'dy51951868+',
	'dbname'=>'kmsmsend',
	'dbcharset'=>'utf8'
	);
$db->connect($config);

$sql = "SELECT `dh_account`,`dh_password`,`sign` from msmaccount group by dh_account";
$dh_query = $db->query($sql);
$dh_accounts = $db->findAll($dh_query);
foreach ($dh_accounts as $k => $v) {
	$account = $v['dh_account'];
	$password = $v['dh_password'];
	$sign = $v['sign'];
	if(!empty($account)){
		$dahan = new Dahan($account,$password,$sign);
		$dahan->sendsms();
		$dahan->getsmsreport();
	}
}

$sql = "SELECT `zt_account`,`zt_password`,`sign` from msmaccount group by zt_account";
$zt_query = $db->query($sql);
$zt_accounts = $db->findAll($zt_query);
foreach ($zt_accounts as $k => $v) {
	$account = $v['zt_account'];
	$password = $v['zt_password'];
	$sign = $v['sign'];
	if(!empty($account)){
		$zt = new Zhutong($account,$password,$sign);
		$zt->sendsms();
		$zt->getsmsreport();
	}
	
}

echo "send_ok";   

?>
