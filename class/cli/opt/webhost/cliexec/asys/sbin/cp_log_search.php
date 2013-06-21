#!/usr/bin/php
<?php
include_once "/opt/webhost/cliexec/asys/lib/asys.lib.php";
include_once "/opt/webhost/cliexec/asys/slib/asys.slib.php";

// 	=======================================================================
// 	= Arg list:  list of arguments to pass to checkArgs()                 =
// 	=            argList[$position] = array($name, $regex, $required);    =
// 	=======================================================================

$argList[] = array("searchstring",        "/^.+$/",	                   	1);
$argList[] = array("start",               "/^[0-9]+$/",                   	0);
$argList[] = array("end",                 "/^[0-9]+$/",                   	0);
$argList[] = array("full",                 "/^full+$/",                   	0);

extract(
        checkArgs($argv,$argList)
);
// 	=======================================================================

$execstr = "/usr/bin/php /asys/controlpanel/scripts/hosting_log_search.php --search $searchstring";

if (isset($start) && $start!="" && isset($end) && $end!="") {
	$execstr .= " --start $start --end $end";
}

if (isset($full) && $full!="") {
	$execstr .= " --full";
}

asysexec($execstr);
?>
