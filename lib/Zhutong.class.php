<?php
class Zhutong{
	private $account;
	private $password;
	private $sign;

	public function __construct($account,$password,$sign){
		$this->account = $account;
		$this->password = $password;
		$this->sign = $sign;
	}

	public function  sendsms()
	 {
	   $db1=conn7();

	   $cflg='2';  //2代表助通
	   mysql_query("UPDATE  msmforsend SET `send_m`='0'     limit  100000  ",$db1);
	   //圈定要发送的短信
	   mysql_query("UPDATE `msmforsend` SET `send_m` = '2'  where  `send_m` = '0'  and  companyflg='$cflg' and spaccount='{$this->account}'  order by sendtime asc limit 200",$db1);
	   //发送短信准备 
	   $result = mysql_query("SELECT * FROM msmforsend  where  `send_m`='2'   and  companyflg='$cflg' and spaccount='{$this->account}' " ,$db1);
	   $num= mysql_numrows($result);
	   $arr = array();

	   for($i=0;$i<$num;$i++)
	   {
	       $rdstr=rand(0,100000);
	       $arr[$i]['msgid']=mysql_result($result,$i,"msm_sn").$rdstr;
		   $arr[$i]['phones']=mysql_result($result,$i,"rcvnumber");
		   $content=mysql_result($result,$i,"content");
		   $content=str_replace("【","<",$content);
		   $content=str_replace("】",">",$content); 
		   $arr[$i]['content']=$content;
		  
	   }

	   if($num==0)
	   {
	     return;
	   }

	   try
	   {
	      $res = $this->sendBatch($arr);
	 
		}
		catch(Exception $e)
		{  
		 $ttt=date("Y-m-d H:i:s",time());
		 $rrr="send=0";
	     $strstr="INSERT INTO `applog`(`content`,`time`) VALUES ('zhutongsend','$ttt')";
	     mysql_query($strstr,$db1); 
		 echo "send bad";
		 return;
		}
		  
			//var_dump($res);

			$res=json_decode($res,true);
			// var_dump($res);   		
			$a=$res["result"];  //该命令的总状态  0正常，其他不正常

		if($a==0) //正确发送， 然后处理发送不成功的号码
		{
		   $len=count($res["data"]);  //号码的总数
		   for($i=0;$i<$len;$i++)
		   {
		   
		      $b=$res["data"][$i]["result"];
			  $c=$res["data"][$i]["msgid"];
			  $c=mb_substr($c,0,20);  //
		      if($b!=0)
			  {
			    mysql_query("UPDATE `msmforsend` SET `status` = '3'  where   msm_sn='$c' and  `send_m`='2'  and  companyflg='$cflg'",$db1);  
			  }
			} 
		   
		    //2）拷贝	
		    // 循环更新短信发送状态
		    $wsql = "SELECT username,rcvnumber,sendtime,huohao,stationid,frequency,status,content,m_operator,msm_sn,companyflg FROM msmforsend  WHERE  send_m=2   and  companyflg='$cflg' ";
		    $wres = mysql_query($wsql,$db1);
		    $wnum = mysql_num_rows($wres);
		    for($i=0;$i<$wnum;$i++){
		    	$msm_sn = mysql_result($wres,$i,'msm_sn');
		    	$status = mysql_result($wres,$i, 'status');
		    	$companyflg = mysql_result($wres,$i, 'companyflg');
		    	$m_operator = mysql_result($wres,$i, 'm_operator');
		    	$sql = "UPDATE msmpool set smssendop=1,status='$status',companyflg='$companyflg',m_operator='$m_operator' where msm_sn='$msm_sn' ";
		    	mysql_query($sql,$db1);
		    }
				// $sqlstr="
	   // 			INSERT  INTO  msmpool(username,rcvnumber,sendtime,huohao,stationid,frequency,status,content,m_operator,msm_sn,companyflg)
	   //  		SELECT   
	   //      			username,rcvnumber,sendtime,huohao,stationid,frequency,status,content,m_operator,msm_sn,companyflg 
	   //  		FROM   msmforsend  WHERE  send_m='2' and  companyflg='$cflg'";
	  	// 		mysql_query($sqlstr,$db1);
	  
		  		//删除
	  			$sqlstr="DELETE FROM msmforsend WHERE  send_m='2' and   companyflg='$cflg'";
	  			mysql_query($sqlstr,$db1);      
		   
		   
		   
		   }
		   else  //对于短信包发送没有回应的内容，先放在msmforsend这个待发送表中，设置标志位为10
		  {
		    mysql_query("UPDATE `msmforsend` SET `send_m` = '10'  where  `send_m` = '2' and  companyflg='$cflg' ",$db1);	
		  }	  
		
	}



