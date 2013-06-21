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

$options = checkOpts($argv,$argList);
// 	=======================================================================

$execstr = "/opt/webhost/FormMail/FMedit";

nasopsexec($execstr); 
?>
