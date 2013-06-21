#!/usr/bin/php
<?php
include_once "/opt/webhost/cliexec/asys/lib/asys.lib.php";
include_once "/opt/webhost/cliexec/asys/slib/asys.slib.php";

// 	=========================================================================================
// 	= Arg list:  list of arguments to pass to checkArgs()             		   	=
// 	=            argList[$position] = array($name, $regex);					=
// 	=		name suffixes:								=
// 	=			= option has a parameter that is required			=
// 	=			== option may have a parameter					=
// 	=========================================================================================

$argList[] = array("sub_acc_id=",	REG_INT,			1);
$argList[] = array("email=",		REG_EMAIL,			1);

$options = checkOpts($argv,$argList);
// 	=======================================================================

$execstr = "/usr/bin/php /asys/controlpanel/scripts/subacc_email_set.php";

if (isset($options["sub_acc_id"]) && $options["sub_acc_id"]!="") {
	$execstr .= " --sub_acc_id \"".escapeshellcmd($options["sub_acc_id"])."\"";
}
if (isset($options["email"]) && $options["email"]!="") {
	$execstr .= " --email \"".escapeshellcmd($options["email"])."\"";
}

asysexec($execstr); 
?>
