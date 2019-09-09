<?php
	define('URL1', 'https://api.chasedatacorp.com/HttpImport/LeadOperations.php');
	define('URL2', 'https://www.chasedatacorp.com/HttpImport/UpdateLead.php');
	
	function sendRequest($url, $data){
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		$res = curl_exec($ch);
		curl_close($ch);
		return $res;
	}

	function getValue($fields, $name){
		if (!isset($fields[$name])){
			echo $name . " is required!";
			exit();
		}
		return $fields[$name];
	}

	if ($_SERVER['REQUEST_METHOD']=='GET'){
		$SecurityCode = getValue($_GET, 'SecurityCode');
		$Action = getValue($_GET, 'Action');
		$LeadId = getValue($_GET, 'LeadId');
		$Disposition = getValue($_GET, 'Disposition');


		// send to URL1
		$res1 = sendRequest(URL1, array(
			"SecurityCode" => $SecurityCode, 
			"Action"	   => $Action,
			"Disposition"  => $Disposition,
			"LeadId"	   => $LeadId,	 
		));
		echo $res1;

		// send to URL2
		$res2 = sendRequest(URL2, array(
			"SecurityCode" => $SecurityCode,
			"GroupId"	   => "224485",
			"SearchField"  => "LeadId",
			"Identifier"   => $LeadId,
			"CallStatus"   => $Disposition		
		));
		echo $res2;

	}else{
		http_response_code(405);
		die();
	}
?>