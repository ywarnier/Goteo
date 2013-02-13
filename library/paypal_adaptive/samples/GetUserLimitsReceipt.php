<?php
$path = '../lib';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('services/AdaptivePayments/AdaptivePaymentsService.php');
require_once('PPLoggingManager.php');

$logger = new PPLoggingManager('GetUserLimits');

// create request
$requestEnvelope = new RequestEnvelope("en_US");
$getUserLimitsReq = new GetUserLimitsRequest($requestEnvelope);
$getUserLimitsReq->country = $_POST['country'];
$getUserLimitsReq->currencyCode = $_POST['currencyCode'];
if( $_POST['email'] != "" || ($_POST['phoneCountry'] != "" && $_POST['phoneNumber'] != "")) {
	$getUserLimitsReq->user = new AccountIdentifier();
	if($_POST['email'] != "") {
		$getUserLimitsReq->user->email =  $_POST['email'];
	}
	if($_POST['phoneCountry'] != "" && $_POST['phoneNumber'] != "") {
		$getUserLimitsReq->user->phone = new PhoneNumberType($_POST['phoneCountry'], $_POST['phoneNumber']);
		if($_POST['phoneExtension'] != "") {
			$getUserLimitsReq->user->phone->extension = $_POST['phoneExtension'];
		}
	}
}
$logger->log("Created GetUserLimitsRequest Object");
$service = new AdaptivePaymentsService();
try {
	$response = $service->GetUserLimits($getUserLimitsReq);
} catch(Exception $ex) {
	require_once 'Common/Error.php';
	exit;
}
$logger->error("Received GetUserLimitsResponse:");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<title>PayPal Adaptive Payments - Get User Limits</title>
<link href="Common/sdk.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="Common/sdk_functions.js"></script>
</head>

<body>
	<div id="wrapper">
		<div id="response_form">
			<h3>Get User Limits</h3>
<?php
$ack = strtoupper($response->responseEnvelope->ack);
if($ack != "SUCCESS"){
	echo "<b>Error </b>";
	echo "<pre>";
	print_r($response);
	echo "</pre>";
} else {
	echo "<pre>";
	print_r($response);
	echo "</pre>";
}
require_once 'Common/Response.php';		
?>
		</div>
	</div>
</body>
</html>