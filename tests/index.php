<?php


require "../src/SHttpClient.php";

$http = new SHttpClient();

/*=======================*/
/*= HTTPS GET request 	=*/
/*= Content type JSON   =*/
/*=======================*/
$http->openSSL("https://<somedomain>", "<Certificate file or URL>");
$http->setHeader("Accept", "application/json");
$resp = $http->GET();

/*=======================*/
/*= HTTPS POST request  =*/
/*= Content type JSON   =*/
/*=======================*/
$http->openSSL("https://<somedomain>", "<Certificate file or URL>");
$http->setHeader("Accept", "application/json");
$http->setHeader("Content-type", "application/json");
$body = json_encode(
			array(
				"email" => "test@subject.com",
				"alias" => "test"
			)
		);
$resp = $http->POST($body);

/*====================================*/
/*= GET request                      =*/
/*====================================*/
$http->open("http://<somedomain>");
$http->setVerbose(true);
$resp = $http->GET();

/*====================================*/
/*= POST request                     =*/
/*= Content type multipart/form-data =*/
/*====================================*/
$http->open("http://<somedomain>");
$http->setVerbose(true);

$message = new MultipartMessage();
$message->addPart(new MultipartField("param1", 1));
$message->addPart(new MultipartField("param2", "two"));
$message->addPart(new MultipartFile("file", "<path to file>"));

$resp = $http->POST($message);
?>