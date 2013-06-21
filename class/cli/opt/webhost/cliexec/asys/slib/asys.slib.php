<?php
function asysexec($cmd,$debug=false) {
	$SSHKEY=$GLOBALS["SLIB"]."/cli-asys-rsa";

	$cmd = trim($cmd);

	if ($cmd=="") {
		echo "Error: asysexec no command set\n";
		return 0;
	}

	$sshargs = array();
	$sshargs[] = "-q";
	$sshargs[] = "-t";
	$sshargs[] = "-i";
	$sshargs[] = $SSHKEY;
	$sshargs[] = "asys@wh-srv1.services.webhost.co.nz";
	$sshargs[] = $cmd;
	
	if ($debug) {
		echo print_r($sshargs,true)."\n";
	}

	if (function_exists("pcntl_exec")) {
                        $pid = pcntl_fork();
                        if ($pid == -1) {
                                die('could not fork');
                        }
                        else if ($pid) {
                                // We are the parent
                                pcntl_wait($status);
                        }
                        else {
                                // We are the child
                                pcntl_exec("/usr/bin/ssh",$sshargs,array("TERM"=>"xterm"));
                        }
	}
}

function nasopsexec($cmd,$debug=false) {
	$SSHKEY=$GLOBALS["SLIB"]."/cli-nasops-rsa";

	$cmd = trim($cmd);

	if ($cmd=="") {
		echo "Error: nasopsexec no command set\n";
		return 0;
	}

	$sshargs = array();
	$sshargs[] = "-q";
	$sshargs[] = "-t";
	$sshargs[] = "-i";
	$sshargs[] = $SSHKEY;
	$sshargs[] = "wdsupport@nasops.webhost.co.nz";
	$sshargs[] = $cmd;
	
	if ($debug) {
		echo print_r($sshargs,true)."\n";
	}

	if (function_exists("pcntl_exec")) {
                        $pid = pcntl_fork();
                        if ($pid == -1) {
                                die('could not fork');
                        }
                        else if ($pid) {
                                // We are the parent
                                pcntl_wait($status);
                        }
                        else {
                                // We are the child
                                pcntl_exec("/usr/bin/ssh",$sshargs,array("TERM"=>"xterm"));
                        }
	}
}
?>
