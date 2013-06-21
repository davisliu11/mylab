#!/usr/bin/php
<?php
// Process switches
while (true) {
        switch($argv[0]) {
                case "-d":
                case "--database":
                        array_shift($argv);
                        $database = preg_replace("/[^0-9a-zA-Z_]/","",$argv[0]);
                        break;
                case "-t":
                case "--table":
                        array_shift($argv);
			$table = preg_replace("/[^0-9a-zA-Z_]/","",$argv[0]);
                        break;
        }
        array_shift($argv);
        if (count($argv)==0) break;
}

if (!isset($database) || !isset($table)) {
	echo "Usage: ".$_SERVER["PHP_SELF"]." --database database --table table\n\n";
	exit;
}

$mysqlINI = parse_ini_file ( "/root/.my.cnf" );
if (!isset($mysqlINI["user"])) {
	echo "No user defined in .my.cnf";
	exit(1);
}

if (!isset($mysqlINI["password"])) {
	echo "No password defined in .my.cnf";
	exit(1);
}

mysql_connect("127.0.0.1",$mysqlINI["user"],$mysqlINI["password"]);
mysql_select_db("asys");

$sql = 'SHOW FIELDS FROM `'.$database.'`.`'.$table.'`';
$resid = mysql_query($sql) or die(mysql_error());
$fields = array();
while ($res = mysql_fetch_row($resid)) {
	$fields[] = $res[0];
}

$fieldstr = "`".implode("`,`",$fields)."`";
$sql = "SELECT MD5(CONCAT_WS('#', $fieldstr)) AS crc FROM `".$database."`.`".$table."` order by crc";
$resid = mysql_query($sql) or die(mysql_error());
$largestr = "";
while ($res = mysql_fetch_row($resid)) {
	$largestr .= $res[0];
}

echo $database." ".$table." ".md5($largestr)."\n";
?>
