<?php
function pbasexec($cmd,$debug=false) {
	$SSHKEY=$GLOBALS["SLIB"]."/cli-pbas-rsa";

	$cmd = trim($cmd);

	if ($cmd=="") {
		echo "Error: pbasexec no command set\n";
		return 0;
	}

	$sshargs = array();
	$sshargs[] = "-q";
	$sshargs[] = "-t";
	$sshargs[] = "-i";
	$sshargs[] = $SSHKEY;
	$sshargs[] = "root@hspc.openhost.net.nz";
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
