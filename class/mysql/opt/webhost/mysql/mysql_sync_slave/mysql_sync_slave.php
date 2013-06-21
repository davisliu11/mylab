#!/usr/bin/php
<?php
// ===== CONFIGURATION =====

// SYNC MODE can be CREATE (where tables on the slave do not exist)
//		or UPDATE (where tables on the slave do exist)
DEFINE("SYNC_MODE",		"UPDATE");

DEFINE("MYSQL_MASTER",		"127.0.0.1");
DEFINE("MYSQL_SLAVE",		"XXXXX");

DEFINE("MYSQL_USER",		"root");
DEFINE("MYSQL_PASSWORD",	"XXXXX");

DEFINE("TEMP_DIR",		"/var/tmp/");
DEFINE("TEMP_FILE",		TEMP_DIR."/mysql_sync_slave.".uniqid(""));

DEFINE("MYSQL",			"/usr/bin/mysql");
DEFINE("MYSQL_SLAVE_PIPE",	"| ".MYSQL." --compress -h ".MYSQL_SLAVE." -u ".MYSQL_USER." -p".MYSQL_PASSWORD);

if (SYNC_MODE=="CREATE")
	DEFINE("MYSQLDUMP_CMD",	"/usr/bin/mysqldump --quote-names --quick --max_allowed_packet=1000000000 ");
else
	DEFINE("MYSQLDUMP_CMD",	"/usr/bin/mysqldump --no-create-info --quote-names --quick --max_allowed_packet=1000000000 ");

// The maximum number of tables that are imported on the slave together at any one time
// A maximum is set to prevent multiple tables from being locked for an extended period
DEFINE("TABLE_GROUP_MAX",	600);

// DBs to skip
DEFINE("DBSKIP_FILE",		"/opt/webhost/mysql/mysql_sync_slave/skip.list");
DEFINE("ATOMIC_FILE",		"/opt/webhost/mysql/mysql_sync_slave/atomic.list");

masterReplicationRunning();

$DBSKIP = array("mysql\n","information_schema\n");
if (is_file(DBSKIP_FILE)) {
	$DBSKIP = array_merge(file(DBSKIP_FILE),$DBSKIP);
}
$dbskip_fp = fopen(DBSKIP_FILE,"a");

$DBATOMIC = array();
if (is_file(ATOMIC_FILE)) {
	$DBATOMIC = file(ATOMIC_FILE);
}

function doTrim(&$item, $key) { $item = trim($item); }
array_walk($DBATOMIC,'doTrim');

// ===== Program execution =====

// Check if slave pipe works
exec("echo \"SELECT 'ping';\" ".MYSQL_SLAVE_PIPE." 2>/dev/null",$execInfo);
if ($execInfo[1]!="ping") {
	showError("MySQL Slave Pipe does not work",$fatal=true);
}

$SKIP=true;
if (isset($argv[1])) {
	$DB_CONSTRAINT = " LIKE '".$argv[1]."'";
	if (in_array($argv[1]."\n",$DBSKIP)) {
		$index = array_search($argv[1]."\n",$DBSKIP);
		unset($DBSKIP[$index]);
	}
}
else $DB_CONSTRAINT = "";

if (isset($argv[2])) {
	$SKIP=false;
	$TABLE_CONSTRAINT = " LIKE '".$argv[2]."'";
}
else $TABLE_CONSTRAINT = "";

masterConnect();

// Determine if tables have foreign key constraints
$masterResID = masterQuery("SHOW DATABASES".$DB_CONSTRAINT);

