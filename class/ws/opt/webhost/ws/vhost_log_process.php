#!/usr/bin/php -q
<?
DEFINE("VHLINK_DIR",		"/home/httpd/vhlinks");
DEFINE("VHLINK_SSL_DIR",	VHLINK_DIR."/www.safeshop.co.nz");
DEFINE("VHLINK_PREVIEW_DIR",	VHLINK_DIR."/web.mydns.net.nz");

$THISROOT = "/opt/webhost/ws";

if (isset($argv[1])) {
	if (!preg_match("/^[0-9]+$/",$argv[1])) {
		echo "Error: ID is not an Integer (".$argv[1].")\n";
		exit;
	}

	$id = $argv[1];
	$timeSQL = "created>date_sub(NOW(),INTERVAL 1 DAY)";
}
else {
	// restrict to 1 hour if run without a argument
	$id = trim(implode("",file($THISROOT."/vhost_log_process.pos")));
	$timeSQL = "created>date_sub(NOW(),INTERVAL 1 HOUR)";
}

if ($id==null || $id==0) {
	echo "Error: ID zero. Exiting\n";
	exit;
}

echo "Run log from ".$id."\n";

mysql_connect("asysdb.webhost.co.nz","vhost_log","9OcdehanFems");
mysql_select_db("asys");

$sql = "SELECT * FROM vhost_log WHERE id>".mysql_real_escape_string($id)." AND ".$timeSQL;
$resid = mysql_query($sql);
while ($row = mysql_fetch_array($resid,MYSQL_ASSOC)) {
	if ($row["ssl"]=="1") {
		if ($row["folder"]==null) {
			sslDel($row["vhost"]);	
		}
		else {
			sslAdd($row["vhost"],$row["folder"]);
		}
	}
	else {
		if ($row["folder"]==null) {
			vhostDel($row["vhost"]);
		}
		else {
			vhostAdd($row["vhost"],$row["folder"]);
		}
	}
	$lastid = $row["id"];
}

if (isset($lastid)) {
	echo "Last id: ".$lastid."\n";
	$fp = fopen($THISROOT."/vhost_log_process.pos","w");
	fputs($fp,$lastid);
	fclose($fp);
}

function vhostDel($vhost) {
	if (is_link(VHLINK_DIR."/".$vhost)) {
		@unlink(VHLINK_DIR."/".$vhost);
		//echo "unlink: ".VHLINK_DIR."/".$vhost."\n";
	}
	if (is_dir(VHLINK_PREVIEW_DIR) && is_link(VHLINK_PREVIEW_DIR."/".$vhost)) {
		@unlink(VHLINK_PREVIEW_DIR."/".$vhost);
		//echo "unlink: ".VHLINK_PREVIEW_DIR."/".$vhost."\n";
	}
}

function vhostAdd($vhost,$folder) {
	vhostDel($vhost);
	symlink($folder,VHLINK_DIR."/$vhost");
	//echo "sym link: ".$folder." ".VHLINK_DIR."/$vhost\n";
	if (is_dir(VHLINK_PREVIEW_DIR)) {
		symlink($folder,VHLINK_PREVIEW_DIR."/$vhost");
		//echo "sym link: ".$folder." ".VHLINK_PREVIEW_DIR."/$vhost\n";
	}
}

function sslDel($label) {
	if (is_link(VHLINK_SSL_DIR."/".$label)) {
		@unlink(VHLINK_SSL_DIR."/".$label);
		//echo "unlink: ".VHLINK_SSL_DIR."/".$label."\n";
	}
}

function sslAdd($label,$folder) {
	sslDel($label);
	symlink($folder,VHLINK_SSL_DIR."/".$label);
	//echo "sym link: ".$folder." ".VHLINK_SSL_DIR."/$label\n";
}
?>
