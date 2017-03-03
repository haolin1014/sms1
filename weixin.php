<?php

include_once("../dataserve/conn_mysql.php");

$db=conn();

sendwxnotice($db);

echo "okok";

/*
//test
$rcvnumber="13761633599";
$artno="wwww-123";
$password="qwe123";
$boxno="A-120";
$expressno="23456789";
$first="间谍飞哥京东方";
$remark="jdfghsdkjglhjgkhdkjsgh对景挂画收到孔径了供货收到孔径了供货";
$status="1";


  $db=conn();
  $db=conn();


      $rcvnumber="13761633599";

	  $url="http://weixin.diyibox.com/index.php/Api/get_sms_status1/mobile/$rcvnumber"; 
	  $html = file_get_contents($url);
      $html=str_replace(":",",",$html);
	  $html=str_replace("\"","",$html);	  
      $str=split(",",$html);  
	  $ret=$str[1];

	

//echo "sdf=$ret "; 




/*******************************
   微信定时任务
   发送微信通知   
   原理：对于weixin.wxnotice中的号码发微信通知。在发送之前，首先判断该号码是否关注，若关注再发为微信通知。同时，将该号码的短信需求更新在weixin.rcvnumbersms中
   
*******************************/
function  sendwxnotice($db)
{ 

   $limit_n=50;  //20
   //1)标记出要操作的记录
   mysql_query("UPDATE kmsmsend.wxforsend SET flg=0",$db);
   mysql_query("UPDATE kmsmsend.wxforsend SET flg=5  order by id asc  LIMIT $limit_n",$db); 
  //2)对标记出的记录发送微信通知
  $result = mysql_query("SELECT * FROM  kmsmsend.wxforsend  where  flg=5",$db); 
  $num= mysql_numrows ($result);
  //echo "num=$num";
  for($i=0;$i<$num;$i++)
  {  // echo "ii=$i";
      $rcvnumber=mysql_result($result,$i,"rcvnumber"); //手机号码
    //下边代码注释原因为不需要二次判断关注情况
     // $url="http://weixin.diyibox.com/index.php/Api/get_sms_status1/mobile/$rcvnumber";
/*    $url="http://weixin.diyibox.com/index.php?s=/Api/get_sms_status1/mobile/$rcvnumber";
     
    $html = file_get_contents($url);
      $html=str_replace(":",",",$html);
    $html=str_replace("\"","",$html);   
      $str=split(",",$html);  
    $ret0=$str[1];  //得到该号码的关注和短信是否需要发送的状态， 0没有该号码，1需要发送短信，2不需要发送短信
*/   // echo "ret0=$ret0";
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
    $wx_send_url=mysql_result($result,$i,"wx_send_url"); //微信发送地址       
    $ret=sendweixin($rcvnumber,$artno,$password,$boxno,$expressno,$first,$remark,$status,$db,$wx_send_url);
    
    $ret = json_decode(trim($ret,chr(239).chr(187).chr(191)),true);
    if($ret['status']==0){//如果上传成功
      if($ret['wxStatus']==1){//如果发送成功
        $wxstatus = 2;
      }else{
        $wxstatus = 3;
      }
    }else{//如果上传失败
      mysql_query("UPDATE kmsmsend.wxforsend SET flg=10 where msm_sn='$msm_sn'",$db);
      continue;
    }

      
    // whl这个地方要将微信发送状态记录到pool表中。
    $sql = "UPDATE kmsmsend.msmpool set wxsendop=1,wxstatus='$wxstatus' where msm_sn='$msm_sn' ";
    mysql_query($sql,$db);
    
    // 处理限制手机号表limit_phone
    if($ret['isSendMsm']==1){
      $res = mysql_query("SELECT `id` from kmsmsend.msmaccount where wx_send_url='$wx_send_url' limit 1",$db);
      $account_id = mysql_result($res, 0,"id");
      $phone = mysql_query("SELECT * from kmsmsend.limit_phone where account_id='$account_id' and phone='$rcvnumber'",$db);
      if(mysql_num_rows($phone)>0){
        mysql_query("DELETE from kmsmsend.limit_phone where account_id='$account_id' and phone='$rcvnumber'",$db);
      }
    }elseif($ret['isSendMsm']==2){
      $res = mysql_query("SELECT `id` from kmsmsend.msmaccount where wx_send_url='$wx_send_url' limit 1",$db);
      $account_id = mysql_result($res, 0,"id");
      $phone = mysql_query("SELECT * from kmsmsend.limit_phone where account_id='$account_id' and phone='$rcvnumber'",$db);
      if(!(mysql_num_rows($phone)>0)){
        mysql_query("INSERT into kmsmsend.limit_phone(`phone`,`account_id`) values ('$rcvnumber','$account_id')",$db);
      }
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
   $sqlstr="DELETE FROM  kmsmsend.wxforsend  WHERE    flg=5";  
   mysql_query($sqlstr,$db);   
 
 }
/**********************************
 发送微信
***********************************/
function  sendweixin($rcvnumber,$artno,$password,$boxno,$expressno,$first,$remark,$status,$db,$wx_send_url)
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
   // $url="http://weixin.diyibox.com/index.php?s=/Api/send_express_info";
   if($wx_send_url){
      $url = $wx_send_url;
   }else{
      $url="http://weixin.diyibox.com/index.php/Api/express_info_send";
   }
     $ret=post($url,$data);
    // $ret=str_replace(":",",",$ret);
    // $ret=str_replace("\"","",$ret);   
    //   $str=split(",",$ret);  
    // $ret=$str[3]; 
    return  $ret;  //0,没有号码，1正常， 其他不正常

}

function post($url,$data){
        // $post_data_str = '';
        // foreach ($data as $key => $value) {
        //   $post_data_str .= $key.'='.$value.'&';
        // }
        // $data = substr($post_data_str, 0,-1);

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

/**********************************
  改变短信状态
  只在不需要发短信的时候，才将微信的发送结果送入原来短信的发送结果中
***********************************/
function  changesmsstatus($stationid,$msm_sn,$rcvnumber,$db)
{
  $message=0;  //0允许发送短信  1关闭发送短信 
  $result = mysql_query("SELECT smsflg FROM  weixin.rcvnumbersms  where  rcvnumber='$rcvnumber'",$db);  
  $num= mysql_numrows($result);
  if($num>0)
  {
    $message=mysql_result($result,0,"smsflg"); 
  if($message!=1)
  {
     $message=0;
  }  
  }
  
  
  $message=1; //modify by 2016.1.22  发微信的运单的状态全部改变，可能与短信的状态是重复的 
  if($message==1) //如果不允许发短信，需要将微信的发送状态送入短信状态标志
  {
    //智能柜
  mysql_query("UPDATE smartbox.smtbx_order SET `sentmsmflgs`='2',`downflg`=0  where  devicesn=$stationid and  ordersn='$msm_sn'",$db);
  //dyhawk
    mysql_query("UPDATE dyhawk.logistics SET `smstatus`='2'  where   (stationaccount=$stationid  or pdasn=$stationid) and  msm_sn='$msm_sn'",$db);
  }

}


?>