	//===================获取短信状态报告========================================================= 
	public function  getsmsreport()
	{
	   $db1=conn7();
	   $cflg=2;

	   try
	   {   
	      $res=$this->getMsgState();
		}
		catch(Exception $e)
		{  
		 $ttt=date("Y-m-d H:i:s",time());
		 $rrr="rcv=1";
	     $strstr="INSERT INTO `applog`(`content`,`time`) VALUES ('zhutongget','$ttt')";
	     mysql_query($strstr,$db1);
		 echo "rcv bad" ;
		 return;
		}

		// whl
		// $whl = json_decode($res,true);
		// if($whl["result"]==0){
		// 	$con = date('Y-m-d H:i:s').'-'.serialize($res);
		// 	$con = $con."\r\n";
		// 	file_put_contents('./msg.log', $con,FILE_APPEND);
		// }
		
	    $res=json_decode($res,true); 	
		// var_dump($res);
		$a=$res["result"];  //该命令的总状态  0正常，其他不正
	 	if($a==0) //正确获取状态， 然后处理每一个获取的号码状态
		{
		   $len=count($res["reports"]);  //获取号码状态的总数
		   for($i=0;$i<$len;$i++)
		   {
		      $msgid=$res["reports"][$i]["msgid"];
			  $msgid=mb_substr($msgid,0,20);  // msm_sn
			  
			  $status=$res["reports"][$i]["status"];	

			  // whl
			  // $con = date('Y-m-d H:i:s').'-'.$msgid.'-'.$status;
			  // $con = $con."\r\n";
			  // file_put_contents('./msg.log', $con,FILE_APPEND);

		     if($status==0)
			 {
			    $res1 = mysql_query("UPDATE `msmpool` SET `status` = '2'  where   msm_sn='$msgid'",$db1);
			 }
			 else
			 {
			    $res1 = mysql_query("UPDATE `msmpool` SET `status` = '3'  where   msm_sn='$msgid'",$db1);
			 }	  	    
		   }
		   
		 }
		 else
		 {
		    $time0=time()-3600*5;  //4小时限制，超过4小时没有结果的算失败  4小时后再不能单个获取状态
		    mysql_query("UPDATE `msmpool` SET `status` = '3'  where  `sendtime`<'$time0'  and   companyflg='$cflg' ",$db1);

		 }  	
	}
	 
	 
	//批量发送 
	 
	 	public function sendBatch($messageArr){
			$url 		= "http://www.api.zthysms.com/DhstSendSms.do";//提交地址
			$account 	= $this->account;//用户名
			$password 	= $this->password;//原密码
			$sendAPI = new sendAPI1($url, $account, $password);
			$data = array();
			foreach ($messageArr as $v) {
				$arr = array(
										'msgid' 	=> $v['msgid'],//消息id
										'content' 	=> $v['content'],//短信内容
										'phones' 	=> $v['phones'],//手机号码
										'sign'		=> '【'.$this->sign.'】',//签名
										'subcode'	=> '',//小号
										'sendtime'	=> ''//定时发送时间
									);
				$data['data'][] = $arr;
			}
			$data['account'] = $account;
			$data['password'] = md5($password);
			$sendAPI->data = $data;//初始化数据包
			$return = $sendAPI->sendSMSBatch();
			return $return;
		}
	 
		/**
		 * 获取短信状态（获取状态报告，请求无数据返回，建议客户端休眠30秒再进行请求，每次最多取100条状态报告）
		 * @param  string $phones 查询电话号
		 * @return json 发送结果集
		 */
		public function getMsgState(){
			$url 		= "http://www.api.zthysms.com/DhstBatchreport.do";//提交地址
			$account 	= $this->account;//用户名
			$password 	= $this->password;//原密码
			$sendAPI = new sendAPI1($url, $account, $password);
			$data = array(
				'msgid' 	=> '',//消息id
				'phone' 	=> '',//手机号码
			);
			$sendAPI->data = $data;//初始化数据包
			$return = $sendAPI->sendSMS1();
			//$res = json_decode($return,true);
			return $return;
		} 
}

/****************************************************
 * 发送短信API
 * 最低运行环境PHP5.3
 * 请确认开启PHP CURL 扩展
 * @author wanghaolin
 * @version 1.0
 *****************************************************/

class sendAPI1 {
	public $data;	//发送数据
	public $timeout = 30; //超时
	private $apiUrl;	//发送地址
	private $account;	//用户名
	private $password;	//密码

	function __construct($url, $account, $password) {
	 	$this->apiUrl 	= $url;
	 	$this->account  = $account;
	 	$this->password = $password;
	}

	private function httpPost(){ // 模拟提交数据函数 
		$curl = curl_init(); // 启动一个CURL会话      
		curl_setopt($curl, CURLOPT_URL, $this->apiUrl); // 要访问的地址                  
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查      
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在      
		curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']); // 模拟用户使用的浏览器      
		curl_setopt($curl, CURLOPT_POST, true); // 发送一个常规的Post请求
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($this->data)); // Post提交的数据包
		curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout); // 设置超时限制防止死循环      
		curl_setopt($curl, CURLOPT_HEADER, false); // 显示返回的Header区域内容      
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // 获取的信息以文件流的形式返回
		$result = curl_exec($curl); // 执行操作      
		if (curl_errno($curl)) {      
			echo 'Error POST'.curl_error($curl);      
		}      
		curl_close($curl); // 关键CURL会话      
		return $result; // 返回数据      
	}

    /**
     * @param $isTranscoding|是否需要转 $isTranscoding 是否需要转utf-8 默认 false
     * @return mixed
     */
	public function sendSMS($isTranscoding = false) {
		@$this->data['content'] 	= $isTranscoding === true ? mb_convert_encoding($this->data['content'], "UTF-8") : $this->data['content'];
		$this->data['account'] = $this->account;
		$this->data['password'] = md5($this->password);
		return $this->httpPost();
	}
	public function sendSMS1($isTranscoding = false) {
		$this->data['account'] = $this->account;
		$this->data['password'] = md5($this->password);
		return $this->httpPost();
	}
	// 批量发送调用的方法
	public function sendSMSBatch($isTranscoding = false) {
		return $this->httpPost();
	}

}
