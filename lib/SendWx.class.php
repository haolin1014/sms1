<?php
class SendWx{
	/************************************************
	函数名称： sendmessagetoweixin
	函数参数：  
	函数作用： 将微信通知内容写入数据表wxnotice中
	$rcvnumber= 用户号码,
	$artno=货号,没有空白
	$password=智能柜密码,没有空白
	$boxno=智能柜格口号,
	$expressno=运单号,
	$first=微信的开始行的内容,
	$content=微信的主要内容,
	$expressflg=运单的性质，到达/结束     0到达 1结束
	************************************************/ 
	public static function  sendmessagetoweixin($stationid,$msm_sn,$rcvnumber,$artno,$password,$boxno,$expressno,$first,$remark,$status,$db,$wx_send_url)
	{ 
			        //货号增加柜号				
			if($password!="")
			{		     	
		 		$pdasn=str_pad($stationid,12,"0",STR_PAD_LEFT); //对于不足12位的前边补零
		 		$result1 = mysql_query("SELECT  stationmark  FROM  smartbox.smtbx_info  where  devicesn='$pdasn'",$db);  
	     		$num1= mysql_numrows($result1);
		 		if($num1!=0)
		 		{
		    		$boxno=mysql_result($result1,0,"stationmark")."号柜-".$boxno;
		 		}
			}				
				
	   $sqlstr="INSERT INTO kmsmsend.wxforsend(`stationid`,`msm_sn`,`rcvnumber`,`artno`,`password`,`boxno`,`expressno`,`first`,`remark`,`status`,`flg`,`wx_send_url`) VALUES ('$stationid','$msm_sn','$rcvnumber','$artno','$password','$boxno','$expressno','$first','$remark','$status','0','$wx_send_url')";                     
	      mysql_query($sqlstr,$db);
	}

	public static function  getweixinswitchsms($rcvnumber,$wx_switch_url,$db)  // 0没关注，1发微信+发短信  2发微信+不发短信
	{
	
	      if($wx_switch_url){
	      	$url="http://weixin.diyibox.com/index.php?s=/Api/get_sms_status1/mobile/$rcvnumber";
	      }else{
	      	$url = $wx_switch_url."?mobile=$rcvnumber";
	      }
	      
		  try
		  {  
		  $html = file_get_contents($url);
	      $html=str_replace(":",",",$html);
		  $html=str_replace("\"","",$html);	  
	      $str=split(",",$html);  
		  $ret0=$str[1];  //得到该号码的关注和短信是否需要发送的状态， 0没有该号码，1需要发送短信，2不需要发送短信
		  }  
		  catch(Exception $e) 
		  {
		  }	 
		  
		  return  $ret0; 
	 

	    //  return  1;
	}

