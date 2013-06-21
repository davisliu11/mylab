#!/usr/bin/php
<?php
require "cli.lib.php";

$mysqlINI = parse_ini_file ( "/root/.my.cnf" );
if (!isset($mysqlINI["user"])) {
	echo "No user defined in .my.cnf";
	exit(1);
}

if (!isset($mysqlINI["password"])) {
	echo "No password defined in .my.cnf";
	exit(1);
}

// Process switches
while (true) {
        switch($argv[0]) {
                case "-m":
                case "--month":
                        array_shift($argv);
			if (preg_match("/^([0-9]{4,4})-([0-9]{2,2})$/",$argv[0],$matches)) {
	                        $year = $matches[1];
	                        $month = $matches[2];
			}
			else {
				echo "Error: --month must be in YYYY-mm format\n";
				exit;
			}
                        break;
		case "-d":
		case "--debug":
			$debug = true;
			break;
		case "-c":
		case "--code":
			array_shift($argv);
			$code = $argv[0];
			break;
        }
        array_shift($argv);
        if (count($argv)==0) break;
}

if (!isset($code)) {
	echo "Usage: spla-mscode-usage.php --code ms_order_code [--month month] [--debug]\n\n";
	exit;
}

if (!isset($year)||!isset($month)) {
	$monthname = date("F Y",mktime(0,0,0,date("m")-1,1,date("Y")));
	// day 0 for -1 months is the last day for -2 months
	$monthstart = date("Ymd235959",mktime(0,0,0,date("m")-1,0,date("Y")));
	$monthend = date("Ymd235959",mktime(0,0,0,date("m"),0,date("Y")));
}
else {
	// Use the previous month by default
	$monthname = date("F Y",mktime(0,0,0,$month,1,$year));
	// day 0 for the month is the last day for the previous month
	$monthstart = date("Ymd235959",mktime(0,0,0,$month,0,$year));
	$monthend = date("Ymd235959",mktime(0,0,0,$month+1,0,$year));
}

echo "\nUsage for $code $monthname ";
echo "(".$monthstart." to ".$monthend.")\n\n";

mysql_connect("127.0.0.1",$mysqlINI["user"],$mysqlINI["password"]);
mysql_select_db("asys");

$cliTable = new cliTable();
$cliTable->addColumn("username");
$cliTable->addColumn("datestart");
$cliTable->addColumn("dateend");
$cliTable->addColumn("accountname");
$cliTable->addColumn("ms_order_code");
$cliTable->addColumn("ms_order_quantity");
$cliTable->addColumn("ms_free_on_vps");

// Find accounts active during month i.e. accounts that started before the end of the month and did not finish before the start of the month
$sql = "select user_account.username,datestart,dateend,account.accountname,ms_order_code,ms_order_quantity,ms_free_on_vps from user_account,account where user_account.accountname=account.accountname and account.ms_order_code='".mysql_real_escape_string($code)."' and datestart<'$monthend' and (dateend=0 or dateend>='$monthstart') order by username,accountname";
if (isset($debug)) {
	echo $sql."\n";
}

$resid = mysql_query($sql);
$row=0;
while ($res = mysql_fetch_row($resid)) {
	$cliTable->newRow();
	while (list(,$colval) = each($res)) {
		$cliTable->addEntry($row,$colval);
	}
	$row++;
}

echo $cliTable->getTable();
?>
