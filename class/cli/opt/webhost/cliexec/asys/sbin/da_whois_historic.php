#!/usr/bin/php
<?php
include_once "/opt/webhost/cliexec/asys/lib/asys.lib.php";
include_once "/opt/webhost/cliexec/asys/slib/asys.slib.php";

// 	=======================================================================
// 	= Arg list:  list of arguments to pass to checkArgs()                 =
// 	=            argList[$position] = array($name, $regex, $required);    =
// 	=======================================================================

$argList[] = array("domain",		REG_DOMAIN,		         	          	1);
$argList[] = array("date",		"/^[0-9]{4,4}-[0-9]{2,2}-[0-9]{2,2}$/",			1);

extract(
        checkArgs($argv,$argList)
);
// 	=======================================================================

asysexec("/usr/bin/php /home/asys/controlpanel/lib/dag/scripts/whois_historic.php --domain ".$domain." --date ".$date);
?>