	/*******************************
	   微信定时任务
	   发送微信通知   
	   原理：对于weixin.wxnotice中的号码发微信通知。在发送之前，首先判断该号码是否关注，若关注再发为微信通知。同时，将该号码的短信需求更新在weixin.rcvnumbersms中
	   
	*******************************/
	public function sendwxnotice($db)
	{ 

	   $limit_n=50;  //20
	   //1)标记出要操作的记录
	   mysql_query("UPDATE weixin.wxnotice SET flg=0",$db);
	   mysql_query("UPDATE weixin.wxnotice SET flg=5  order by id asc  LIMIT $limit_n",$db); 
	  //2)对标记出的记录发送微信通知
	  $result = mysql_query("SELECT * FROM  weixin.wxnotice  where  flg=5",$db); 
	  $num= mysql_numrows ($result);
	  //echo "num=$num";
	  for($i=0;$i<$num;$i++)
	  {  // echo "ii=$i";
	      $rcvnumber=mysql_result($result,$i,"rcvnumber"); //手机号码
		  //下边代码注释原因为不需要二次判断关注情况
	     // $url="http://weixin.diyibox.com/index.php/Api/get_sms_status1/mobile/$rcvnumber";
	/*	  $url="http://weixin.diyibox.com/index.php?s=/Api/get_sms_status1/mobile/$rcvnumber";
		   
		  $html = file_get_contents($url);
	      $html=str_replace(":",",",$html);
		  $html=str_replace("\"","",$html);	  
	      $str=split(",",$html);  
		  $ret0=$str[1];  //得到该号码的关注和短信是否需要发送的状态， 0没有该号码，1需要发送短信，2不需要发送短信
	*/	 // echo "ret0=$ret0";
	$ret0=1;
		  if($ret0==0)   //没有关注，直接删除这个记录
		  {
		      $sqlstr="DELETE FROM   weixin.rcvnumbersms  where  rcvnumber='$rcvnumber'";	
	          mysql_query($sqlstr,$db);   
		  }
		  else  //需要发微信
	      {
		    $rcvnumber=mysql_result($result,$i,"rcvnumber");
			$artno=mysql_result($result,$i,"artno");
			$password=mysql_result($result,$i,"password");
			$boxno=mysql_result($result,$i,"boxno");
			$expressno=mysql_result($result,$i,"expressno");
			$first=mysql_result($result,$i,"first");
			$remark=mysql_result($result,$i,"remark");
			$status=mysql_result($result,$i,"status");
			$stationid=mysql_result($result,$i,"stationid");
			$msm_sn=mysql_result($result,$i,"msm_sn");				
		    $ret=$this->sendweixin($rcvnumber,$artno,$password,$boxno,$expressno,$first,$remark,$status,$db); 
					
			if(($ret!=0)&&($ret!=1))
			{
			  return;  //若不正常，直接返回
			}	

	/*		
			//改变发短信状态（仅对不发短信的运单记录，改变短信标志为成功。 微信这里只要发送即为成功）
			changesmsstatus($stationid,$msm_sn,$rcvnumber,$db);	
				
			//改变号码对应的短信需求状态
		    $result11 = mysql_query("SELECT smsflg FROM  weixin.rcvnumbersms  where  rcvnumber='$rcvnumber'",$db);  
	        $num11= mysql_numrows($result11);		
			$flg=$ret0-1;  //已经关注， 0需要发送短信， 1不需要发送端
	        if($num11>0)  
			{
			   mysql_query("UPDATE  weixin.rcvnumbersms  SET `smsflg`='$flg'",$db);
			}
			else
			{
	          mysql_query("INSERT INTO  weixin.rcvnumbersms ( `rcvnumber`,`smsflg`) VALUES ('$rcvnumber','$flg')",$db); 
			}
			
		*/	
		  }

	   }
	   //3)删除已经标出的记录
	   $sqlstr="DELETE FROM  weixin.wxnotice  WHERE    flg=5";	
	   mysql_query($sqlstr,$db);   
	 
	 }
	/**********************************
	 发送微信
	***********************************/
	public function  sendweixin($rcvnumber,$artno,$password,$boxno,$expressno,$first,$remark,$status,$db)
	{
	   
	     //得到快递公司名称
		  
			     $result = mysql_query("SELECT expressname FROM  dyhawk.logistics  where  expressno='$expressno'  limit 1",$db);  
			     $num= mysql_numrows ($result);
				 if($num>0)
				 {
				   $expressname=mysql_result($result,0,"expressname"); 
				   $result = mysql_query("SELECT name FROM  dyhawk.expresscompany  where  code='$expressname'  limit 1",$db);  
			       $num= mysql_numrows ($result);
				   if($num>0)
				   {
				     $name=mysql_result($result,0,"name");
				   } 		 
				 }
		          $expressname=$name;  //若无名称时显示为空。
	  //  echo "ss=$expressname,  $rcvnumber,$artno,$password,$boxno,$expressno,$first,$remark,$status, </br>";
		 $data=array();
		 $data["first"]=$first;
		 $data["remark"]=$remark;
		 $data["number"]=$expressno;
		 $data["exname"]=$expressname;
		 $data["mobile"]=$rcvnumber;
		 $data["artno"]=$artno;
		 $data["password"]=$password;
		 $data["container"]=$boxno; 
		 $data["status"]=$status; 	 
		// $url="http://weixin.diyibox.com/index.php/Api/send_express_info";
		 $url="http://weixin.diyibox.com/index.php?s=/Api/send_express_info";
		 
	     $ret=$this->post($url,$data);
	     //$ret=json_decode($ret,true);
		  $ret=str_replace(":",",",$ret);
		  $ret=str_replace("\"","",$ret);	  
	      $str=split(",",$ret);  
		  $ret=$str[3]; 
		  return  $ret;  //0,没有号码，1正常， 其他不正常

	}

	public function post($url,$data){
	        $ch = curl_init();
	        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
	        curl_setopt($ch,CURLOPT_URL, $url);
	        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
	        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
	        curl_setopt($ch, CURLOPT_HEADER, FALSE);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	        curl_setopt($ch, CURLOPT_POST, TRUE);
	        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
	        $data = curl_exec($ch);
	        curl_close($ch);
	        return $data;    
	 }
}