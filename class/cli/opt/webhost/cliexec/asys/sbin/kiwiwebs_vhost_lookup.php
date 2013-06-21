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

$argList[] = array("domain=",	REG_DOMAIN,		1);

$options = checkOpts($argv,$argList);
// 	=======================================================================

// Database settings
DEFINE("DB",            "asys_kiwiw");
DEFINE("DB_PORT",       "3306");
DEFINE("DB_USER",       "asys_kiwiw");
DEFINE("DB_PASS",       "tE4ftrB6j");
DEFINE("DB_HOST",       "asysdb.webhost.co.nz");

mysql_connect(DB_HOST,DB_USER,DB_PASS) or die(mysql_error());
mysql_select_db(DB) or die(mysql_error());

$sqlstr = "SELECT DISTINCT vhostpath FROM vhost WHERE domain='".mysql_real_escape_string($options["domain"])."'";

$resid = mysql_query($sqlstr) or die(mysql_error());
if (mysql_num_rows($resid)==0) echo "Domain does not exist\n";
else {
	while ($res = mysql_fetch_row($resid)) {
		echo $res[0]."\n";
	}
}
?>
