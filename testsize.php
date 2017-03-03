<?php
include_once("../dataserve/conn_mysql.php");
header("Content-type:text/html;charset=utf-8");
$db = conn();
// $devicesn = '201900000054';
// $boxId = 1;
$devicesn = $_GET['devicesn'];
$boxId = $_GET['boxId'];
if($devicesn&&$boxId){
	echo "格口类型:";
	echo getBoxSize($devicesn,$boxId,$db);
}else{
	echo '设备号和格口号不能为空';
}

function getBoxSize($devicesn,$boxId,$db){
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
	        $boxNum = $boxSet['BoxNo'];//格口数
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
?>