while ($masterRes = mysql_fetch_row($masterResID)) {
	$database = $masterRes[0];

	echo $database."\n";

	// Once a database has been processed it is added to the skip list
	if ($SKIP===true && in_array($database."\n",$DBSKIP)) {
		echo "Skip: ".$database."\n";
		continue;
	}

	// Ensure we still have a connection as each loop can take a long time to process
	masterConnect();

	// Only recreate the database if there is no TABLE_CONSTRAINT, otherwise we'll lose all
	// but the selected table
	if (SYNC_MODE=="CREATE" && $TABLE_CONSTRAINT=="") {
		slaveQuery("DROP DATABASE `".mysql_real_escape_string($database)."`",false);
		slaveQuery("CREATE DATABASE `".mysql_real_escape_string($database)."`");
	}

	if (in_array($database,$DBATOMIC)) {
		echo "ATOMIC: ".$database."\n";
		$importOk = tableSync($database,"ALL");
	}
	else {
		// Group tables that need to be imported together due to foreign key constraints
		list($tableQueue,$tableGroups) = tableGroup($database);

		$importOk = true;

		// Process table groups
		while (list($groupid,$tableGroup) = each($tableGroups)) {
			$importOk = $importOk && tableSync($database,$tableGroup);
			if (!$importOk) break;

			while (list(,$table) = each($tableGroup)) {
				unset($tableQueue[$table]);
			}
		}

		// Process tables not in groups
		while (list(,$table) = each($tableQueue)) {
			$importOk = $importOk && tableSync($database,$table);
		}
	}

	// If the import was not ok we'll need to redo it
	if ($importOk) fputs($dbskip_fp,$database."\n");
}

@mysql_close($mysqlConnectionMaster);
@mysql_close($mysqlConnectionSlave);
@unlink(TEMP_FILE);
fclose($dbskip_fp);

exit;



// ===== Function Definition =====

