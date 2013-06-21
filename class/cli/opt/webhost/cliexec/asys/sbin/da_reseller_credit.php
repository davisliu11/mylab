#!/usr/bin/php
<?php
include_once "/opt/webhost/cliexec/asys/lib/asys.lib.php";
include_once "/opt/webhost/cliexec/asys/slib/asys.slib.php";

// 	=======================================================================
// 	= Arg list:  list of arguments to pass to checkArgs()                 =
// 	=            argList[$position] = array($name, $regex, $required);    =
// 	=======================================================================

DEFINE("CLIEXEC_DESC",	"Credit a reseller account");

// 	=======================================================================

while (!isset($username) || $username=="") {
	// \n required as stdout gets nothing on the screen otherwise :-/
	echo "Enter reseller's username: \n";
	$stdin_fp = fopen("php://stdin","r");
	$username = trim(fgets($stdin_fp));
	if (!preg_match(REG_USERNAME,$username)) {
		echo "Error: Invalid format for username\n";
		unset($username);
	}
	fclose($stdin_fp);
}

while (!isset($amount) || $amount=="") {
	echo "Enter credit amount: \n";
	$stdin_fp = fopen("php://stdin","r");
	$amount = trim(fgets($stdin_fp));
	if (!preg_match("/^[0-9]+(\.[0-9]+)?$/",$amount)) {
		echo "Error: Invalid format for amount\n";
		unset($amount);
	}
	fclose($stdin_fp);
}

while (!isset($reason) || $reason=="") {
	echo "Enter the reason for the credit (DON'T PUT THE TICKET ID HERE): \n";
	$stdin_fp = fopen("php://stdin","r");
	$reason = trim(fgets($stdin_fp));
	fclose($stdin_fp);
}

while (!isset($ticketid) || $ticketid=="") {
	echo "Enter the ticket ID: \n";
	$stdin_fp = fopen("php://stdin","r");
	$ticketid = trim(fgets($stdin_fp));
	if (!preg_match("/^[a-zA-Z]{3,3}-[0-9]{6,6}$/",$ticketid)) {
		echo "Error: Invalid format for ticket ID\n";
		unset($ticketid);
	}
	fclose($stdin_fp);
}

$reason .= " Ref: $ticketid";

asysexec("/usr/bin/php /home/asys/controlpanel/lib/dag/scripts/reseller_credit.php ".$username." ".$amount." \"".escapeshellcmd($reason)."\"");
?>
