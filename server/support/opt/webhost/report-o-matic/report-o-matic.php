#!/usr/bin/php
<?php
include 'Mail.php';
include 'Mail/mime.php';

DEFINE("DATABASE",		"esupport");
DEFINE("REPORT_QRY_TMPL",	"SELECT ticketmaskid ticket,FROM_UNIXTIME(dateline,'%Y-%m-%d') date,subject,swticketstatus.title status FROM swtickets,swticketstatus WHERE swtickets.ticketstatusid=swticketstatus.ticketstatusid AND subject like '[customer]:%' AND dateline>=[timeStart] AND dateline<=[timeEnd]");
DEFINE("REPORT_SUBJECT",	"Ticket report for [customer] [month]");
DEFINE("REPORT_BODY",
"Number of tickets: [ticket_count]

== Ticket statuses: ==
[ticket_statuses]
======================

==== Ticket list =====
[ticket_list]
======================
");

$thisScript = array_shift($argv); // Get rid of script name from args

while (count($argv)>0) {
	$arg = array_shift($argv);
	switch($arg) {
		case "--customer":
			$customer = trim(array_shift($argv));
			break;
		case "--month-offset":
			$monthOffset = trim(array_shift($argv));
			if (!preg_match("/^[0-9]+$/",$monthOffset)) {
				fatalError("Month offset ".$monthOffset." is not an Integer");
			}
			break;
		case "--email":
			$email = trim(array_shift($argv));
			break;
	}
}

if (!isset($customer) || !isset($monthOffset) || !isset($email)) {
	echo "\nUsage: $thisScript --customer customer --month-offset offset\n\n";
	exit;
}

$offset = (Int)$monthOffset;
$timeStart = mktime(0,0,0,date("m")-$offset,1,date("Y"));
$timeEnd = mktime(23,59,59,date("m")-$offset+1,0,date("Y"));
$month = date("F Y",$timeStart);
$dateStart = date("Y-m-d H:i:s",$timeStart);
$dateEnd = date("Y-m-d H:i:s",$timeEnd);

// === Connect to MySQL using /root/.my.cnf settings ===
$ini = parse_ini_file("/root/.my.cnf");
if (isset($ini["user"]))
	$user = $ini["user"];
else
	$user = "root";

if (isset($ini["pass"]))
	$pass = $ini["pass"];
else if (isset($ini["password"]))
	$pass = $ini["password"];
else
	fatalError("No password set in .my.cnf");

if (!isset($host)) {
	if (isset($ini["protocol"]) && $ini["protocol"]=="tcp")
		$host = "127.0.0.1";
	else
		$host = "localhost";
}

mysql_connect($host,$user,$pass);
mysql_select_db(DATABASE);
// =======================================================

$sql = str_replace("[customer]",mysql_real_escape_string($customer),REPORT_QRY_TMPL);
$sql = str_replace("[timeStart]",mysql_real_escape_string($timeStart),$sql);
$sql = str_replace("[timeEnd]",mysql_real_escape_string($timeEnd),$sql);

$resid = mysql_query($sql) or die(mysql_error());
$ticketStatuses = array();
$ticketInfos = array();
$ticketCount = 0;
$fp = fopen("/tmp/".$customer.".csv","w");
while ($res = mysql_fetch_array($resid,MYSQL_ASSOC)) {
	$ticketStatuses[$res["status"]]++;
	$ticketLine = $res["ticket"].",".$res["date"].",".$res["subject"].",".$res["status"];
	$ticketInfos[] = $ticketLine;
	fputs($fp,$ticketLine."\n");
	$ticketCount++;
}
fclose($fp);

$reportSubject = str_replace("[month]",$month,REPORT_SUBJECT);
$reportSubject = str_replace("[customer]",$customer,$reportSubject);
$reportBody = str_replace("[ticket_count]",$ticketCount,REPORT_BODY);
while (list($key,$val) = each($ticketStatuses)) {
	$statuses .= $key.":\t".$val."\n";
}
$reportBody = str_replace("[ticket_statuses]",$statuses,$reportBody);
$reportBody = str_replace("[ticket_list]",implode("\n",$ticketInfos),$reportBody);

$reportFrom = "Web Drive Support <support@webdrive.co.nz>";
$reportBodyHTML = str_replace("\n","</br>",$reportBody);

$crlf = "\n";
  
$hdrs = array(
      'From'    => $reportFrom,
      'Subject' => $reportSubject
);
  
$mime = new Mail_mime($crlf);
  
$mime->setTXTBody($reportBody);
$mime->setHTMLBody($reportBodyHTML);
$mime->addAttachment("/tmp/".$customer.".csv");

$body = $mime->get();
$hdrs = $mime->headers($hdrs);
  
$mail =& Mail::factory('mail');
$mail->send($email, $hdrs, $body);

unlink("/tmp/".$customer.".csv");

function fatalError($error) {
	echo "Error: ".$error."\n";
	exit;
}
?>