function tableGroup($database) {
	// List of tables with Foreign Key constraints
	$GLOBALS["tableFKList"] = array();
	// List of views with dependencies
	$GLOBALS["viewList"] = array();

	// Tables grouped so that they are processed together
	$tableGroups = array();
	// Tables are ordered by depth so that parent tables are processed first

	$masterResID2 = masterQuery("SHOW TABLES FROM `".mysql_real_escape_string($database)."`".$GLOBALS["TABLE_CONSTRAINT"]);

	echo "SHOW TABLES FROM `".mysql_real_escape_string($database)."`".$GLOBALS["TABLE_CONSTRAINT"]."\n";

	// Build a table lookup for testing if a table exists during FK and View processing
	$tableLookup = array();
	if (mysql_num_rows($masterResID2)!=0) {
		while ($masterRes2 = mysql_fetch_row($masterResID2)) {
			$tableLookup[] = $masterRes2[0];
		}
		mysql_data_seek($masterResID2,0);
	}

	// A queue of all tables in this database
	// Items are removed once they're processed
	$tableQueue = array();
	// A queue of all views in this database
	$viewQueue = array();

	while ($masterRes2 = mysql_fetch_row($masterResID2)) {
	        $table = $masterRes2[0];
		$tableQueue[$table] = $table;

		$masterResID3 = masterQuery("SHOW CREATE TABLE `".mysql_real_escape_string($database)."`.`".mysql_real_escape_string($table)."`") or die("#".__LINE__." ".mysql_error());
		while ($masterRes3 = mysql_fetch_row($masterResID3)) {
			if (preg_match("/^CREATE ALGORITHM/",$masterRes3[1])) {
				// Views should be processed after all the tables
				// so review view from the queue so it can be added at the end of the function
				unset($tableQueue[$table]);
				$viewQueue[$table] = $table;
				continue;
			}

			$lines = explode("\n",$masterRes3[1]);
			foreach ($lines as $line) {
				// Group tables together that have foreign key constraints
				if (preg_match("/FOREIGN KEY .* REFERENCES `([^`]+)`/",$line,$match)) {
					$tableParent = $match[1];

					if (!isset($GLOBALS["tableFKList"][$table]))
						$GLOBALS["tableFKList"][$table] = new table($table);

					if (!isset($GLOBALS["tableFKList"][$tableParent]))
						$GLOBALS["tableFKList"][$tableParent] = new table($tableParent);

					$GLOBALS["tableFKList"][$table]->addParent($GLOBALS["tableFKList"][$tableParent]);
					$tableDepth = $GLOBALS["tableFKList"][$table]->getDepth();

					$GLOBALS["tableFKList"][$tableParent]->addChild($GLOBALS["tableFKList"][$table]);
					$GLOBALS["tableFKList"][$tableParent]->setDepth($tableDepth-1);
				}
/*
// old view code
				else if (preg_match("/SQL SECURITY DEFINER VIEW/",$line)) {
					$parentTables = explode(",",$match[1]);
				        $splits = preg_split("/ from | join /",$line);
				        array_shift($splits);

				        while (list(,$split) = each($splits)) {
						$splitB4 = $split;
				                $split = trim(str_replace("(","",preg_replace("/^`[^`]+`\./","",trim($split))));
				                if (preg_match("/`([^`]+)`/",$split,$match)){
							$tableParent = $match[1];

							if (in_array($tableParent,$tableLookup)) {
								// check table name we parsed actually exists :-)
								if (!isset($GLOBALS["tableFKList"][$table]))
									$GLOBALS["tableFKList"][$table] = new table($table);

								if (!isset($GLOBALS["tableFKList"][$tableParent]))
									$GLOBALS["tableFKList"][$tableParent] = new table($tableParent);

								$GLOBALS["tableFKList"][$table]->addParent($GLOBALS["tableFKList"][$tableParent]);
								$tableDepth = $GLOBALS["tableFKList"][$table]->getDepth();

								$GLOBALS["tableFKList"][$tableParent]->addChild($GLOBALS["tableFKList"][$table]);
								$GLOBALS["tableFKList"][$tableParent]->setDepth($tableDepth-1);
							}
				                }
        				}
				}
*/
			}
		}
	}

	/*
	// Table depth debug
	$tableNames = array_keys($GLOBALS["tableFKList"]);
	while (list(,$tableName) = each($tableNames)) {
		echo $GLOBALS["tableFKList"][$tableName]->getName()." ".$GLOBALS["tableFKList"][$tableName]->getDepth()."\n";
	}
	exit;
	*/

	// Group tables that need to be processed together
	$GLOBALS["tableGroupProcessed"] = array();	// List of processed tables so tables aren't put into multiple groups
	$tableNames = array_keys($GLOBALS["tableFKList"]);
        while (list(,$tableName) = each($tableNames)) {
		if (!in_array($tableName,$GLOBALS["tableGroupProcessed"])) {
			$tableGroupPtr = & $tableGroups[];
			$tableGroupPtr = array();
                	$GLOBALS["tableFKList"][$tableName]->groupInsert($tableGroupPtr);
		}
        }

	$tableGroupMaxReached = false;

	// Sort each table group so that a parent table is alway imported before a child
	// thereby preventing foreign key errors
	$tableGroupIDs = array_keys($tableGroups);
	while (list(,$tableGroupID) = each($tableGroupIDs)) {
		$sortArray = & $tableGroups[$tableGroupID];
	
		if (count($sortArray)>TABLE_GROUP_MAX) {
			echo "Warning: Maximum number of tables in a group reached for $database: ".implode(",",$sortArray)."\n";
			$tableGroupMaxReached = true;
		}
		
		usort($sortArray, "parentChildSort");
	}

	if ($tableGroupMaxReached) {
		echo "Error: database $database reached table group max "+TABLE_GROUP_MAX+".  Increase maximum to continue.\n";
		exit;
	}

	// $views should be processed after all the tables
	$tableQueue += $viewQueue;

	return array($tableQueue,$tableGroups);
}

class table {
	function table($tableName) {
		$this->tableName = $tableName;
		$this->tableParents = array();
		$this->tableChildren = array();
		$this->depth = null;
		$this->groupID = null;
	}

	function getName() {
		return $this->tableName;
	}

	function addParent(& $table) {
		$this->tableParents[] = & $table;
	}

	function addChild(& $table) {
		$this->tableChildren[] = & $table;		
	}

