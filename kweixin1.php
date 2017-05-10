<?php
set_time_limit(90);
include_once("../dataserve/conn_mysql.php");
header("Content-type:text/html;charset=utf-8");
$db=conn();

ksendwxnotice($db);

kwxforsendnew($db);

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
function  ksendwxnotice($db)
{ 

   $limit_n=100;  //20
   //1)标记出要操作的记录
   // mysql_query("UPDATE kmsmsend.wxforsend SET flg=0",$db);
   mysql_query("UPDATE kmsmsend.wxforsend SET flg=5  order by id asc  LIMIT $limit_n",$db); 
  //2)对标记出的记录发送微信通知
  $result = mysql_query("SELECT * FROM  kmsmsend.wxforsend  where  flg=5",$db); 
  $num= mysql_numrows ($result);
  for($i=0;$i<$num;$i++)
  {  
      $rcvnumber=mysql_result($result,$i,"rcvnumber"); //手机号码

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

    // 2017.2.14新增md5字段
    switch ($wx_send_url) {
      case 'http://weixin.diyibox.com/index.php/Api/express_info_send':
        $wxpassword='123456';
        $md5 = md5($expressno.$wxpassword);
        $ret=ksendweixin2($rcvnumber,$artno,$password,$boxno,$expressno,$first,$remark,$status,$md5,$db,$wx_send_url,$msm_sn);
        if(!$ret){
          // 记录日志
        }
        continue 2;
        break;
      case 'http://site.100mzhan.com/bmz/openapi/diyi/?method=bmz.wechat.send':
        $wxpassword='bmz123';
        break;
      default:
        $wxpassword='123456';
        break;
    }
    $md5 = md5($expressno.$wxpassword);

    $ret=ksendweixin($rcvnumber,$artno,$password,$boxno,$expressno,$first,$remark,$status,$md5,$db,$wx_send_url);
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
    //   $res = mysql_query("SELECT `id` from kmsmsend.msmaccount where wx_send_url='$wx_send_url' limit 1",$db);
    //   $account_id = mysql_result($res, 0,"id");
    //   $phone = mysql_query("SELECT * from kmsmsend.limit_phone where account_id='$account_id' and phone='$rcvnumber'",$db);
    //   if(mysql_num_rows($phone)>0){
    //     mysql_query("DELETE from kmsmsend.limit_phone where account_id='$account_id' and phone='$rcvnumber'",$db);
    //   }
    // }elseif($ret['isSendMsm']==2){
    //   $res = mysql_query("SELECT `id` from kmsmsend.msmaccount where wx_send_url='$wx_send_url' limit 1",$db);
    //   $account_id = mysql_result($res, 0,"id");
    //   $phone = mysql_query("SELECT * from kmsmsend.limit_phone where account_id='$account_id' and phone='$rcvnumber'",$db);
    //   if(!(mysql_num_rows($phone)>0)){
    //     mysql_query("INSERT into kmsmsend.limit_phone(`phone`,`account_id`) values ('$rcvnumber','$account_id')",$db);
    //   }
    }
    
    }

   }
   //3)删除已经标出的记录
   $sqlstr="DELETE FROM  kmsmsend.wxforsend  WHERE    flg=5";  
   mysql_query($sqlstr,$db);   
 
 }
/**********************************
 发送微信
***********************************/
function  ksendweixin($rcvnumber,$artno,$password,$boxno,$expressno,$first,$remark,$status,$md5,$db,$wx_send_url)
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
   $data["md5"] = $md5;     
  // $url="http://weixin.diyibox.com/index.php/Api/send_express_info";
   // $url="http://weixin.diyibox.com/index.php?s=/Api/send_express_info";
   if($wx_send_url){
      $url = $wx_send_url;
   }else{
      $url="http://weixin.diyibox.com/index.php/Api/express_info_send";
   }
   
     $ret=kpost($url,$data);
    // $ret=str_replace(":",",",$ret);
    // $ret=str_replace("\"","",$ret);   
    //   $str=split(",",$ret);  
    // $ret=$str[3]; 
    return  $ret;  //0,没有号码，1正常， 其他不正常

}

