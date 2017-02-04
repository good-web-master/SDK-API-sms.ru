<?php
require (__DIR__ . '/SMS.php');
$sms = new SMS();

$data = array(
	'to' => '+71112223344',
	'text' => 'Привет!'
);

if (!$sms->send($data)) {
	print_r($sms->getLastError());
} else {
	echo 'good';
}