	function hasChild($tableName) {
		$childCount = count($this->tableChildren);
		for ($i=0;$i<$childCount;$i++) {
			if ($this->tableChildren[$i]->getName() == $tableName) return true;
		}
		return false;
	}

	function groupInsert(& $tableGroupPtr) {
		// Return if the table has already been processed
		if (in_array($this->tableName,$tableGroupPtr)) return true;

		// Add the current table to the group
		$tableGroupPtr[] = $this->tableName;

		// Add the table's children to the group
		$childCount = count($this->tableChildren);
		for ($i=0;$i<$childCount;$i++) {
			$this->tableChildren[$i]->groupInsert($tableGroupPtr);
		}

		// Add the table's parents to the group
		$parentCount = count($this->tableParents);
		for ($i=0;$i<$parentCount;$i++) {
			$this->tableParents[$i]->groupInsert($tableGroupPtr);
		}

		// Mark the table as processed so multiple groups are not created
		$GLOBALS["tableGroupProcessed"][] = $this->tableName;

		return true;
	}

	function getDepth() {
		// If depth is not set yet then set it to 0
		// Parent tables will be -1 and children with be 1 etc
		if ($this->depth===null) $this->depth = 0;
		return $this->depth;
	}
	
	function setDepth($depth) {
		$this->depth = $depth;
	}
}

function parentChildSort($a,$b) {
	$aDepth = $GLOBALS["tableFKList"][$a]->getDepth();
	$bDepth = $GLOBALS["tableFKList"][$b]->getDepth();

	if ($aDepth<$bDepth) return -1;
	else if ($aDepth>$bDepth) return 1;
	else return 0;
}

function dbSync($database) {
	masterConnect();
	slaveConnect();	

	slaveWaitToCatchUp();

	$masterResID2 = masterQuery("SHOW TABLES FROM `".mysql_real_escape_string($database)."`".$GLOBALS["TABLE_CONSTRAINT"]);
	// Get READ lock on all the tables
	while ($masterRes2 = mysql_fetch_row($masterResID2)) {
		$table = $masterRes2[0];
		masterQuery("LOCK TABLES `".mysql_real_escape_string($database)."`.`".mysql_real_escape_string($table)."` READ") or die("#".__LINE__." ".mysql_error());
	}

	$errFile = TEMP_DIR."/mysql_sync_slave.".uniqid("").".err";
	doDump($errFile,$database);

	if (filesize($errFile)>0) {
                showError("Slave Import: ".implode("",file($errFile)),$fatal=false);
                $syncOk = false;
        }
        unlink($errFile);

}


