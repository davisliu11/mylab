#!/usr/bin/php
<?php
if (!is_file($argv[1])) {
	echo "Usage: ".$argv[0]." binlog\n";
	exit;
}
$log = $argv[1];

$updatesize = array();
$currentdb = "NONE";
$pp = popen("/usr/bin/mysqlbinlog ".$log,"r");
while ($line = fgets($pp)) {
	if (preg_match("/^use ([a-zA-Z0-9_]+)/",trim($line),$matches)) {
		$currentdb = $matches[1];
	}
	else {
		$updatesize[$currentdb] += strlen($line)/1024/1024;
	}
}
fclose($pp);

asort($updatesize);

while (list($db,$size) = each($updatesize)) {
	echo $db."\t\t".round($size,2)." MB\n";
}
?>
