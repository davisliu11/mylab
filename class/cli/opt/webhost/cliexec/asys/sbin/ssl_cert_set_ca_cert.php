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

$argList[] = array("certid=",		"/^[0-9]+$/",			1);
$argList[] = array("cacertid=",		"/^[0-9]+$/",			1);
$argList[] = array("delete",		"/.*/",				0);

$options = checkOpts($argv,$argList);
// 	=======================================================================

$execstr = "/usr/bin/php /asys/controlpanel/scripts/ssl_cert_set_ca_cert.php";

if (isset($options["certid"]) && $options["certid"]!="") {
	$execstr .= " --certid \"".escapeshellcmd($options["certid"])."\"";
}
if (isset($options["cacertid"]) && $options["cacertid"]!="") {
	$execstr .= " --cacertid \"".escapeshellcmd($options["cacertid"])."\"";
}
if (isset($options["delete"]) && $options["delete"]===true) {
	$execstr .= " --delete";
}

asysexec($execstr); 
?>