function tableSync($database,$tables) {
	$allTables = false;

	if (!is_array($tables)) {
		if ($tables=="ALL") {
			$allTables = true;
			$masterResID2 = masterQuery("SHOW TABLES FROM `".mysql_real_escape_string($database)."`".$GLOBALS["TABLE_CONSTRAINT"]);
			while ($masterRes2 = mysql_fetch_row($masterResID2)) {
				$table = $masterRes2[0];
				$tableArray[] = $table;
			}
			$tables = $tableArray;
		}
		else {
			$tableArray = array($tables);
			$tables = $tableArray;
		}
	}

	$syncOk = true;

	masterConnect();
	slaveConnect();	

	slaveWaitToCatchUp();

	while (list(,$table) = each($tables)) {
		echo $database.".".$table."\n";
		masterQuery("LOCK TABLES `".mysql_real_escape_string($database)."`.`".mysql_real_escape_string($table)."` READ") or die("#".__LINE__." ".mysql_error());
	}
	reset($tables);

	$errFile = TEMP_DIR."/mysql_sync_slave.".uniqid("").".err";
	$fp = fopen(TEMP_FILE,"w");
	while (list(,$table) = each($tables)) {
		if (SYNC_MODE!="CREATE")
			fputs($fp,"DELETE FROM `".mysql_real_escape_string($database)."`.`".mysql_real_escape_string($table)."`;\n");
	}
	fclose($fp);
	reset($tables);

	if ($allTables) {
		doDump($errFile,$database);
	}
	else {
		while (list(,$table) = each($tables)) {
			doDump($errFile,$database,$table);
		}
		reset($tables);
	}

	if (filesize($errFile)>0) {
                showError("Slave Import: ".implode("",file($errFile)),$fatal=false);
                $syncOk = false;
        }
        unlink($errFile);

	//echo implode("",file(TEMP_FILE))."\n";

	slaveQuery("SLAVE STOP");

	masterQuery("UNLOCK TABLES");

	if (slaveReplicationError()) {
		slaveQuery("SLAVE START");
		showError("Error on slave. Exiting.",$fatal=true);
	}

	// Disconnect as the slave pipe may take a long time
	masterDisconnect();
	slaveDisconnect();

	list($pipeOk,$pipeMsg) = slavePipe(TEMP_FILE,$database);
	if (!$pipeOk) {
		if (preg_match("/Cannot delete or update a parent row: a foreign key constraint fails/",$pipeMsg)) {
			echo "Msg: Table create failed due to foreign key constraint.  Using table truncate instead.\n";
		}
		else {
			slavePing();
			slaveQuery("SLAVE START");
			showError("Slave Pipe: ".$pipeMsg,$fatal=true);
		}
	}

	$syncOk = $syncOk && $pipeOk;

	slaveConnect();	

	slaveQuery("SLAVE START");

	slaveDisconnect();

	return $syncOk;
}

function doDump($errFile,$database,$table=null) {
        echo "Dumping";

	$cmd = MYSQLDUMP_CMD." '".$database."'";
	if (!is_null($table)) $cmd .= " '".$table."'";
	$cmd .= ">> ".TEMP_FILE." 2> ".$errFile;

	$descriptorspec = array(
		0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
		1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
		2 => array("file", "/tmp/error-output.txt", "a") // stderr is a file to write to
	);

        $process = proc_open($cmd, $descriptorspec, $pipes);

        if (is_resource($process)) {
                while (true) {
                        $status = proc_get_status($process);
                        if (!$status["running"]) break;
                        echo ".";
                        sleep(5);
			// Keep the connections alive
                        slavePing();
                        masterPing();
                }
        }
        echo "\n";
}

function slaveWaitToCatchUp() {
	$waitMax = 10;
	$waitCount = 0;
	while (++$waitCount<$waitMax && !slaveReplicationRunning()) {
		showError("Slave not running, waiting for it to catch up",$fatal=false);
		sleep(1);
	}
}

function masterReplicationRunning() {
	$masterRes = masterQuery("SHOW SLAVE STATUS");
	$masterInfo = mysql_fetch_array($masterRes,MYSQL_ASSOC);

	if (isset($masterInfo["Exec_master_log_pos"])) {
		// MySQL 4
		if ($masterInfo["Slave_IO_Running"]=="Yes" || $masterInfo["Slave_SQL_Running"]=="Yes") {
			echo "Master's slave IO is running!\n";
			echo "This script should not be run while the master's slave is running\n";
			echo "IMPORTANT: Once you have run the script you MUST set the master details to be the current log and position for the slave as it will have changed\n";
			echo "If you don't do the above, the updates that were just run on the slave will be repeated on the master (replication death-spiral!!!!!)\n\n";
			echo "Exiting... Talk to Steve\n\n";
			exit;
		}
	}
	else if (isset($masterInfo["Exec_Master_Log_Pos"])) {
		// MySQL 5
		if ($masterInfo["Slave_IO_Running"]=="Yes" || $masterInfo["Slave_SQL_Running"]=="Yes") {
			echo "Master's slave IO is running!\n";
			echo "This script should not be run while the master's slave is running\n";
			echo "IMPORTANT: Once you have run the script you MUST set the master details to be the current log and position for the slave as it will have changed\n";
			echo "If you don't do the above, the updates that were just run on the slave will be repeated on the master (replication death-spiral!!!!!)\n\n";
			echo "Exiting... Talk to Steve\n\n";
			exit;
		}
	}
}

