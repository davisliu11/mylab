#!/usr/bin/php
<?php
include_once "/opt/webhost/cliexec/asys/lib/asys.lib.php";
include_once "/opt/webhost/cliexec/asys/slib/asys.slib.php";

// 	=======================================================================
// 	= Arg list:  list of arguments to pass to checkArgs()                 =
// 	=            argList[$position] = array($name, $regex, $required);    =
// 	=======================================================================

$argList[] = array("domain",              REG_DOMAIN,                   	1);
$argList[] = array("start",               "/^[0-9]+$/",                   	0);
$argList[] = array("end",                 "/^[0-9]+$/",                   	0);

extract(
        checkArgs($argv,$argList)
);
// 	=======================================================================

$execstr = "/usr/bin/php /home/asys/controlpanel/lib/dag/scripts/domain_transaction_log_search.php -d ".$domain;

if (isset($start) && $start!="" && isset($end) && $end!="") {
	$execstr .= " -s $start -e $end";
}

asysexec($execstr);
?>
