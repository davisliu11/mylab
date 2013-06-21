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

$argList[] = array("destination=",		array(REG_DOMAIN,REG_EMAIL),			1);

$options = checkOpts($argv,$argList);
// 	=======================================================================

$execstr = "/usr/bin/php /asys/controlpanel/scripts/cp_email_blacklist_show.php";

if (isset($options["destination"]) && $options["destination"]!="") {
	$execstr .= " --destination \"".escapeshellcmd($options["destination"])."\"";
}

asysexec($execstr); 
?>
