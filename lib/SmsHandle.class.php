<?php
class SmsHandle {
	public function handle() {
		$db1 = conn7();
		//获取短信，每次最多200条
		mysql_query("UPDATE `msmwait` SET `flg` = '2' order by sendtime asc limit 200", $db1);
		$result = mysql_query("SELECT username,rcvnumber,sendtime,huohao,stationid,frequency,status,content,msm_sn,id,manager,smsfree,devicesn,boxid,password,expressno,mode,wxcontent FROM msmwait  where  `flg` = '2'  order by sendtime asc limit 200", $db1);
		$num = mysql_num_rows($result);
		if ($num == 0) {
			return; //没有短信时返回
			
		}
		//对于短信的最大限制，防止出现异常，无限制发送
		$this->checkMaxNum($num,$db1);
		//获取设置数据1
		$yys = $this->getSetConfig1($db1);
		extract($yys);
		//获取设置数据2
		$rate = $this->getSetConfig2($num,$db1);
		//----开始分析--------------
		for ($i = 0;$i < $num;$i++) {
			$username = mysql_result($result, $i, "username");
			$rcvnumber = mysql_result($result, $i, "rcvnumber");
			$sendtime = mysql_result($result, $i, "sendtime");
			$huohao = mysql_result($result, $i, "huohao");
			$stationid = mysql_result($result, $i, "stationid");
			$frequency = mysql_result($result, $i, "frequency");
			$status = mysql_result($result, $i, "status");
			$content = mysql_result($result, $i, "content");
			$msm_sn = mysql_result($result, $i, "msm_sn");
			$id = mysql_result($result, $i, "id");
			$manager = mysql_result($result, $i, "manager");
			$smsfree = mysql_result($result, $i, "smsfree");
			$devicesn = mysql_result($result, $i, "devicesn");
			$boxId = mysql_result($result, $i, "boxid");
			$password = mysql_result($result, $i, "password");
			$expressno = mysql_result($result, $i, "expressno");
			$mode = mysql_result($result, $i, "mode");
			$wxcontent = mysql_result($result, $i, "wxcontent");
			$m_operator = $this->checknumber($rcvnumber, $yidong, $liantong, $dianxin, $db1); //运营商判别  1移动  2联通  3电信
			$keyword = $this->checkkeyword($content, $m_operator, $yidongkeyword, $liantongkeyword, $dianxinkeyword, $db1); //关键字判别
		
			$companyflg = $this->getcompanyflg($i, $rate);
			// 根据选择的短信服务商查询该短信服务商的账号
			$account = $this->getSpaaccount($companyflg,$manager,$db1);

			if (($keyword == 0) && ($m_operator != 4)) //如果是运营商之外的号码，或者短信中有关键字，将直接发送失败
			{
				$status = 0;
			} else {
				$status = 3;
			}

			// 站点和账户限制
			$station_limit = $this->station_limit($stationid,$db1);
			$user_limit = $this->user_limit($username,$db1);

			// 取货码限制判断
			$content = $this->passwordHide($user_limit['smslimit'],$station_limit['smslimit'],$content,$password,$huohao,$rcvnumber,$db1);

			// whl如何为自运营模式才进行扣费，合作模式是合作者自行扣费（使用continue防止卡死）
			if ($mode == 1) {
				// 请求微信端判断是否要发送微信和短信
				if($station_limit['wxsend']==1 && $user_limit['wxsend']==1 && $account['wx_switch_url']){
					// $wxres = SendWx::getweixinswitchsms($rcvnumber,$account['wx_switch_url'],$db);
					$wxres = $this->limitPhone($rcvnumber,$manager,$db1);
				}else{
					$wxres = 0;
				}
				
				// 进行收费
				$chargeRes = $this->charge($devicesn,$username,$smsfree,$wxres,$boxId,$station_limit,$user_limit,$content);

				if($chargeRes==false){//如果收费失败跳出该条
					continue;
				}
				// 微信入库
				if ($wxres != 0 && $station_limit['wxsend']==1 && $user_limit['wxsend']==1) {
					SendWx::sendmessagetoweixin($devicesn, $msm_sn, $rcvnumber, $huohao, $password, $boxId, $expressno, "您可点开此条消息，用二维码取件，或转发好友代取", $wxcontent, "0", $db1,$account['wx_send_url']);
					mysql_query("UPDATE msmpool set wxsendflg=1 where msm_sn='$msm_sn'", $db1);
				}
				
			}

			// 短信入库
			if ($wxres != 2 && $station_limit['smssend']==1 && $user_limit['smssend']==1) {
				mysql_query("INSERT INTO `msmforsend`(`username`,`rcvnumber`,`sendtime`,`huohao`,`stationid`,`frequency`,`status`,`content`,`m_operator`,`msm_sn` ,`companyflg`,`smsfree`,`spaccount`,`devicesn`) VALUES         ('$username','$rcvnumber','$sendtime','$huohao','$stationid','$frequency','$status','$content','$m_operator','$msm_sn' ,'$companyflg','$smsfree','{$account["spaccount"]}','$devicesn')", $db1);
				mysql_query("UPDATE msmpool set smssendflg=1,content='$content' where msm_sn='$msm_sn'", $db1);
			}
		}
		mysql_query("delete from `msmwait`  WHERE  `flg` = '2'", $db1);
		mysql_query("UPDATE `counter` SET counter2=counter2+$num WHERE `name` ='sendmsm_max_day' LIMIT 1", $db1);
	}

