#!/usr/bin/php
<?php
include_once "/opt/webhost/cliexec/asys/lib/asys.lib.php";
include_once "/opt/webhost/cliexec/asys/slib/asys.slib.php";

// 	=======================================================================
// 	= Arg list:  list of arguments to pass to checkArgs()                 =
// 	=            argList[$position] = array($name, $regex, $required);    =
// 	=======================================================================

$argList[] = array("username",		REG_USERNAME,			1);

extract(
        checkArgs($argv,$argList)
);
// 	=======================================================================

asysexec("/usr/bin/php /asys/controlpanel/scripts/user_upgrade_to_dag_reseller.php --username ".escapeshellcmd($username));
?>
