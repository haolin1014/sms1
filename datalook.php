<?php
include_once("../dataserve/conn_mysql.php");
header("Content-Type:text/html;charset=utf-8");
$db = conn7();
// 等待发送短信量
$sql = "SELECT count(*) as count from msmwait";
$res = mysql_query($sql,$db);
$num = mysql_result($res,0, 'count');
echo "<p>等待处理：".$num."</p>";
// 正在发送短信量
$sql = "SELECT count(*) as count from msmforsend";
$res = mysql_query($sql,$db);
$num = mysql_result($res,0, 'count');
echo "<p>等待发送：".$num."</p>";
// 等待状态短息量
$sql = "SELECT count(*) as count from msmpool";
$res = mysql_query($sql,$db);
$num = mysql_result($res,0, 'count');
echo "<p>等待状态：".$num."</p>";

echo "<hr>";
// 今日发送消息数
$today = strtotime(date("Y-m-d"));

$sql = "SELECT count(*) as count from deeyee.ksendmsm where sendtime > '$today' ";
$res = mysql_query($sql,$db);
$num = mysql_result($res,0, 'count');
echo "<p>今日发送消息数：".$num."</p>";
// 消息成功数
$sql = "SELECT count(*) as count from deeyee.ksendmsm where sendtime > '$today' and ( status=2 or wxstatus=2)";
$res = mysql_query($sql,$db);
$num = mysql_result($res,0, 'count');
echo "<p>消息成功数：".$num."</p>";
// 消息失败数
$sql = "SELECT count(*) as count from deeyee.ksendmsm where sendtime > '$today' and status=3 and wxstatus=3";
$res = mysql_query($sql,$db);
$num1 = mysql_result($res,0, 'count');
$sql = "SELECT count(*) as count from deeyee.ksendmsm where sendtime > '$today' and wxsendflg=0 and smssendflg=1 and status=3 ";
$res = mysql_query($sql,$db);
$num2 = mysql_result($res,0, 'count');
$sql = "SELECT count(*) as count from deeyee.ksendmsm where sendtime > '$today' and wxsendflg=1 and smssendflg=0 and wxstatus=3 ";
$res = mysql_query($sql,$db);
$num3 = mysql_result($res,0, 'count');
$num = $num1+$num2+$num3;
echo "<p>消息失败数：".$num."</p>";
// 短信失败数
$sql = "SELECT count(*) as count from deeyee.ksendmsm where sendtime > '$today' and status=3";
$res = mysql_query($sql,$db);
$num = mysql_result($res,0, 'count');
echo "<p>短信状态失败：".$num."</p>";
?>