	//对于短信的最大限制，防止出现异常，无限制发送
	public function checkMaxNum($num,$db1){
		$result1 = mysql_query("SELECT counter1,counter2 FROM counter WHERE `name` ='sendmsm_max_day' LIMIT 1", $db1);
		$num1 = mysql_numrows($result1);
		if ($num1 > 0) {
			$maxcounter = mysql_result($result1, 0, "counter1"); //最大发送限制
			$currentvalue = mysql_result($result1, 0, "counter2"); //当前发送量
			
		} else {
			return;
		}
		if (($currentvalue + $num) > $maxcounter) //限制：大于最大数将不能再继续发送
		{
			Log::info('短信数量大于最大发送量');
			return;
		}
	}

	//获取设置数据1
	public function getSetConfig1($db1){
		$arr = array();
		$result1 = mysql_query("SELECT * FROM  config WHERE `op_name` ='yidong' LIMIT 1", $db1);
		$arr['yidong'] = split(",", mysql_result($result1, 0, "section"));
		$arr['yidongkeyword'] = split(",", mysql_result($result1, 0, "keyword"));
		$result1 = mysql_query("SELECT * FROM  config WHERE `op_name` ='liantong' LIMIT 1", $db1);
		$arr['liantong'] = split(",", mysql_result($result1, 0, "section"));
		$arr['liantongkeyword'] = split(",", mysql_result($result1, 0, "keyword"));
		$result1 = mysql_query("SELECT * FROM  config WHERE `op_name` ='dianxin' LIMIT 1", $db1);
		$arr['dianxin'] = split(",", mysql_result($result1, 0, "section"));
		$arr['dianxinkeyword'] = split(",", mysql_result($result1, 0, "keyword"));
		return $arr;
	}

	//获取设置数据2
	public function getSetConfig2($num,$db1){
		$result1 = mysql_query("SELECT * FROM  counter WHERE `name` ='sendrate' LIMIT 1", $db1);
		$rate = array();
		$rate[0] = mysql_result($result1, 0, "counter1");
		$rb1 = $rate[1] = mysql_result($result1, 0, "counter2");
		$rate[2] = mysql_result($result1, 0, "counter3");
		$rb3 = $rate[3] = mysql_result($result1, 0, "counter4");
		$rate[4] = mysql_result($result1, 0, "counter5");
		$rb5 = $rate[5] = mysql_result($result1, 0, "counter6");
		$rate[6] = mysql_result($result1, 0, "counter7");
		$rb7 = $rate[7] = mysql_result($result1, 0, "counter8");
		$rate[1] = floor(($rb1 * $num) / 100);
		$rate[3] = floor((($rb1 + $rb3) * $num) / 100);
		$rate[5] = floor((($rb1 + $rb3 + $rb5) * $num) / 100);
		$rate[7] = floor((($rb1 + $rb3 + $rb5 + $rb7) * $num) / 100);
		return $rate;
	}

