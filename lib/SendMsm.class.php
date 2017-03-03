<?php
/**
 * 消息发送类
 */
class SendMsm {
	/**
	 * [sendOneSMSforBox 发送消息方法]
	 * @param  [string]  $rcvnumber [电话号码]
	 * @param  [string]  $content   [短信内容]
	 * @param  [string]  $username  [用户名]
	 * @param  [string]  $devicesn  [设备号]
	 * @param  [integer]  $boxId     [格口号]
	 * @param  [string]  $stationid [站点id]
	 * @param  [integer] $smsfree   [格口是否收费]
	 * @param  [string]  $wxcontent [微信内容]
	 * @param  [string]  $expressno [快递单号]
	 * @param  [string]  $password  [取件码]
	 * @param  integer $mode      [发送模式]
	 * @return [json]             [发送结果]
	 */
	public static function sendOneSMSforBox($rcvnumber, $content, $username, $devicesn=null, $boxId=null,$huohao=null,$stationid=null,$smsfree=1,$wxcontent=null,$expressno=null,$password=null,$mode=1) {
		// 参数检测
		if(!($rcvnumber&&$content&&$username&&$stationid)){
			$arr = array(
				'status'=>1,
				'msg'=>'缺少必要参数',
				);
			return json_encode($arr);
		}

		$db=conn();
		// 获取消息发送账户id
		$res = mysql_query("SELECT * from kmsmsend.account_user where username='$username'",$db);
		$num = mysql_num_rows($res);
		if($num>0){
			$manager = mysql_result($res, 0,'account_id');
			$user_smssend = mysql_result($res, 0, "smssend");
			$user_wxsend = mysql_result($res, 0, "wxsend");
		}else{
			// 如果查找不到就取默认值
			$manager = 1;
			$user_smssend = 1;
			$user_wxsend = 1;
		}
		// 获取站点限制信息
		$res = mysql_query("SELECT * from kmsmsend.account_station where stationaccount='$stationid'",$db);
		$num = mysql_num_rows($res);
		if($num>0){
			$station_smssend = mysql_result($res, 0, "smssend");
			$station_wxsend = mysql_result($res, 0, "wxsend");
		}else{
			$station_smssend = 1;
			$station_wxsend = 1;
		}
		// 如果短信和微信都不发，则中止消息发送程序
		if(($user_smssend==0||$station_smssend==0)&&($user_wxsend==0||$station_wxsend==0)){
			$arr = array(
				'status'=>1,
				'msg'=>'短信微信被限制发送',
				);
			return json_encode($arr);
		}

    	//订单序列号，
		$m_date= date('YmdHis');
		$m_time = mb_substr(microtime(), 2,6); 
		$msm_sn=$m_date.$m_time;

		//发短信
		$sendtime = time();
		$sqlstr = "INSERT INTO  kmsmsend.msmwait ( `username` ,`rcvnumber` ,`sendtime` ,`devicesn` ,`content`,`msm_sn`,`stationid`,`smsfree`,`manager`,`wxcontent`,`expressno`,`boxid`,`huohao`,`password`,`mode` ) 
	                                  VALUES ('$username', '$rcvnumber',  '$sendtime', '$devicesn', '$content', '$msm_sn','$stationid','$smsfree','$manager','$wxcontent','$expressno','$boxId','$huohao','$password','$mode')";
	    $res = mysql_query($sqlstr, $db);
	    // 记录入msmpool表                              
	    $sqlstr1 = "INSERT INTO  kmsmsend.msmpool ( `username` ,`rcvnumber` ,`sendtime` ,`devicesn` ,`content`,`msm_sn`,`stationid`,`smsfree`,`manager`,`wxcontent`,`expressno`,`boxid`,`huohao`,`password`,`mode` ) 
	                                  VALUES ('$username', '$rcvnumber',  '$sendtime', '$devicesn', '$content', '$msm_sn','$stationid','$smsfree','$manager','$wxcontent','$expressno','$boxId','$huohao','$password','$mode')";
	    $res1 = mysql_query($sqlstr1, $db);
		
		if($res==TRUE){
			$arr = array(
				'status'=>0,
				'msg'=>'消息添加成功',
				'msm_sn'=>$msm_sn
				);
		}else{
			$arr = array(
				'status'=>1,
				'msg'=>'消息添加失败'
				);
		}
		return json_encode($arr);
	
	}
}
