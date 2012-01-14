<?php
header('Content-type: text/html; charset=utf-8');


if(isset($_POST["firstName"]) || isset($_GET["checktor"])){
	$text = array('æ', 'ø', 'å');
	$ascii = array('%E6', '%F8', '%E5');	


	// manually encoding since I can't seem to prepare the content-type properly
	$firstName = str_replace($text, $ascii, strtolower($_POST["firstName"]));
	$lastName = str_replace($text, $ascii, strtolower($_POST["lastName"]));
	$dob = $_POST["dob"];	
	$cpr = $_POST["cpr"];
	
	$cookieFile = tempnam("/tmp", "COOKIE");	
	//$cookieFile = "testcookie";	

	// initiate curl
	$ch = curl_init();
	
	// make sure we are anonymous
	$checktor = curl_wrapper(array(
		"url" => "https://check.torproject.org/",
		"ch" => $ch
	));
	// get tor status message
	$pattern_msg = '/<h1.*?>(.*)<\/h1>/sx';	
	preg_match($pattern_msg, $checktor[0], $msg);	
	
	// get ip address
	$pattern_ip = '/Your IP address appears to be: <b>(.*)<\/b>/s';	
	preg_match($pattern_ip, $checktor[0], $ip);
	if(strpos($msg[1], "Sorry")){
		echo "Error: ";
		echo $msg[1];
		echo $ip[0];		
		exit();
	}elseif(isset($_GET["checktor"])){
		echo "Success: ";
		echo  $msg[1];
		echo $ip[0];
		exit();
	}	
	
	// step1a - get session cookie
	curl_wrapper(array(
		"url" => "http://www.callme.dk/pow-basic/1",
		"cookieFile" => $cookieFile,
		"ch" => $ch,
		"setCookie" => true	
	));

	// Step 1b - complete step 1
	$data1 = "variationId=590&task=submitOrderSubscription&subscriptionId=51";
	curl_wrapper(array(
		"url" => "http://www.callme.dk/pow-basic/1",
		"data" => $data1,
		"cookieFile" => $cookieFile,
		"ch" => $ch
	));

	// Step 2
	$data2 = "task=submitOrderExtras&personlige=Videre%20til%20personlige%20oplysninger";
	curl_wrapper(array(
		"url" => "http://www.callme.dk/pow-basic/2",
		"data" => $data2,
		"cookieFile" => $cookieFile,
		"ch" => $ch,
	));
	
	// Step 3a - get phone number
	while(!isset($phone)){
		$response3 = curl_wrapper(array(
			"url" => "http://www.callme.dk/pow-basic/3",
			"cookieFile" => $cookieFile,
			"ch" => $ch,
		));	
	
		$pattern = '/<label for=\"newPhoneNumber[\d]+\">(\d+)<\/label>/';
		preg_match($pattern, $response3[0], $matches);
		
		if(isset($matches[0])){
			$phone = $matches[0];
		}
	}
	
	// Step 3b - post chosen phone number
	$data3 = "task=submitOrderPhoneNumber&currentCallMeNumber=&currentPhoneNumber=&currentOperator=&currentSIM=&phoneNumberType=newNumber&newPhoneNumber=".$phone."&saldoLimitAmount=";
	curl_wrapper(array(
		"url" => "http://www.callme.dk/pow-basic/3",
		"data" => $data3,
		"cookieFile" => $cookieFile,
		"ch" => $ch,
	));

	$email = $firstName."@".$lastName.".dk";
	$email = ereg_replace("[^a-zA-Z@\.]", "", $email);
	
	// Step 4 - post personal info (name, cpr etc)
	$data4 = "task=submitOrderPersonalInfo&firstName=".$firstName."&lastName=".$lastName."&email=".$email."&emailConfirm=".$email."&CPR1=".$dob."&CPR2=".$cpr."&housing=Ejerbolig&occupation=Fuldtidsansat&civilStatus=Gift&memberClub=&memberNumber=&termsAccepted=1&bankRegNumber=&bankAccountNumber=&subscriptionPaymentMethod=noPaymentService&personlige=Videre%20til%20bestilling";

	$response4 = curl_wrapper(array(
		"url" => "https://www.callme.dk/pow-basic/4",
		"data" => $data4,
		"cookieFile" => $cookieFile,
		"ch" => $ch,
	));
	
	// Get response - does the cpr number exist or not?
	if($response4[1]["http_code"] == 302){
		echo"success: ". $cpr;
	}else{
		$pattern = '/<div class="error">(.+)<\/div>/s';
		preg_match($pattern, $response4[0], $matches);
		$pos = strpos($matches[1], "<table");
		$error = substr($matches[1], 0, $pos);
		echo trim($error);
	}
}

/***************************
* wrapper function for cURL requests
***************************/	
function curl_wrapper($options = array()){	
	$ch = $options["ch"];
	
	
	// init and set cookies
	if(isset($options["setCookie"])){
		curl_setopt ($ch, CURLOPT_COOKIEJAR, $options["cookieFile"]);
	}
	
	//curl_setopt ($ch, CURLOPT_COOKIEFILE, $options["cookieFile"]);	
	
	// set URL
	curl_setopt($ch, CURLOPT_URL, $options["url"]);
	
	// return instead of echo result
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);		
	
	// set request method
	if(isset($options["data"])){
		curl_setopt($ch, CURLOPT_POST, true);						
		curl_setopt($ch, CURLOPT_POSTFIELDS, $options["data"]);
		$headers = array('Content-type: application/x-www-form-urlencoded;charset=utf-8'); 
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	}
	
	// set tor proxy
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt ($ch, CURLOPT_REFERER, "http://www.google.com/");  
	curl_setopt ($ch, CURLOPT_PROXY, "http://127.0.0.1:9050/");
	curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
	
	// return response	
	$response = curl_exec($ch);
	$info = curl_getinfo($ch);
	return array($response, $info, @$options["cookieFile"]);
}

// debug outputting
function debug($output){
	$debug = "<pre>";
	$debug .= print_r($output, true);	
	$debug .= "</pre>";	

	echo $debug; 
}

?>
