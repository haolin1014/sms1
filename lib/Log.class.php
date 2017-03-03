<?php
class Log{
	public static function info($con,$path='./log.log'){
		$con = date('Y-m-d H:i:s').'-'.$con;
		$con = $con."\r\n";
		$res = file_put_contents($path, $con,FILE_APPEND);
		return $res;
	}
}