<?php

class wdmysqlbk
{
	
	public function __construct(array $arguments)
	{

		date_default_timezone_set('Pacific/Auckland');

		require_once('config.php');
		require_once('log.php');		
		
		// Argument input container
		$input = array();

		// Set verbosity
		if (in_array('-v', $arguments))
		{
			wdmysqlbk_log::setVerbose(true);
		}

		if (count($arguments) > 1)
		{
			for ($argument = 1; $argument < count($arguments); $argument++) 
			{
				if ($argument == 1)
				{
					$action = $arguments[$argument];
				}
				// Argument with value specified
				if (strstr($arguments[$argument], '='))
				{
					list($command, $value) = explode('=', $arguments[$argument], 2);
					$input[substr($command, 2)] = $value;
				}
				// Boolean argument
				elseif (substr($arguments[$argument], 0, 2) == '--')
				{
					$command = $arguments[$argument];
					$input[$command] = true;
				}
			}
		}
		else
		{
			$action = null;
		}

		switch ($action)
                {
			case 'run':
				$this->_backupAll();
				break;
			case 'check':
				$this->_checkAll();
				break;
			case 'check-nrpe':
				$this->_checkAll($nrpe=true);
				break;
			default:
				$this->_outputUse();
				break;
		}

	}

	// Backup specific target machine
	private function _backupTarget($targetname, array $target)
	{
		putenv("PATH=/opt/webhost/bin/:/usr/mysql/5.1/bin/:/usr/gnu/bin/:".getenv("PATH"));

		$hour = (int)date("H");
		$minute = (int)date("i");

		$baseconfig = wdmysqlbk_config::getBaseConfig();

		$commonconfig = array();

		foreach ($target as $schedule => $config) {
			if ($schedule=="common") {
				$commonconfig = $config;
				continue;
			}

			$config = array_merge($config, $commonconfig);

			if (!isset($config["run-hour"]) && !isset($config["run-minute"])) {
				wdmysqlbk_log::log("$targetname - $schedule - one or both of run-hour or run-minute must be set");
				continue;
			}

			if ($config["run-minute"]=="*") {
				wdmysqlbk_log::log("$targetname - $schedule - run-minute can no be a *, defaulting to 0");
				$config["run-minute"] = "0";
			}

			if (!isset($config["run-hour"])) $config["run-hour"] = "*";	// default to all hours
			if (!isset($config["run-minute"])) $config["run-minute"] = "0";	// default to 0 minutes

			if (strpos($config["run-hour"],",")!==false) {
				// A comma delimited list of hours
				$hours = explode(",",$config["run-hour"]);
				array_walk($hours,'intval');
				if (!in_array($hour,$hours)) continue;
			}
			else {
				if ($config["run-hour"]!="*" && (int)$config["run-hour"]!=$hour) continue; 
			}

			if (strpos($config["run-minute"],",")!==false) {
				// A comma delimited list of hours
				$minutes = explode(",",$config["run-minute"]);
				array_walk($minutes,'intval');
				if (!in_array($minute,$minutes)) continue;
			}
			else {
				if ($config["run-minute"]!="*" && (int)$config["run-minute"]!=$minute) continue;
			}

			$mysqldumpcmd = array("mysql-dump-all");

			if (isset($config["host"]))
				$mysqldumpcmd[] = "--host ".$config["host"];

			if (isset($config["dir-per-day"]) && $config["dir-per-day"]==true)
				$mysqldumpcmd[] = "--dir-per-day";
			if (isset($config["dir-add-time-suffix"]) && $config["dir-add-time-suffix"]==true)
				$mysqldumpcmd[] = "--dir-add-time-suffix";
			if (isset($config["file-per-table"]) && $config["file-per-table"]==true)
				$mysqldumpcmd[] = "--file-per-table";

			if (isset($config["days-to-keep"]))
				$mysqldumpcmd[] = "--days-to-keep ".(int)$config["days-to-keep"];

			if (isset($config["database"]))
				$mysqldumpcmd[] = "--database ".$config["database"];

			if (isset($config["tables"]))
				$mysqldumpcmd[] = "--tables ".$config["tables"];

			if (
				isset($baseconfig["compress"]) && $baseconfig["compress"]=="1" ||
				isset($config["compress"]) && $config["compress"]=="1"
			) {
				$mysqldumpcmd[] = "--compress";
			}

			if (isset($baseconfig["dump_root"])) {
				if (isset($config["my-cnf"])) {
					if (is_file($config["my-cnf"]))
						$mysqldumpcmd[] = "--my-cnf ".$config["my-cnf"];
					else if (is_file($baseconfig["dump_root"]."/".$targetname."/".$config["my-cnf"]))
						$mysqldumpcmd[] = "--my-cnf ".$baseconfig["dump_root"]."/".$targetname."/".$config["my-cnf"];
				}
				$outputdir = $baseconfig["dump_root"]."/".$targetname."/".$schedule;
				if (!is_dir($outputdir)) mkdir($outputdir,0700,true);

				$mysqldumpcmd[] = "--directory ".$outputdir;

				$mysqldumpcmd[] = "2>".$outputdir."/dump.err";
				$mysqldumpcmd[] = ">".$outputdir."/dump.log";

				if (!is_file($outputdir."/dump.err")) {
					touch($outputdir."/dump.err");
					chmod($outputdir."/dump.err",0600);
				}
				if (!is_file($outputdir."/dump.log")) {
					touch($outputdir."/dump.log");
					chmod($outputdir."/dump.log",0600);
				}
			}
			else {
				wdmysqlbk_log::log("$targetname - $schedule - dump_root must be set");
				continue;
			}

			$cmd = implode(" ", $mysqldumpcmd);

			wdmysqlbk_log::log($cmd);

			system($cmd . '&');
		}
	}
	
