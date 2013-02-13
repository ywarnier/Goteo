<?php
$path = '../lib';
set_include_path(get_include_path() . PATH_SEPARATOR . $path);
require_once('services/AdaptivePayments/AdaptivePaymentsService.php');
require_once('PPLoggingManager.php');

$logger = new PPLoggingManager('GetShippingAddresses');

// create request
$getShippingAddressesReq = new GetShippingAddressesRequest(new RequestEnvelope("en_US"), $_POST['key']);
$logger->log("Created GetShippingAddressesRequest Object");
$service  = new AdaptivePaymentsService();
try {
	$response = $service->GetShippingAddresses($getShippingAddressesReq);
} catch(Exception $ex) {
	require_once 'Comon/Error.php';
	exit;
}
$logger->error("Received GetShippingAddressesResponse:");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<title>PayPal Adaptive Payments - Get Shipping Addresses</title>
<link href="Common/sdk.css" rel="stylesheet" type="text/css" />
<script type="text/javascript" src="Common/sdk_functions.js"></script>
</head>

<body>
	<div id="wrapper">
		<div id="response_form">
			<h3>Get Shipping Addresses</h3>
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