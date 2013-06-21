#!/usr/bin/php
<?php
include_once "/opt/webhost/cliexec/asys/lib/asys.lib.php";
include_once "/opt/webhost/cliexec/asys/slib/asys.slib.php";

// 	=======================================================================
// 	= Arg list:  list of arguments to pass to checkArgs()                 =
// 	=            argList[$position] = array($name, $regex, $required);    =
// 	=======================================================================

$argList[] = array("domain",		REG_DOMAIN,			1);
$argList[] = array("type",		"/^[0-9A-Za-z]+$/",		1);
$argList[] = array("debug",		"/^[01]$/",			0);

extract(
        checkArgs($argv,$argList)
);
// 	=======================================================================

if (!isset($debug)) {
	$debug=1;
}

asysexec("/usr/bin/php /home/asys/controlpanel/lib/dag/scripts/send_notifications.php ".$domain." ".$type." ".$debug);

if ($debug=="1") {
	echo "\nDebug Mode.  Notification NOT sent.\n";
}
?>