	// 根据选择的短信服务商查询该短信服务商的账号
	public function getSpaaccount($companyflg,$manager,$db1){
		$arr = array();
		switch ($companyflg) {
			case '1':
				$res = mysql_query("SELECT `dh_account`,`wx_switch_url`,`wx_send_url` from msmaccount where id='$manager' limit 1", $db1);
				$arr['spaccount'] = mysql_result($res, 0, 'dh_account');
				$arr['wx_switch_url'] = mysql_result($res,0, 'wx_switch_url');
				$arr['wx_send_url'] = mysql_result($res,0, 'wx_send_url');
			break;
			case '2':
				$res = mysql_query("SELECT `zt_account`,`wx_switch_url`,`wx_send_url` from msmaccount where id='$manager' limit 1", $db1);
				$arr['spaccount'] = mysql_result($res, 0, 'zt_account');
				$arr['wx_switch_url'] = mysql_result($res,0, 'wx_switch_url');
				$arr['wx_send_url'] = mysql_result($res,0, 'wx_send_url');
			break;
		}
		return $arr;
	}

	//运营商号码分析
	public function checknumber($rcvnumber, $yidong, $liantong, $dianxin, $db) {
		// $yidong=array(134,135,136,137,138,139,150,151,152,157,158,159,182,183,184,187,188,147,178,1705);
		// $liantong=array(130,131,132,155,156,185,186,145,176,1709);
		// $dianxin=array(133,153,180,181,189,177,173,1700,1701,1702);
		$m_operator = 4;
		$n = count($yidong) - 1;
		for ($i = 0;$i < $n;$i++) {
			$len = strlen($yidong[$i]);
			$rcv = substr($rcvnumber, 0, $len);
			if ($rcv == $yidong[$i]) {
				$m_operator = 1;
				return $m_operator;
			}
		}
		$n = count($liantong) - 1;
		for ($i = 0;$i < $n;$i++) {
			$len = strlen($liantong[$i]);
			$rcv = substr($rcvnumber, 0, $len);
			if ($rcv == $liantong[$i]) {
				$m_operator = 2;
				return $m_operator;
			}
		}
		$n = count($dianxin) - 1;
		for ($i = 0;$i < $n;$i++) {
			$len = strlen($dianxin[$i]);
			$rcv = substr($rcvnumber, 0, $len);
			if ($rcv == $dianxin[$i]) {
				$m_operator = 3;
				return $m_operator;
			}
		}
		return $m_operator;
	}
	//$m_operator暂时没有用， 关键字为三个运营商的全部
	public function checkkeyword($content, $m_operator, $yidong, $liantong, $dianxin, $db) {
		// $yidong=array("法轮功","袭击");
		// $liantong=array();
		// $dianxin=array();
		$n = count($yidong) - 1;
		for ($i = 0;$i < $n;$i++) {
			$pos = strrpos($content, $yidong[$i]);
			if ($pos === false) {
			} else {
				return 1;
			}
		}
		$n = count($liantong) - 1;
		for ($i = 0;$i < $n;$i++) {
			$pos = strrpos($content, $liantong[$i]);
			if ($pos === false) {
			} else {
				return 1;
			}
		}
		$n = count($dianxin) - 1;
		for ($i = 0;$i < $n;$i++) {
			$pos = strrpos($content, $dianxin[$i]);
			if ($pos === false) {
			} else {
				return 1;
			}
		}
		return 0;
	}
	//------------------------------------------------
	// n为总数，  m为当前计数    返回  公司标志
	public function getcompanyflg($m, $rate) {
		$ret = 0;
		//echo "</br>";
		//print_r($rate);
		if ($m >= $rate[5]) {
			$ret = $rate[6];
		} else if ($m >= $rate[3]) {
			$ret = $rate[4];
		} else if ($m >= $rate[1]) {
			$ret = $rate[2];
		} else {
			$ret = $rate[0];
		}
		return $ret;
	}