function slaveReplicationRunning() {
	return 1;

	$slaveRes = slaveQuery("SHOW SLAVE STATUS");
	$slaveInfo = mysql_fetch_array($slaveRes,MYSQL_ASSOC);

	if (isset($slaveInfo["Exec_master_log_pos"])) {
		// MySQL 4
		return $slaveInfo["Slave_IO_Running"]=="Yes" && $slaveInfo["Slave_SQL_Running"]=="Yes" &&
			$slaveInfo["Exec_master_log_pos"]==$slaveInfo["Read_Master_Log_Pos"];
	}
	else if (isset($slaveInfo["Exec_Master_Log_Pos"])) {
		// MySQL 5
		return $slaveInfo["Slave_IO_Running"]=="Yes" && $slaveInfo["Slave_SQL_Running"]=="Yes" &&
			$slaveInfo["Exec_Master_Log_Pos"]==$slaveInfo["Read_Master_Log_Pos"];
	}
}

function slaveConnect() {
	if (!isset($GLOBALS["mysqlConnectionSlave"]) || !@mysql_ping($GLOBALS["mysqlConnectionSlave"]))
		$GLOBALS["mysqlConnectionSlave"] = mysql_connect(MYSQL_SLAVE,MYSQL_USER,MYSQL_PASSWORD) or die(mysql_error());
}

function slaveDisconnect() {
	@mysql_close($GLOBALS["mysqlConnectionSlave"]);
}

function slaveReplicationError() {
	$slaveRes = slaveQuery("SHOW SLAVE STATUS");
	$slaveInfo = mysql_fetch_array($slaveRes,MYSQL_ASSOC);

	if (isset($slaveInfo["Last_errno"]))
		return $slaveInfo["Last_errno"]!="0";
	if (isset($slaveInfo["Last_Errno"]))
		return $slaveInfo["Last_Errno"]!="0";
}

function slaveQuery($qry,$fatal=true) {
	slaveConnect();
	$queryid = mysql_query($qry,$GLOBALS["mysqlConnectionSlave"]);
	if ($fatal==true && $queryid===false) die("#".__LINE__." ".mysql_error()." ".$qry);

	return $queryid;
}

function slavePing() {
	slaveQuery("SELECT 'ping'");
}

function masterConnect() {
	if (!isset($GLOBALS["mysqlConnectionMaster"]) || !@mysql_ping($GLOBALS["mysqlConnectionMaster"]))
		$GLOBALS["mysqlConnectionMaster"] = mysql_connect(MYSQL_MASTER,MYSQL_USER,MYSQL_PASSWORD) or die(mysql_error());
}

function masterDisconnect() {
	@mysql_close($GLOBALS["mysqlConnectionMaster"]);
}

function masterQuery($qry) {
	masterConnect();
	$queryid = mysql_query($qry,$GLOBALS["mysqlConnectionMaster"]) or showError("#".__LINE__." ".mysql_error()." ".$qry,$fatal=true);

	return $queryid;
}

function masterPing() {
	masterQuery("SELECT 'ping'");
}

function slavePipe($file,$database) {
	$errFile = TEMP_DIR."/mysql_sync_slave.".uniqid("").".err";

	system("cat ".$file." ".MYSQL_SLAVE_PIPE." '".$database."' 2> ".$errFile);

	//echo "cat ".$file." ".MYSQL_SLAVE_PIPE." '".$database."'\n";

	if (filesize($errFile)>0) {
		$error = implode("",file($errFile));
		unlink($errFile);
		//echo $file."\n";
		return array(false,$error);
	}
	else {
		unlink($errFile);
		return array(true,"");
	}
}

function showError($err,$fatal=false) {
	echo "Error: $err\n";
	if ($fatal) exit;
}
?>
