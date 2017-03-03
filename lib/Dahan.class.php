<?php
class Dahan{
	private $account;
	private $password;
	private $sign;

	public function __construct($account,$password,$sign){
		$this->account = $account;
		$this->password = md5($password);
		$this->sign = $sign;
	}

	//==========================发送短信==================================================================
	//发送成功的短信转移到poolmsm表中， 对于发送不成的包，还放置在msmforsend表中，标志位置10，等待人工处理
	 public function  sendsms()
	 {
	   $db1=conn7();
	   $cflg='1';  //2代表助通
	   
	   mysql_query("UPDATE  msmforsend SET `send_m`='0'     limit  100000  ",$db1);
	   //圈定要发送的短信
	   mysql_query("UPDATE `msmforsend` SET `send_m` = '2'  where  `send_m` = '0'    and  companyflg='$cflg' and spaccount='{$this->account}'  order by sendtime asc limit 200",$db1);
	   //发送短信准备 
	   $result = mysql_query("SELECT * FROM msmforsend  where  `send_m`='2'    and  companyflg='$cflg' and spaccount='{$this->account}' " ,$db1);
	   $num= mysql_numrows($result);
	   $phonenumber="";
	   $sn="";
	   $content=array();
	   

	   for($i=0;$i<$num;$i++)
	   {
	     if($i<$num-1)
		 {
			$phonenumber=$phonenumber.mysql_result($result,$i,"rcvnumber").",";
			$sn=$sn.mysql_result($result,$i,"msm_sn").",";
		 }
		 else
		 {
		  	$phonenumber=$phonenumber.mysql_result($result,$i,"rcvnumber");
			$sn=$sn.mysql_result($result,$i,"msm_sn");
		 }    
	     $content[$i]=mysql_result($result,$i,"content");        
	   }
	   if($num==0)
	   {
	     return;
	   }

	   
	   
	   //return;
	   try
	   {
	    //发送短信
	   	$soap = new SoapClient("http://ws.3tong.net/services/smsAgile1?wsdl");

	    $reponse=$soap->sendSms($this->account,$this->password,$phonenumber,$content,$sn,"","【".$this->sign."】"); 
	    // var_dump($reponse);
		}
		catch(Exception $e)
		{  
		 $ttt=date("Y-m-d H:i:s",time());
		 $rrr="send=0";
	     $strstr="INSERT INTO `applog`(`content`,`time`) VALUES ('$rrr','$ttt')";
	     mysql_query($strstr,$db1); 
		 echo "send bad";
		 return;
		}
		
		
	    //解析响应
		$p = xml_parser_create();
		xml_parse_into_struct($p, $reponse, $vals, $index);
		xml_parser_free($p);
		// var_dump($vals);
	/*	
		print_r($phonenumber);  //test
		echo "</br>";
		echo "</br>";	
		print_r($content);  //test
		echo "</br>";	
		echo "</br>";	
		print_r($sn);  //test
		echo "</br>";	
		echo "</br>";		
		
	    print_r($vals);  //test
	*/	
		
	    
		$n=count($vals);	
		$result=$vals[2][value];
		$failphone="";
		
		if(($n>5)&&($result==0)) //正确发送， 然后处理发送不成功的号码
		{
		 
		   $str1=$vals[4][value];
		   $str2=$vals[5][value];  
		  // echo "$str1  $str2";
		   if($str1!="")
		   {
		     $failphone=$str1.",".$str2;
		   }
		   else 
		   {
		       $failphone=$str2;
		   }
		   
		   $blacklist=split(",",$failphone);
		   $len=count($blacklist);
		   	  
		   for($i=0;$i<$len;$i++)
		   {
		     $number=$blacklist[$i];
		     mysql_query("UPDATE `msmforsend` SET `status` = '3'  where   rcvnumber='$number' and  `send_m`='2'   and  companyflg='$cflg'",$db1);  	   
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
		   		$sql = "UPDATE msmpool set smssendop=1,status='$status',companyflg='$companyflg',m_operator='$m_operator' where msm_sn='$msm_sn' ";
		   		mysql_query($sql,$db1);
		   	}

	// $sqlstr="
	//    INSERT  INTO  msmpool(username,rcvnumber,sendtime,huohao,stationid,frequency,status,content,m_operator,msm_sn,companyflg)
	//     SELECT   
	//         username,rcvnumber,sendtime,huohao,stationid,frequency,status,content,m_operator,msm_sn,companyflg 
	//     FROM   msmforsend  WHERE  send_m=2   and  companyflg='$cflg' ";
	//   mysql_query($sqlstr,$db1);
	  
		  //删除
	  $sqlstr="DELETE FROM msmforsend WHERE  send_m=2   and  companyflg='$cflg' ";
	  mysql_query($sqlstr,$db1);   
		
		  
		}
		else  //对于短信包发送没有回应的内容，先放在msmforsend这个待发送表中，设置标志位为10
		{
		    mysql_query("UPDATE `msmforsend` SET `send_m` = '10'  where  `send_m` = '2'  and  companyflg='$cflg'",$db1);	
		}
			
	}

	//===================获取短信状态报告========================================================= 
	public function  getsmsreport()
	{
	   $db1=conn7();
	   $cflg=1;
	   try
	   {   
	   $soap = new SoapClient("http://ws.3tong.net/services/smsAgile1?wsdl"); 
	   //$soap = new SoapClient("http://3tong.net/services/smsAgile?wsdl");

	   $url="http://wt.3tong.net/http/sms/StatusReport";
	   //$url="http://3tong.net/http/sms/StatusReport";
		}
		catch(Exception $e)
		{  
		 $ttt=date("Y-m-d H:i:s",time());
		 $rrr="rcv=1";
	     $strstr="INSERT INTO `applog`(`content`,`time`) VALUES ('$rrr','$ttt')";
	     mysql_query($strstr,$db1);
		 echo "rcv bad" ;
		 return;
		}

	   $reponse=$soap->getReport($this->account,$this->password);  
	    // var_dump($reponse);
	     //解析响应
		$p = xml_parser_create();
		xml_parse_into_struct($p, $reponse, $vals, $index);
		xml_parser_free($p);
		// var_dump($vals);
		

	    $n=floor(count($vals)/10);	 	 
		if($n>0)
		{  //获取到状态
		  for($i=0;$i<$n;$i++)
		  {
		     $msgid=$vals[$i*10+4][value];
			 $status=$vals[$i*10+6][value];
		     if($status==0)
			 {
			    $res = mysql_query("UPDATE `msmpool` SET `status` = '2'  where   msm_sn='$msgid'",$db1);
			 }
			 else
			 {
			    $res = mysql_query("UPDATE `msmpool` SET `status` = '3'  where   msm_sn='$msgid'",$db1);
			 }	  
		  
		  }
		  
		}
		else 

		{
		   	//批量获取为空,开始进行单条查询，若ok即成功，其它为失败
		   $time0=time()-3600*5;  //4小时限制，超过4小时没有结果的算失败  4小时后再不能单个获取状态
		   mysql_query("UPDATE `msmpool` SET `status` = '3'  where  `sendtime`<'$time0' and  companyflg='$cflg' ",$db1);
		}
		
		

	}
}