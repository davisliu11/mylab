<?php
class cliTable {
	var $cols;
	var $rows;
	var $currentrow;

	var $colpad;

	function cliTable($colpad=5) {
		$this->cols = array();
		$this->rows = array();

		$this->colpad = $colpad;
	}

	function addColumn($label) {
		$this->cols[] = array($label,strlen($label));
	}

	function addEntry($rowid,$value) {
		$col = count($this->currentrow);	// column is the next available
		$this->currentrow[$col] = $value;
		$valuelength = strlen($value);
		if ($valuelength > $this->cols[$col][1]) $this->cols[$col][1] = $valuelength;	// record the maximum length of column
	}

	function newRow() {
		$this->currentrow = & $this->rows[];
	}

	function getTable() {
		$table = "";
		$header = "";

		$colsizelookup = array();
		$colcount = count($this->cols);
		for ($i=0;$i<$colcount;$i++) {
			$label = $this->cols[$i][0];
			$colsize = $this->cols[$i][1];

			$colsizelookup[$i] = $colsize;

			$header .= str_pad($label, $colsize+$this->colpad);
		}

		$table .= preg_replace("/./","-",$header)."\n";
		$table .= "$header\n";
		$table .= preg_replace("/./","-",$header)."\n";
		
		$rowcount = count($this->rows);
		for ($i=0;$i<$rowcount;$i++) {
			for ($j=0;$j<$colcount;$j++) {
				$table .= str_pad($this->rows[$i][$j], $colsizelookup[$j]+$this->colpad);
			}
			$table .= "\n";
		}

		return $table;
	}
}
?>
