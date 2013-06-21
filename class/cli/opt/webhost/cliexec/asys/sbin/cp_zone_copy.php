#!/usr/bin/php
<?php
include_once "/opt/webhost/cliexec/asys/lib/asys.lib.php";
include_once "/opt/webhost/cliexec/asys/slib/asys.slib.php";

// 	=======================================================================
// 	= Arg list:  list of arguments to pass to checkArgs()                 =
// 	=            argList[$position] = array($name, $regex, $required);    =
// 	=======================================================================

$argList[] = array("zoneid",		"/^[0-9]+$/",			1);
$argList[] = array("newname",		"/^.+$/",                   	1);
$argList[] = array("username",		REG_USERNAME,			1);
$argList[] = array("sub_acc_id",	"/^[0-9]+$/",			0);

extract(
        checkArgs($argv,$argList)
);
// 	=======================================================================

asysexec("/usr/bin/php /asys/controlpanel/scripts/zone_copy.php --zoneid ".$zoneid." --new-name \"".escapeshellcmd($newname)."\" --username ".$username." --sub-acc-id ".$sub_acc_id); 
?>
