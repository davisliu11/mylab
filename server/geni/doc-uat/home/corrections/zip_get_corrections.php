#!/usr/bin/php
<?php
$user = trim(`whoami`);
if ($user!="corrections") {
	echo "Error: This script must be run as the corrections user, not $user.\n\n";
	exit(0);
}

DEFINE("SITE_URL",		"www.corrections.govt.nz");
DEFINE("SITE_UAT",		"uat.corrections.govt.nz");
//DEFINE("SITE_UAT",		"112.109.67.3");
DEFINE("UPDATE_EMAIL",		"webmaster@corrections.govt.nz");

DEFINE("EXEC_UNZIP",		"/usr/bin/unzip");
DEFINE("EXEC_RM",		"/bin/rm");
DEFINE("EXEC_RSYNC",		"/usr/bin/rsync");
DEFINE("EXEC_HTDIG",		"/var/www/corrections.govt.nz/htdig_private/rundig.sh -c /var/www/corrections.govt.nz/htdig_private/site.conf 2>/dev/null >/dev/null");

DEFINE("ZIP_URL",		"http://aklcms02.corrections.govt.nz/export-corrections/corrections.zip");
DEFINE("ZIP_NAME_TPL",		"corrections-[zipsuffix].zip");
DEFINE("ZIP_KEEP_COUNT",	7);

DEFINE("FOLDER_HOME",		"/home/corrections");
DEFINE("FOLDER_TMP",		FOLDER_HOME."/corrections-tmp");

DEFINE("FOLDER_WEB",		"/var/www/corrections.govt.nz/htdocs");

include "zip_get.exec.php";
?>
