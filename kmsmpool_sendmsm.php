<?php
/*
 关于短信发送
*/
include_once ("../dataserve/conn_mysql.php");

kmsmpool_sendmsm();

/********************************************
函数： 将msmpool中的已经获取过状态的短信进行处理
      1）先将要处理的短信做个标记
    2）将标记出的记录中的属于智能柜的短信的状态同步复制到smtbx_order中。
    3）拷贝已经标记出的记录到sendmsm表中
    4）删除已经标记出的记录
    5）每次标记的记录最大数有限制
********************************************/
function kmsmpool_sendmsm() {
    $db = conn();
    $db1 = conn7();
    $db2 = conn2();
    $db4 = conn4();
    $limit_n = 100;
    //1)标记出要操作的记录
    mysql_query("UPDATE `msmpool` SET send_n=5  where  status>1 or wxstatus>1 and send_n=0 order by id asc  LIMIT $limit_n", $db1); //100

    $result = mysql_query("SELECT stationid,devicesn,msm_sn,status,wxstatus,smssendflg,wxsendflg  FROM  msmpool   where  send_n=5    order by id asc", $db1); // and  stationid>100000
    $num = mysql_num_rows($result);
    if ($num > $limit_n) //防止在特殊情况下，=5的太多，使得该页执行时间大于30s
    {
        mysql_query("UPDATE `msmpool` SET `send_n` = '0' where send_n=5", $db1);
        return;
    }
    for ($i = 0;$i < $num;$i++) {
        $msm_sn = mysql_result($result, $i, "msm_sn");
        $devicesn = mysql_result($result, $i, "devicesn");
        $status = mysql_result($result, $i, "status");
        $wxstatus = mysql_result($result, $i, "wxstatus");
        $stationid = mysql_result($result, $i, "stationid");
        $stationid = str_pad($stationid, 12, "0", STR_PAD_LEFT); //对于不足12位的前边补零
        $smssendflg = mysql_result($result, $i, "smssendflg");
        $wxsendflg = mysql_result($result, $i, "wxsendflg");

        if($smssendflg==1&&$wxsendflg==1){
            // 微信短信都发
            if($status==2 || $wxstatus==2){//如果短信或微信有一个发送成功，则同步状态为成功
                tongbu($msm_sn,$devicesn,'2',$stationid,$db2,$db4,$db1); 
            }elseif($status==3 && $wxstatus==3){//如果短信和微信都发送失败，则同步状态为失败
                tongbu($msm_sn,$devicesn,'3',$stationid,$db2,$db4,$db1); 
            }
            
        }elseif($smssendflg==1&&$wxsendflg==0){
            // 只发短信
            if($status>1){
                // 智能柜和pad状态同步
                tongbu($msm_sn,$devicesn,$status,$stationid,$db2,$db4,$db1); 
            }
        }elseif($smssendflg==0&&$wxsendflg==1){
            // 只发微信
            if($wxstatus>1){
                // 智能柜和pad状态同步
                tongbu($msm_sn,$devicesn,$wxstatus,$stationid,$db2,$db4,$db1); 
            }
        }
    }

    // 将同步过的记录转移到sendmsm表中
    $result = mysql_query("SELECT stationid,devicesn,msm_sn,status,wxstatus,smssendflg,wxsendflg  FROM  msmpool   where  send_n=6    order by id desc LIMIT $limit_n", $db1); // and  stationid>100000
    $num = mysql_num_rows($result);
    
    for ($i = 0;$i < $num;$i++) {
        $msm_sn = mysql_result($result, $i, "msm_sn");
        $devicesn = mysql_result($result, $i, "devicesn");
        $status = mysql_result($result, $i, "status");
        $wxstatus = mysql_result($result, $i, "wxstatus");
        $stationid = mysql_result($result, $i, "stationid");
        $stationid = str_pad($stationid, 12, "0", STR_PAD_LEFT); //对于不足12位的前边补零
        $smssendflg = mysql_result($result, $i, "smssendflg");
        $wxsendflg = mysql_result($result, $i, "wxsendflg");

        if($smssendflg==1&&$wxsendflg==1){
            // 如果短信和微信状态都回来了，才拷贝到sendmsm表
            if($status>1 && $wxstatus>1){
                 mysql_query("UPDATE `msmpool` SET `send_n` = '7' where msm_sn='$msm_sn'", $db1);
            }
            
        }elseif($smssendflg==1&&$wxsendflg==0){
            // 只发短信
            if($status>1){
                // 智能柜和pad状态同步
                mysql_query("UPDATE `msmpool` SET `send_n` = '7' where msm_sn='$msm_sn'", $db1);
            }
        }elseif($smssendflg==0&&$wxsendflg==1){
            // 只发微信
            if($wxstatus>1){
                // 智能柜和pad状态同步
                mysql_query("UPDATE `msmpool` SET `send_n` = '7' where msm_sn='$msm_sn'", $db1);
            }
        }
    }

    tosendmsm($db); 
}
// 智能柜和pad状态同步
function tongbu($msm_sn,$devicesn,$status,$stationid,$db2,$db4,$db1) {
  // 如果是用智能柜发送的短信
  if($devicesn){
    $result1 = mysql_query("SELECT id,sentmsmflgs FROM  smtbx_order  where  devicesn=$devicesn and  ordersn='$msm_sn'", $db2);
    $num1 = mysql_numrows($result1);
    if ($num1 > 0) {
        $id = mysql_result($result1, 0, "id");
        $sentmsmflgs = mysql_result($result1, 0, "sentmsmflgs");
        if($sentmsmflgs!=2){
           $sqlstr = "UPDATE `smtbx_order` SET `sentmsmflgs`='$status',`downflg`=0  where  id='$id'";
           $r = mysql_query($sqlstr, $db2); 
        }
    }
  }
  
  // 2)对物流短信状态的反馈，其中还有智能柜的短信的状态，
    $result1 = mysql_query("SELECT id FROM  logistics  where  (stationaccount=$stationid  or pdasn=$stationid) and  msm_sn='$msm_sn'", $db4);
    $num1 = mysql_numrows($result1);
    if ($num1 > 0) {
        $id = mysql_result($result1, 0, "id");
        $sqlstr = "UPDATE `logistics` SET `smstatus`='$status'  where  id='$id'";
        mysql_query($sqlstr, $db4);
    }
  mysql_query("UPDATE `msmpool` SET `send_n` = '6' where msm_sn='$msm_sn'", $db1);

}
// 状态完成的短信迁移
function tosendmsm($db){
    mysql_query("SET AUTOCOMMIT=0"); //开始事务处理
     $sqlstr = "
    INSERT INTO ksendmsm ( `username` ,`rcvnumber` ,`sendtime` ,`huohao` ,`stationid` ,`frequency` ,`status` ,`content`,`m_operator`,`msm_sn`,`companyflg`,`devicesn`,`wxcontent`,`smsfree`,`smssendop`,`smssendflg`,`wxsendop`,`wxsendflg`,`wxstatus`,`boxid`,`password`,`expressno`,`mode`,`manager` )   SELECT   `username` ,`rcvnumber` ,`sendtime` ,`huohao` ,`stationid` ,`frequency` ,`status` ,`content`,`m_operator`,`msm_sn` ,`companyflg`,`devicesn`,`wxcontent`,`smsfree`,`smssendop`,`smssendflg`,`wxsendop`,`wxsendflg`,`wxstatus`,`boxid`,`password`,`expressno`,`mode`,`manager`  FROM   kmsmsend.msmpool    WHERE    send_n=7";
     $result1 = mysql_query($sqlstr, $db);
     //删除已经标出的记录
     $sqlstr = "DELETE FROM  kmsmsend.msmpool  WHERE    send_n=7";
     $result2 = mysql_query($sqlstr, $db);
     if ($result1 && $result2) {
         mysql_query("COMMIT"); //全部成功，提交执行结果
         //echo '提交';
         
     } else {
         mysql_query("ROLLBACK"); //有任何错误发生，回滚并取消执行结果
         //echo '回滚';
         
     }
}
?>