	//扣费
	public function charge($devicesn,$username,$smsfree,$wxres,$boxId,$station_limit,$user_limit,$content){
		// 进行收费
		$db = conn();
		// 如果是智能柜发短信，则查询智能柜收费信息
		if ($boxId && $devicesn) {
			$result = mysql_query("SELECT small,middle,large,smssend FROM   smartbox.smtbx_info  where  devicesn='$devicesn'", $db);
			$num = mysql_numrows($result);
			if ($num == 0) {
				// 没有该智能柜
				return false;
			}
			$small = mysql_result($result, 0, "small"); //1000为1元
			$middle = mysql_result($result, 0, "middle");
			$large = mysql_result($result, 0, "large");
			$smssend = mysql_result($result, 0, "smssend");
		}else{
			$smssend = 1;
		}
		
		// 	查询用户
		$result = mysql_query("SELECT * FROM   deeyee.user  where  username='$username'", $db);
		$num = mysql_numrows($result);
		//判断有无这个用户
		if ($num == 0) {
			return false; //没有这个用户
			
		}
		$befor_fund = mysql_result($result, 0, "fund");
		
		// 格口收费时短信是否还收费
		if ($wxres != 2 && $station_limit['smssend']==1 && $user_limit['smssend']==1) {
			$rate = mysql_result($result, 0, "rate");

			if ($rate == 1000||($smssend==0)) {
				$rate = 0; //不收费
				
			} else if ($rate == 0) {
				$rate = 70; //默认7分/条
				
			}
			$msgCount = $this->str_len($content);
			$after_fund = $befor_fund - $rate*$msgCount; //短信费用
			
		} else {
			$after_fund = $befor_fund; //不扣费
			
		}

		// 如果有格口，智能柜进行扣费
		$rate = 0;
		if ($smsfree == 1 && $boxId && $devicesn) {
			// 判断大小格口,收费
			$boxsize = $this->getBoxSize($devicesn,$boxId,$db);
			switch ($boxsize) {
				case 3:
					$rate = $large;
					break;
				case 2:
					$rate = $middle;
					break;
				case 1:
					$rate = $small;
					break;
			}
			$after_fund1 = $after_fund - $rate; //格口费用
			
		}
		$last_fund = $after_fund1 ? $after_fund1 : $after_fund;
		//写入数据库
		$sendtime = mktime();
		//改变用户的资金
		$sqlstr = "UPDATE  deeyee.user SET  `fund` =  '$last_fund'  WHERE  `username` =$username  LIMIT 1";
		$res = mysql_query($sqlstr, $db);

		if($befor_fund > $after_fund){
			//扣除费用记录
			$sqlstr = "INSERT INTO  deeyee.consumingrecords (`username` ,`beforsend` ,`aftersend` ,`msm_num` ,`operation`,`devicesn`,`time` ) 
			VALUES ('$username',  '$befor_fund',  '$after_fund', '$msgCount' ,'1',  '$devicesn',  '$sendtime')";
			mysql_query($sqlstr, $db);
		}
		

		if ($rate != 0) //格口收费时进行记录
		{
			//格口费用记录
			$sqlstr = "INSERT INTO  deeyee.consumingrecords (`username` ,`beforsend` ,`aftersend` ,`msm_num` ,`operation`,`devicesn`,`time`,`boxsize` ) 
		VALUES ('$username',  '$after_fund',  '$after_fund1', '$msgCount' ,  '1',  '$devicesn',  '$sendtime','$boxsize')";
			mysql_query($sqlstr, $db);
		}

		return true;
		
	}

