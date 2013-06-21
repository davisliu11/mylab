#!/usr/bin/php -q
<?
DEFINE("VHLINK_DIR",		"/home/httpd/vhlinks");
DEFINE("VHLINK_SSL_DIR",	VHLINK_DIR."/www.safeshop.co.nz");
DEFINE("VHLINK_PREVIEW_DIR",	VHLINK_DIR."/web.mydns.net.nz");

$THISROOT = "/opt/webhost/ws";

mysql_connect("asysdb.webhost.co.nz","vhost_log","9OcdehanFems");
mysql_select_db("asys");

if (isset($argv[1])) {
        if (!preg_match("/^[A-Za-z0-9]+$/",$argv[1])) {
                echo "Error: ID is not a username (".$argv[1].")\n";
                exit;
        }

        $username = $argv[1];
	$userSQL = "AND folder LIKE '%".mysql_real_escape_string($username)."%'";
}

$timeSQL = "created>date_sub(NOW(),INTERVAL 2 MONTH)";

$sql = "SELECT DISTINCT vhost FROM vhost_log WHERE `ssl`=0 AND ".$timeSQL." $userSQL ORDER BY vhost limit 1000";
$resid = mysql_query($sql) or die(mysql_error());
while ($row = mysql_fetch_array($resid,MYSQL_ASSOC)) {
	if (is_link("/home/httpd/vhlinks/".$row["vhost"]))
		$link1 = readlink("/home/httpd/vhlinks/".$row["vhost"]);
	else
		$link1 = "NONE";

	if (is_link("/home/httpd/vhlinks.old/".$row["vhost"]))
		$link2 = readlink("/home/httpd/vhlinks.old/".$row["vhost"]);
	else
		$link2 = "NONE";

	if ($link1!=$link2) {
		echo $row["vhost"]." ".$link1." ".$link2."\n";
	}
}
?>
