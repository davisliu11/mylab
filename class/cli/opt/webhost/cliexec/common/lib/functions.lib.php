<?php
function fatalError($err) {
	echo "\nError: ".$err."\n\n";

    exit;
}

function checkArgs(&$argv,&$argList) {
		if (in_array("--help",$argv)) {
			// Just show the help and exit
			echo "\n".getUsage($argv,$argList)."\n\n";
			exit;
		}

        if (!is_array($argList)) return;

        reset($argList);
        $retAr = array();
        while (list($key,$valAr) = each($argList)) {
                $key++;
                $argName = $valAr[0];
                $argReg = $valAr[1];
                $argReq = ($valAr[2]==1);

                if (!$argReq && $argv[$key]=="") {
                        $retAr[$argName] = $argv[$key];
                }
                else if ($argReq && $argv[$key]=="") {
                        fatalError($argName." is blank"."\n".getUsage($argv,$argList));
                }
                else {
                        $regOk = 0;
                        if (is_array($argReg)) {
                                while (!$regOk&&list(,$theReg)=each($argReg)) {
                                        $regOk = preg_match($theReg,$argv[$key]);
                                }
                        }
                        else {
                                $regOk = preg_match($argReg,$argv[$key]);
                        }

                        if (!$regOk) {
                                fatalError($argName." $argv[$key] is not valid"."\n".getUsage($argv,$argList));
                        }
                        else {
                                $retAr[$argName] = $argv[$key];
                        }
                }
        }
        return $retAr;
}

function checkOpts(&$argv,&$argList) {
	// This function is designed to be compatible with the argList format while support --option options

	// ### Start PHP 5.2 code ###
	// Use the PEAR Getopt library to support --option options
	// When this library runs on PHP 5.3 the code can be replaced by PHP's getopt() function
	
	if (in_array("--help",$argv)) {
		// Just show the help and exit
		echo "\n".getUsageOpt($new_params,$argList)."\n\n";
		exit;
	}

	require_once 'Console/Getopt.php';

        if (!is_array($argList)) return;

        reset($argList);

	$cg = new Console_Getopt();
	$args = $cg->readPHPArgv();
	array_shift($args);

	$shortOpts = '';
	$longOpts = array();
	while (list(,$argElem) = each($argList)) {	
		$longOpts[] = $argElem[0];
	}
	reset($argList);

	$params = $cg->getopt2($args, $shortOpts, $longOpts);
	if (PEAR::isError($params)) {
		fatalError($params->getMessage());
	}

	$new_params = array();
	foreach ($params[0] as $param) {
		$new_params[preg_replace("/^--/","",$param[0])] = $param[1];
	}
	// ### End PHP 5.2 code ###

        $retAr = array();
        while (list($key,$valAr) = each($argList)) {
                $argName = preg_replace("/[=]+$/","",$valAr[0]);
                $argReg = $valAr[1];
                $argReq = $valAr[2];

		if (preg_match("/=$/",$valAr[0])) {
			$paramoptional = preg_match("/==$/",$valAr[0]);
			
			if (!$paramoptional) {
				// A parameter is required
				if (
					($argReq==1 && !isset($new_params[$argName])) ||				// required argument is not set
					($argReq!=1 && isset($new_params[$argName]) && $new_params[$argName]=="")	// argument not required but it is set with a blank value
				) {
					fatalError($argName." is required\n".getUsageOpt($new_params,$argList));
				}
			}
			
			if (isset($new_params[$argName])) {
                        	$regOk = 0;
                        	if (is_array($argReg)) {
                        	        while (!$regOk&&list(,$theReg)=each($argReg)) {
                        	                $regOk = preg_match($theReg,$new_params[$argName]);
                        	        }
                        	}
                        	else {
                        	        $regOk = preg_match($argReg,$new_params[$argName]);
                        	}

                        	if (!$regOk) {
                        	        fatalError($argName." $new_params[$argName] is not valid"."\n".getUsageOpt($new_params,$argList));
                        	}
                        	else {
                        	        $retAr[$argName] = $new_params[$argName];
                        	}
			}
			else if ($paramoptional) {
				// No parameter set
	                        $retAr[$argName] = (array_key_exists($argName,$new_params)) ? true : false;
			}
		}
		else {
			// No parameter expected
			$retAr[$argName] = (array_key_exists($argName,$new_params)) ? true : false;
		}
        }
        return $retAr;
}

