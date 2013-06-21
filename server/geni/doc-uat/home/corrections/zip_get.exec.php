<?php
include 'Mail.php';
include 'Mail/mime.php';

$emailBody = array();

array_shift($argv);
$argc = count($argv);
while ($argc>0) {
	$val = array_shift($argv);
	switch($val) {
		case "-f": 
		$file = array_shift($argv);
		if (!is_file($file)) {
			showError("File: ".$file." does not exist");
			exit;
		}
		break;
	}
	$argc = count($argv);
}

if (!isset($file)) {
	// check for an updated file
	$curlOb = curl_init(); 
	curl_setopt($curlOb, CURLOPT_URL, ZIP_URL); 
	curl_setopt($curlOb, CURLOPT_PORT , 80); 
	curl_setopt($curlOb, CURLOPT_NOBODY, 1); 
	curl_setopt($curlOb, CURLOPT_HEADER, 1); 
	curl_setopt($curlOb, CURLOPT_RETURNTRANSFER, 1);

	$curlData = explode("\n",curl_exec($curlOb));
	while (list(,$header) = each($curlData)) {
		if (preg_match("/^Last-Modified: (.*)/",$header,$matches)) {
			$ts = strtotime($matches[1]);
			if ($ts===false) {
				showError("Invalid timestamp ".$matches[1]);
				exit;
			}

			$zipsuffix = date("YmdHis",$ts);
			$zipname = str_replace("[zipsuffix]",$zipsuffix,ZIP_NAME_TPL);
			if (is_file(FOLDER_HOME."/".$zipname)) {
				showMsg("Zip file up-to-date already");
				exit;
			}

			showMsg("Getting updated ZIP from ".date("Y-m-d H:i:s",$ts));
			$wgetExec = "/usr/bin/wget --quiet ".ZIP_URL." --output-document ".FOLDER_HOME."/".$zipname;
			`$wgetExec`;
			$emailBody[] = "Fetched updated ZIP from ".date("Y-m-d H:i:s",$ts);
			$file = FOLDER_HOME."/".$zipname;
		}		
                if (preg_match("/^Content-Length: (.*)/",$header,$matches2)) {
			$lengthExpected = (int)$matches2[1];
			$lengthReceived = filesize($file);
			if ($lengthReceived!=$lengthExpected) {
				showError("File size $lengthReceived Bytes differs from the expected $lengthExpected Bytes");
				unlink($file);
				exit;
			}
		}
	}
}

if (strpos($file,FOLDER_HOME)!==0) {
	$file = FOLDER_HOME."/".$file;
}

// Don't delete UAT files if the ZIP is too small
$minMB = 10;
$minByte = $minMB * 1024 * 1024;    
if (!is_file($file) || filesize($file)<$minByte) {
  $emailBody[] = "Website ".SITE_UAT." was was not updated as the downloaded ZIP archive was smaller than the ".$minMB."MB minimum";

  $from = "corrections@uat.corrections.govt.nz";
  $subject = "FAIL: ".SITE_UAT;
  $body = implode("\n",$emailBody);

  $bodyHTML = "<html><body>".str_replace(SITE_UAT,"<a href=\"http://".SITE_UAT."\">".SITE_UAT."</a>",$body)."</body></html>";

  $crlf = "\n";
  
  $hdrs = array(
  	'From'    => $from,
  	'Subject' => $subject
  );
  
  $mime = new Mail_mime($crlf);
  
  $mime->setTXTBody($body);
  $mime->setHTMLBody($bodyHTML);
  
  $body = $mime->get();
  $hdrs = $mime->headers($hdrs);
  
  $mail =& Mail::factory('mail');
  $mail->send("steve@webdrive.co.nz", $hdrs, $body);
  exit;
}

if (is_dir(FOLDER_TMP)) system(EXEC_RM." -fr ".FOLDER_TMP);
mkdir(FOLDER_TMP);

$execstr = EXEC_UNZIP." -d ".FOLDER_TMP."/ ".$file." 2>&1";
$output=`$execstr`;

$execstr = EXEC_RSYNC." --delete -var ".FOLDER_TMP."/ ".FOLDER_WEB."/ 2>&1 | egrep -v \"^sending incremental file list|./$|^sent |^total size is \"";
$output=`$execstr`;
showMsg($output);

system(EXEC_RM." -fr ".FOLDER_TMP);
system(EXEC_HTDIG);

$emailBody[] = "";
$emailBody[] = "Website ".SITE_UAT." was successfully updated.  Indexing of the site is complete.";

$from = "corrections@uat.corrections.govt.nz";
$subject = "SUCCESS: ".SITE_UAT;
$body = implode("\n",$emailBody);
$bodyHTML = "<html><body>".str_replace(SITE_UAT,"<a href=\"http://".SITE_UAT."\">".SITE_UAT."</a>",$body)."</body></html>";

$crlf = "\n";

$hdrs = array(
	'From'    => $from,
	'Subject' => $subject
);

$mime = new Mail_mime($crlf);

$mime->setTXTBody($body);
$mime->setHTMLBody($bodyHTML);

$body = $mime->get();
$hdrs = $mime->headers($hdrs);

$mail =& Mail::factory('mail');
$mail->send(UPDATE_EMAIL, $hdrs, $body);

// Remove old ZIP files
$zipWildcard = str_replace("[zipsuffix]","*",ZIP_NAME_TPL);
$lsExec = "ls --color=never -tr -1 ".$zipWildcard;
$zips = explode("\n",trim(`$lsExec`));
$zipCount = count($zips);

if ($zipCount>ZIP_KEEP_COUNT) {
	$loopCount = $zipCount - ZIP_KEEP_COUNT;
	for ($i=0;$i<$loopCount;$i++) {
		$rmExec = "rm -f ".FOLDER_HOME."/".$zips[$i];
		`$rmExec`;
		showMsg("Removing old ZIP (".$zips[$i].")");
	}
}

function showError($err) {
	echo date("Y-m-d H:i:s")." [Error] ".$err."\n";
}

function showMsg($msg) {
	echo date("Y-m-d H:i:s")." ".$msg."\n";
}
?>