	// Backup all target machines
	private function _backupAll()
	{		
		$targets = wdmysqlbk_config::getTargets();

		if (count($targets) > 0)
		{
			foreach($targets as $targetname => $target)
			{
				$this->_backupTarget($targetname, $target);
			}
		}
		else
		{
			$this->_outputUse('No Targets Defined.');
		}
				
	}

	// Check specific target machine
	private function _checkTarget($targetname, array $target, $nrpe=false)
	{
		$baseconfig = wdmysqlbk_config::getBaseConfig();

		$commonconfig = array();

		$nrpeexitcode = 0;
		$nrpemsgs = array();

		foreach ($target as $schedule => $config) {
			if ($schedule=="common") {
				$commonconfig = $config;
				continue;
			}

			$config = array_merge($config, $commonconfig);

			if (!isset($config["run-hour"]) && !isset($config["run-minute"])) {
				wdmysqlbk_log::log("$targetname - $schedule - one or both of run-hour or run-minute must be set");
				continue;
			}

			//print_r($config);

			if (!isset($config["run-hour"])) $config["run-hour"] = "*";	// default to all hours
			if (!isset($config["run-minute"])) $config["run-minute"] = "0";	// default to 0 minutes

			$outputdir = $baseconfig["dump_root"]."/".$targetname."/".$schedule;

			// Determine when the last schedule should have run
			$hours = array();
			$minutes = array();

			if (strpos($config["run-hour"],",")!=false) {
				// A comma delimited list of hours
				$hours = explode(",",$config["run-hour"]);
				array_walk($hours,'intval');
			}
			else {
				if ($config["run-hour"]=="*") // all hours
					$hours[] = '*';
				else
					$hours[] = (int)$config["run-hour"];
			}

			if (strpos($config["run-minute"],",")!=false) {
				// A comma delimited list of hours
				$minutes = explode(",",$config["run-minute"]);
				array_walk($minutes,'intval');
			}
			else {
				if ($config["run-minute"]=="*") // all minutes
					$minutes[] = '*';
				else
					$minutes[] = (int)$config["run-minute"];
			}
			
			$runtimelast = 0;
			// Set timenow to the end of the current minute
			// 	for the job runtime to be compared against
			$timenow = mktime(date("H"),date("i"),59,date("n"),date("j"),date("Y"));

			foreach($hours as $hour) {
				foreach($minutes as $minute) {
					$loophours = array();	// hours the job could have run

					if (strval($hour)=='*') {
						// run time is going to be last hour or the current one
						// last hour
						// all the time values need to be saved for use with mktime later
						// 	otherwise the values will default to the current time
						//	which will be a problem when midnight has just passed
						$onehourago = mktime(date("H")-1);
						$loophours[] = array(
								date("H",$onehourago),
								date("i",$onehourago),
								date("s",$onehourago),
								date("n",$onehourago),
								date("j",$onehourago),
								date("Y",$onehourago)
								);
						// current hour
						$loophours[] = array(
								date("H"),date("i"),date("s"),date("n"),date("j"),date("Y")
								);
					}
					else {
						// run time for hour is going to be yesterday or today

						// the same hour yesterday
						// current minute date("i") is used as a placeholder and will be replaced by
						// the minute value of the inner loop when the runtime is calculated
						$houryesterday = mktime($hour,date("i"),date("s"),date("n"),date("j")-1);
						$loophours[] = array(
								date("H",$houryesterday),
								date("i",$houryesterday),
								date("s",$houryesterday),
								date("n",$houryesterday),
								date("j",$houryesterday),
								date("Y",$houryesterday)
								);
						// the hour today
						$loophours[] = array(
								$hour,date("i"),date("s"),date("n"),date("j"),date("Y")
								);
					}

					foreach ($loophours as $loophour) {
						$runtime = mktime(
								$loophour[0],
								$minute,	// replace the runtime's minute with the currnet loop's minute
								$loophour[2],$loophour[3],$loophour[4],$loophour[5]);
						
						if ($runtime<=$timenow) {
							// runtime is in the past
							if ($runtimelast==0 || $runtime>$runtimelast)
								$runtimelast=$runtime; // runtimelast isn't set yet or the new one is closer to now than the old
						}
					}
				}
			}

			$dumpdir = $outputdir."/".date("Y-m-d-Hi",$runtimelast);

			$errors = array();
			if (!is_dir($dumpdir)) {
				$errors[] = "$targetname - $schedule - dump dir missing: ".$dumpdir;
			}

			if (is_file($outputdir."/dump.log")) {
				$logstat = @stat($outputdir."/dump.log");
				if ($logstat["mtime"]<$runtimelast && abs($logstat["mtime"]-$runtimelast)>60) {
					// old log file
					$errors[] = "$targetname - $schedule - has old log ".$outputdir."/dump.log";
				}
			}
			if (is_file($outputdir."/dump.err")) {
				$logerrstat = @stat($outputdir."/dump.err");
				if ($logerrstat["size"]!=0) {
					// there are errors
					$errors[] = "$targetname - $schedule - has errors ".$outputdir."/dump.err";
				}
			}

			$haserrors = (count($errors)!=0);
			if ($nrpe) {
				if ($haserrors) {
					$nrpemsgs[] = "$targetname/$schedule: fail";
					if ($nrpeexitcode<1) $nrpeexitcode = 1;
				}
			}
			else {
				if (!$haserrors) {
					echo "OK: ".$dumpdir."\n";
				}
				else {
					echo "FAIL: ".$dumpdir."\n";
					echo "\t".implode("\n\t",$errors)."\n";
				}
			}
		}

		return array($nrpeexitcode,$nrpemsgs);
	}

	// Check all target machine dumps
	private function _checkAll($nrpe=false)
	{		
		$targets = wdmysqlbk_config::getTargets();

		if (count($targets) > 0)
		{
			$exitmsgs = array();
			$exitval = 0;
			foreach($targets as $targetname => $target)
			{
				$nrpereturn = $this->_checkTarget($targetname, $target, $nrpe);

				if ($nrpe==true) {
					if (isset($nrpereturn[1]))
						$exitmsgs[] = implode(" ",$nrpereturn[1]);
					if ($exitval==0 && isset($nrpereturn[0]) && $nrpereturn[0]!=0)
						$exitval = $nrpereturn[0];
				}
			}
			
			if ($nrpe==true) {
				if ($exitval==0) {
					echo "OK\n";
					exit(0);
				}
				else {
					echo implode(" ",$exitmsgs)."\n";
					exit($exitval);
				}
			}
		}
		else
		{
			$this->_outputUse('No Targets Defined.');
		}
				
	}
	
	// Output CLI usage statement
	private function _outputUse($error = null)
	{
		echo 'Use Error: ' . $error . "\n";
		echo 'usage	run' . "\n";
		echo 'usage	check' . "\n";
	}
	
}

?>
