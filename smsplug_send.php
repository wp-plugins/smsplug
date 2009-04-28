<?php
	
	require_once("SMS.inc");
	require_once("../../../wp-config.php");
	require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	
	
	$iUserId = $_POST["smsplug_userid"];
	$iNumber = $_POST["smsplug_natel"];
	$sMessage = $_POST["smsplug_msg"];
	
	
	$tables = $wpdb->get_results('select * from '.SMSPLUG_TABLEPREFIX . SMSPLUG_TABLE.' where smsplug_author="'.$iUserId.'" and DATE_FORMAT(smsplug_dateadded, "%Y%m") = DATE_FORMAT(now(), "%Y%m");', ARRAY_N);
	
	$oSms = new SMS(SMSPLUG_USERNAME, SMSPLUG_PASSWORD);
	$oSms->setOriginator(SMSPLUG_ORIGINATOR);
	$oSms->addRecipient($_POST["smsplug_natel"]);
	$oSms->setContent($_POST["smsplug_msg"]);
	
	if(SMSPLUG_ONLYREGISTEREDUSERS == 1) {
		if(count($tables) <= SMSPLUG_AMOUNT) {
			//Create new instance;
		
			$result = $oSms->sendSMS();
		    if ($result != 1) {
		      $return = $oSms->getErrorDescription();
		    } else {
			    //store data;
			    $sql = "insert into " . SMSPLUG_TABLEPREFIX . SMSPLUG_TABLE ." (smsplug_author, smsplug_number, smsplug_msg, smsplug_dateadded) values ('". $iUserId ."', '" . $iNumber . "', '" . $sMessage . "', now() )";
				$wpdb->query($sql);
			    $return = __('SMS ist Gesendet', 'smsblaster');
			}
		} else {
			$return = "No more SMS";
		}
	} else {
		$result = $oSms->sendSMS();

		if ($result != 1) {
			$return = $oSms->getErrorDescription();
		} else {
			//store data;
			$sql = "insert into " . SMSPLUG_TABLEPREFIX . SMSPLUG_TABLE ." (smsplug_author, smsplug_number, smsplug_msg, smsplug_dateadded) values ('". $iUserId ."', '" . $iNumber . "', '" . $sMessage . "', now() )";
			$wpdb->query($sql);
			$return = __('SMS ist Gesendet', 'smsblaster');
		}
	}
	
	echo $return;
?>