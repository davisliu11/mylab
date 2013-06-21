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

$argList[] = array("commonname=",	"/^([\pL\pN\-\*]+)(\.[\pL\pN\-]+)+$/u",			1);
$argList[] = array("force-new-csr",	"/.*/",							0);
$argList[] = array("force-new-key",	"/.*/",							0);

$options = checkOpts($argv,$argList);
// 	=======================================================================

$execstr = "/usr/bin/php /asys/controlpanel/scripts/ssl_csr_make.php";

if (isset($options["commonname"]) && $options["commonname"]!="") {
	$execstr .= " --commonname \"".str_replace("\*","*",escapeshellcmd($options["commonname"]))."\"";
}
if (isset($options["force-new-key"]) && $options["force-new-key"]===true) {
	$execstr .= " --force-new-key";
}

asysexec($execstr); 
?>