	// 站点限制
	public function station_limit($stationid,$db1){
		$station_limit = array();
		$sql = "SELECT * from account_station where stationaccount='$stationid' limit 1";
		$result = mysql_query($sql,$db1);
		if(mysql_num_rows($result)>0){
			$station_limit['smssend'] = mysql_result($result, 0, "smssend");
			$station_limit['wxsend'] = mysql_result($result, 0, "wxsend");
			$station_limit['smslimit'] = mysql_result($result, 0, "smslimit");
		}else{
			$station_limit['smssend'] = 1;
			$station_limit['wxsend'] = 1;
			$station_limit['smslimit'] = 0;
		}
		return $station_limit;
	}
	// 账户限制
	public function user_limit($username,$db1){
		$user_limit = array();
		$sql = "SELECT * from account_user where username='$username' limit 1";
		$result = mysql_query($sql,$db1);
		if(mysql_num_rows($result)>0){
			$user_limit['smssend'] = mysql_result($result, 0, "smssend");
			$user_limit['wxsend'] = mysql_result($result, 0, "wxsend");
			$user_limit['smslimit'] = mysql_result($result, 0, "smslimit");
		}else{
			$user_limit['smssend'] = 1;
			$user_limit['wxsend'] = 1;
			$user_limit['smslimit'] = 0;
		}
		return $user_limit;
	}
	// 短信屏蔽取货码或取货号
	public function passwordHide($user_limit,$station_limit,$content,$password,$huohao,$rcvnumber_o,$db){

		// 黑名单功能不屏蔽取件码
		$whiteRes = mysql_query("SELECT id FROM  dyhawk.whitelist  where  phonenumber='$rcvnumber_o' and  category=1",$db);
		if(mysql_num_rows($whiteRes)!=0){

			if($password){
				$content='取货码:'.$password.','.$content;
			}elseif($huohao){
				$content="<取货号:".$huohao.">".$content;
			}
			return $content;
		}

		if($password){//必须要取件码存在才屏蔽取件码，例如发送普通短信的时候就不需要取件码。
			if($user_limit==1 || $station_limit==1){
				$content=$content;
			}else{
				$content='取货码:'.$password.','.$content;
			}
		}
		if($huohao){//必须要取件码存在才屏蔽取件码，例如发送普通短信的时候就不需要取件码。
			if($user_limit==1 || $station_limit==1){
				$content=$content;
			}else{
				$content="<取货号:".$huohao.">".$content;
			}
		}
		return $content;
	}

	// 短信限制查询(如果短信限制表中有该账户的该号码，则不发送短信)
	public function limitPhone($phone,$manager,$db1){
		$sql = "SELECT * from limit_phone where phone='$phone' and account_id='$manager' limit 1";
		$result = mysql_query($sql,$db1);
		if(mysql_num_rows($result)>0){
			return 2;
		}else{
			return 1;
		}
	}
	// 计算短信数
	public function str_len($content)
	{  //将货号短语结合成字符串作为输入，最后输出短信的条数
	  //utf-8编码
	  //本函数适应于utf-8编码
	  //货号的前后两个括号<取货号：>6个，+【递易智能】 6个  共12个字符。 所以短信货号+短语 <=58,取货号那6个可以不加了，已经包含在内容里了
	  $all_len=mb_strlen($content,"UTF-8");
	  $end_len=$all_len+6+70-1;  //包含70
	  $num=floor($end_len/70);
	  return  $num;
	}

		// 获取格口大中小
	public function getBoxSize($devicesn,$boxId,$db){
		$boxsize = 0;
		$sql = "SELECT sys_set from smartbox.smtbx_info where devicesn=$devicesn";
		$res = mysql_query($sql,$db);
		if(mysql_num_rows($res)>0){
			$sys_set = mysql_result($res, 0,'sys_set');
			$boxSetInfo = explode(';;',$sys_set);
	        $boxSet = array();
	        $boxTmp = array();
	        foreach($boxSetInfo as $vo){
				//取得不同设置项的信息
	            $boxTmp = explode(',,',$vo);
				//取得每个配置项信息
	            $boxSet[$boxTmp[0]] = $boxTmp[1];
	        }
	        $jixing = $boxSet['DeviceType'];//机型
	        switch ($jixing) {
	        	case '三型机':
	        		$boxId = $boxId % 15;
					if (($boxId == 1) || ($boxId == 0)) {
						$boxsize = 3;
					} else {
						$boxsize = 1;
					}
	        		break;
	        	case '四型机':
	        		$boxId = $boxId % 9;
					if (($boxId == 1) || ($boxId == 0)) {
						$boxsize = 3;
					} else if (($boxId == 2) || ($boxId == 8)) {
						$boxsize = 2;
					} else if (($boxId == 3) || ($boxId == 4) || ($boxId == 5) || ($boxId == 6) || ($boxId == 7)) {
						$boxsize = 1;
					}
	        		break;
	        	default:
	        		// 默认为一型机和二型机
					$boxId = $boxId % 10;
					if ($boxId == 0) $boxId = 10;
					if (($boxId == 1) || ($boxId == 10)) {
						$boxsize = 3;
					} else if (($boxId == 2) || ($boxId == 3) || ($boxId == 8) || ($boxId == 9)) {
						$boxsize = 2;
					} else if (($boxId == 4) || ($boxId == 5) || ($boxId == 6) || ($boxId == 7)) {
						$boxsize = 1;
					}
	        		break;
	        }
		}
		return $boxsize;
	}
}