function getUsage(&$argv,&$argList) {
	$usage = "Usage: ".basename($argv[0]);
	
	if (defined("CLIEXEC_USAGE")) {
		$usageAr[] = CLIEXEC_USAGE;
	}
	else {
		$keys = array_keys($argList);
		while (list(,$key) = each($keys)) {
			$usagetoken = $argList[$key][0];
			if ($argList[$key][2]==0) $usagetoken = "[".$usagetoken."]";
	
			$usageAr[] = $usagetoken;		
		}
	}
	return $usage." ".implode(" ",$usageAr);
}

function getUsageOpt(&$new_params,&$argList) {
	$usage = "Usage: ".basename($GLOBALS["argv"][0]);
	$keys = array_keys($argList);
	while (list(,$key) = each($keys)) {
		$argName = preg_replace("/[=]+$/","",$argList[$key][0]);
		$usagetoken = "--".$argName;
		if (preg_match("/=$/",$argList[$key][0])) {
			if (preg_match("/==$/",$argList[$key][0])) {
				// optional parameter
				$usagetoken .= "=[".$argName."]";
			}
			else {
				// required parameter
				$usagetoken .= "=".$argName;
			}
		}
		if ($argList[$key][2]==0) $usagetoken = "[".$usagetoken."]";

		$usageAr[] = $usagetoken;
	}

	return $usage." ".implode(" ",$usageAr);
}

// SUDO functions
$SUDOUSER="nobody";

function sudoexec() {
	$numargs = func_num_args();

	$cmd = basename($_SERVER["PHP_SELF"]);

	$sbincmd = str_replace("/bin/".$cmd,"/sbin/".$cmd.".php",$_SERVER["PHP_SELF"]);

	$sudocmd = "/usr/bin/sudo -u ".$GLOBALS["SUDOUSER"]." ".$sbincmd;

	for ($i=0;$i<$numargs;$i++) {
		$sudocmd .= " ".escapeshellarg(func_get_arg($i));
	}

	//echo $sudocmd."\n";

	//system($sudocmd);

	$sudoargs = array();
	$sudoargs[] = "-u";
	$sudoargs[] = $GLOBALS["SUDOUSER"];
	$sudoargs[] = $sbincmd;

	$argkeys = array_keys($GLOBALS["argv"]);
	while (list(,$key) = each($argkeys)) {
		if ($key==0) continue;
			$sudoargs[] = $GLOBALS["argv"][$key];
	}

	if (function_exists("pcntl_exec")) {
		$pid = pcntl_fork();
		if ($pid == -1) {
			die('could not fork '.__FILE__."#".__LINE__);
		}
		else if ($pid) {
			// We are the parent
			pcntl_wait($status);
		}
		else {
			// We are the child
			pcntl_exec("/usr/bin/sudo",$sudoargs,array("TERM"=>"xterm"));
		}
	}

}

function sudooptexec() {
	$cmd = basename($_SERVER["PHP_SELF"]);

	$sbincmd = str_replace("/bin/".$cmd,"/sbin/".$cmd.".php",$_SERVER["PHP_SELF"]);

	$sudocmd = "/usr/bin/sudo -u ".$GLOBALS["SUDOUSER"]." ".$sbincmd;

	$sudoargs = array();
	$sudoargs[] = "-u";
	$sudoargs[] = $GLOBALS["SUDOUSER"];
	$sudoargs[] = $sbincmd;

	$argkeys = array_keys($GLOBALS["argv"]);
	while (list(,$key) = each($argkeys)) {
		if ($key==0) continue;
			$sudoargs[] = $GLOBALS["argv"][$key];
	}

	if (function_exists("pcntl_exec")) {
		$pid = pcntl_fork();
		if ($pid == -1) {
			die('could not fork '.__FILE__."#".__LINE__);
		}
		else if ($pid) {
			// We are the parent
			pcntl_wait($status);
		}
		else {
			// We are the child
			pcntl_exec("/usr/bin/sudo",$sudoargs,array("TERM"=>"xterm"));
		}
	}

}
?>
