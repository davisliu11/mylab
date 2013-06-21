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

$argList[] = array("commonname=",	"/^([\pL\pN\-\*]+)(\.[\pL\pN\-]+)+$/u",			0);
$argList[] = array("search=",		"/^([\pL\pN\-\*\.]+)$/u",				0);
$argList[] = array("historic",		"/.*/",							0);

$options = checkOpts($argv,$argList);
// 	=======================================================================

$execstr = "/usr/bin/php /asys/controlpanel/scripts/ssl_cert_search.php";

if (isset($options["commonname"]) && $options["commonname"]!="") {
	$execstr .= " --commonname \"".str_replace("\*","*",escapeshellcmd($options["commonname"]))."\"";
}

if (isset($options["search"]) && $options["search"]!="") {
	$execstr .= " --search \"".escapeshellcmd($options["search"])."\"";
}

if (isset($options["historic"]) && $options["historic"]===true) {
        $execstr .= " --historic";
}

asysexec($execstr); 
?>