/**********************************
 2017.05.09 批量微信推送异步
***********************************/
function  ksendweixin2($rcvnumber,$artno,$password,$boxno,$expressno,$first,$remark,$status,$md5,$db,$wx_send_url,$msm_sn)
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
   $data["msm_sn"]=$msm_sn;  
   $data["md5"] = $md5;     
   $data["wx_send_url"] = $wx_send_url; 

   $result = mysql_query("INSERT into kmsmsend.wxforsendnew (`mobile`,`artno`,`password`,`container`,`number`,`first`,`remark`,`status`,`md5`,`exname`,`wx_send_url`,`msm_sn`)values('$rcvnumber','$artno','$password','$boxno','$expressno','$first','$remark','$status','$md5','$expressname','$wx_send_url','$msm_sn')",$db); 

    return  $result;

}

function kwxforsendnew($db){
    $limit_n=100;  //20

    mysql_query("UPDATE kmsmsend.wxforsendnew SET flg=5  order by id asc  LIMIT $limit_n",$db); 
    //2)对标记出的记录发送微信通知
    $result = mysql_query("SELECT * FROM  kmsmsend.wxforsendnew  where  flg=5",$db); 
    $num= mysql_numrows ($result);
    //echo "num=$num";
    
    $data=array();

    for($i=0;$i<$num;$i++)
    {  

      $arr["first"]=mysql_result($result,$i,"first");
      $arr["remark"]=mysql_result($result,$i,"remark");
      $arr["number"]=mysql_result($result,$i,"number");
      $arr["exname"]=mysql_result($result,$i,"exname");
      $arr["mobile"]=mysql_result($result,$i,"mobile");
      $arr["artno"]=mysql_result($result,$i,"artno");
      $arr["password"]=mysql_result($result,$i,"password");
      $arr["container"]=mysql_result($result,$i,"container");
      $arr["status"]=mysql_result($result,$i,"status"); 
      $arr["msm_sn"]=mysql_result($result,$i,"msm_sn");
      $arr["md5"] = mysql_result($result,$i,"md5");  
      
      array_push($data,$arr);
      $msm_sn = $arr['msm_sn'];
      // whl这个地方要将微信发送状态记录到pool表中。
      $sql = "UPDATE kmsmsend.msmpool set wxsendop=1 where msm_sn='$msm_sn' ";
      mysql_query($sql,$db);
     }
    
     // $url = 'http://weixin.diyibox.com/index.php/Api/express_info_send';
     $url = 'http://wxtest.diyibox.com/index.php/Smstemplate/express_insert_info';
     $data1 = array();
     $data1['data'] = json_encode($data);
     
     $ret = kpost($url,$data1);
     $ret = json_decode($ret,true);

     if($ret['status']==1){//如果上传成功
       //删除已经标出的记录
       $sqlstr="DELETE FROM  kmsmsend.wxforsendnew  WHERE    flg=5";  
       mysql_query($sqlstr,$db); 
     }else{//如果上传失败
       mysql_query("UPDATE kmsmsend.wxforsend SET flg=10 where flg=5 ",$db);
     }

}

function kpost($url,$data){
        $post_data_str = '';
        foreach ($data as $key => $value) {
          $post_data_str .= $key.'='.$value.'&';
        }
        $post_data = substr($post_data_str, 0,-1);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,FALSE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,FALSE);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        $data = curl_exec($ch);

        // whl如果相应时间超过1s则记录
        $httptime = curl_getinfo($ch,CURLINFO_TOTAL_TIME);
        if($httptime>1){
          $logcon = date('Y-m-d H:i:s').' '.$httptime."\r\n";
          file_put_contents('./msg.log', $logcon,FILE_APPEND);
        }
        if(curl_errno($ch))
        {
            $con = 'weixin Curl error: ' . curl_errno($ch).' '.curl_error($ch);
            $logcon = date('Y-m-d H:i:s').' '.$con."\r\n";
            file_put_contents('./msg.log', $logcon,FILE_APPEND);
        }

        curl_close($ch);
        return $data;    
 }

/**********************************
  改变短信状态
  只在不需要发短信的时候，才将微信的发送结果送入原来短信的发送结果中
***********************************/
function  kchangesmsstatus($stationid,$msm_sn,$rcvnumber,$db)
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