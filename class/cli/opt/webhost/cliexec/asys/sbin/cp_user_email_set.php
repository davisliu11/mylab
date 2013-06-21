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

$argList[] = array("username=",		REG_USERNAME,			1);
$argList[] = array("email=",		REG_EMAIL,			1);

$options = checkOpts($argv,$argList);
// 	=======================================================================

$execstr = "/usr/bin/php /asys/controlpanel/scripts/user_email_set.php";

if (isset($options["username"]) && $options["username"]!="") {
	$execstr .= " --username \"".escapeshellcmd($options["username"])."\"";
}
if (isset($options["email"]) && $options["email"]!="") {
	$execstr .= " --email \"".escapeshellcmd($options["email"])."\"";
}

asysexec($execstr); 
?>
