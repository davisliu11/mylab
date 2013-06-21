#!/usr/bin/php
<?php
include_once "/opt/webhost/cliexec/asys/lib/asys.lib.php";
include_once "/opt/webhost/cliexec/asys/slib/asys.slib.php";

// 	=======================================================================
// 	= Arg list:  list of arguments to pass to checkArgs()                 =
// 	=            argList[$position] = array($name, $regex, $required);    =
// 	=======================================================================

$argList[] = array("search",              array(REG_DOMAIN,"/^[0-9]+$/"),                   	1);

extract(
        checkArgs($argv,$argList)
);
// 	=======================================================================

$execstr = "/usr/bin/php /home/asys/controlpanel/lib/dag/scripts/domain_invoice_search.php -s ".$search;

asysexec($execstr);
?>
