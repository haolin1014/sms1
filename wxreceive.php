<?php
/*
2017.05.08 接收微信推送结果
 */
include_once("../dataserve/conn_mysql.php");
$db = conn();

// 合作方密码
$checkData = array(
   'diyi'=>'123456',
   // 'baimizhan'=>'bmz123'
);

file_put_contents('./wxreceive.log', $_POST['data']."\r\n",FILE_APPEND);

$data = base64_decode($_POST['data']);
$password = $_POST['password'];
$partnerId = $_POST['partnerId'];

// 判断是否正常接收数据
if(empty($password)||empty($data)||empty($partnerId)){
   $arr = array(
      'status'=>1,
      'msg'=>'data is empty'
      );
   echo json_encode($arr);
   exit();
}

$local_md5 = md5($data.$checkData[$partnerId]);

file_put_contents('./wxreceive.log', $data.'-'.$local_md5.'-'.$password."\r\n",FILE_APPEND);

// 验证签名
if($password!=$local_md5)
{
   $arr = array(
      'status'=>2,
      'msg'=>'sign is wrong'
      );
   echo json_encode($arr);
   exit();
}

$data = json_decode($data,true);

if(is_array($data)){
	foreach ($data as $k => $v) {
		$msm_sn = $v['msm_sn'];
		$wxStatus = $v['wxStatus'];
		$isSendMsm = $v['isSendMsm'];
		$rcvnumber = $v['rcvnumber']; 

	    if($wxStatus==1){//如果发送成功
	      $wxstatus = 2;
	    }else{
	      $wxstatus = 3;
	    }
		 
		// whl这个地方要将微信发送状态记录到pool表中。
		$sql = "UPDATE kmsmsend.msmpool set wxstatus='$wxstatus' where msm_sn='$msm_sn' ";
		mysql_query($sql,$db);
		
		// 处理限制手机号表limit_phone
		if($isSendMsm==1){
		  $res = mysql_query("SELECT `id` from kmsmsend.msmaccount where manager='$partnerId' limit 1",$db);
		  $account_id = mysql_result($res, 0,"id");
		  $phone = mysql_query("SELECT * from kmsmsend.limit_phone where account_id='$account_id' and phone='$rcvnumber'",$db);
		  if(mysql_num_rows($phone)>0){
		    mysql_query("DELETE from kmsmsend.limit_phone where account_id='$account_id' and phone='$rcvnumber'",$db);
		  }
		}elseif($isSendMsm==2){
		  $res = mysql_query("SELECT `id` from kmsmsend.msmaccount where manager='$partnerId' limit 1",$db);
		  $account_id = mysql_result($res, 0,"id");
		  $phone = mysql_query("SELECT * from kmsmsend.limit_phone where account_id='$account_id' and phone='$rcvnumber'",$db);
		  if(!(mysql_num_rows($phone)>0)){
		    mysql_query("INSERT into kmsmsend.limit_phone(`phone`,`account_id`) values ('$rcvnumber','$account_id')",$db);
		  }
		}

	}
}else{
	$arr = array(
	   'status'=>3,
	   'msg'=>'data is wrong'
	   );
	echo json_encode($arr);
	exit();
}


?>