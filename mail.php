#!/usr/bin/env php
<?php

$includePrefix = __DIR__."/";
$defaultConfigFilename = ".phpmailer.json";

require $includePrefix.'class.phpmailer.php';
require $includePrefix.'class.smtp.php';

$from=get_current_user()."@".gethostname();
$body= "See attachment(s).";
$attachs = false;
$debugLevel=0;

$optionsDefinitions = array();
$optionsDefinitions["h"] = "help";
$optionsDefinitions["a:"] = "attachement: filepath of an attachment to add, you can add multiple -a options to add many files";
$optionsDefinitions["b:"] = "body: by default it's \"".$body."\"";
$optionsDefinitions["d:"] = "debug level: 2 would be an interesting value";
$optionsDefinitions["f:"] = "from: by default it's ".$from;
$optionsDefinitions["u:"] = "user: MANDATORY smtp username";
$optionsDefinitions["s:"] = "subject: MANDATORY";
$optionsDefinitions["c:"] = "phpmailer configuration file: OPTIONAL, default: ".getHome()."/".$defaultConfigFilename;

$options = getopt(implode("", array_keys($optionsDefinitions)));

$configuration = array();
$configLocationToCheck = isset($options['c']) ? $options['c'] : null;

if($configLocationToCheck){
    echo "\nTrying to use config from $configLocationToCheck";
}

$configFileLocation = locateConfigFile( $configLocationToCheck , $defaultConfigFilename);

if($configFileLocation){
    echo "\nReading configuration from $configFileLocation";
    $configuration = readConfiguration($configFileLocation);
}

$preOptions = array(
    "f" => $configuration['from'],
    "u" => $configuration['Username'],
    "d" => $configuration['SMTPDebug'],
);

$options = array_merge($preOptions, $options);

//	var_dump($options); die();
foreach($options as $key=>$value) {
	switch($key) {
		case "a":
			$attachs=$value;
		break;

		case "b":
			$body=$value;
		break;

		case "d":
			$debugLevel=$value;
		break;

		case "f":
			$from=$value;
		break;

		case "h":
			help();
		break;

		case "s":
			$subject=$value;
		break;

		case "u":
			$user=$value;
		break;
		
	}
}

if(count($options) == 0) {

	echo "Invalid option or no option given (-s is mandatory)\n";
	help();
	die();
}

if (! isset($options["s"])) {

	die("Option -s is mandatory");
}

if (! isset($options["u"])) {

	die("Option -u is mandatory");
}

$desti = $argv[count($argv)-1];
if(preg_match("/^-/", $argv[count($argv)-2])) {

	die("\nLast argument must be recipient email address, not an option");
}

$pwd = $configuration['Password'];
if(!$pwd){

    echo "\nPassword for user ".$user.": ";

    $handle = fopen ("php://stdin","r");
    $pwd = fgets($handle);
    if(trim($pwd) == ''){
        echo "no password, no job, bye...\n";
    }
    fclose($handle);
}

//include("class.smtp.php"); // optional, gets called from within class.phpmailer.php if not already loaded

$mail             = new PHPMailer();
$mail->CharSet = 'UTF-8';
$mail->SMTPDebug  =  $configuration['SMTPDebug'];           // enables SMTP debug information (for testing)
                                           // 1 = errors and messages
                                           // 2 = messages only
if($configuration['IsSMTP']){
    $mail->IsSMTP(); // telling the class to use SMTP
}
$mail->SMTPAuth   = $configuration['SMTPAuth'];                  
$mail->SMTPSecure = $configuration['SMTPSecure'];                 
$mail->Host       = $configuration['Host'];                     // sets GMAIL as the SMTP server
$mail->Port       = $configuration['Port'];                   // set the SMTP port for the GMAIL server
$mail->Username   = $user;  // GMAIL username
$mail->Password   = $pwd;            // GMAIL password

$mail->SetFrom($from);

$mail->AddReplyTo($from);

$mail->Subject    = $subject;

$mail->AltBody    = "To view the message, please use an HTML compatible email viewer!"; // optional, comment out and test

$mail->MsgHTML($body);

$mail->AddAddress($desti);

if($attachs) {
	if(is_array($attachs)) {
		foreach ($attachs as $attach) {
			$mail->AddAttachment(realpath($attachs));
		}
	} else {
		$mail->AddAttachment(realpath($attachs));
	}
}


if(!$mail->Send()) {
  echo "\nMailer Error: " . $mail->ErrorInfo;
  exit(1);
} else {
  echo "\nMessage sent!";
  exit(0);
}
    

function help() 
{

global $optionsDefinitions;
    echo "\n".'Usage ./mail.phar -u "smtpUser" -s "An amazing subject" recipient@domain.tld'. "\n";
 	foreach($optionsDefinitions as $option => $comment) {
 		$option = str_replace(":", "", $option);
 		printf("-%s %s\n", $option, $comment);

 	}
}

function getHome() 
{
  $home = getenv('HOME');
  if (!empty($home)) {
    $home = rtrim($home, '/');
  }
  elseif (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
    $home = $_SERVER['HOMEDRIVE'] . $_SERVER['HOMEPATH'];
    $home = rtrim($home, '\\/');
  }
  return empty($home) ? NULL : $home;
}

function locateConfigFile($locationToCheck = null, $defaultConfigFilename = ".phpmailer.json")
{
    $defaultConfigLocation = getHome()."/".$defaultConfigFilename;
    
    if($locationToCheck && is_readable($locationToCheck)){
        return $locationToCheck;
    }elseif(is_readable( $defaultConfigLocation )){
        return  $defaultConfigLocation;
    }
    
    return null;
}

function readConfiguration($configFile)
{
    $configurationDefault = array(
        "SMTPDebug" => 0,
        "IsSMTP" => true,
        "SMTPAuth" => true,
        "SMTPSecure" => "tls",
        "Host" => "smtp.gmail.com",
        "Port" => 587,
        "Username" => "",
        "Password" => "",
        "from" => ""
    );
    $content = file_get_contents($configFile);
    $configuration = json_decode($content, true);
    if((json_last_error() === JSON_ERROR_NONE)){
        return array_merge($configurationDefault , $configuration);
    }else{
        die("$configFile - JSON Error");
    }
    return $configurationDefault;
}
