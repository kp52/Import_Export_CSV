<?php
/******************************************
ImportCSV (module)
v1.0 Keith Penton, KP52, December 2012
*******************************************/

$importDir = $modx->config['base_path'] . 'assets/import/';
$modId = intval($_REQUEST['id']); 

if (isset($formTpl)) {
	$html = $modx->getChunk($formTpl);
} else {
	$html = file_get_contents(dirname(__FILE__) . '/import.html');
}

if (empty($html)) {
	die('No valid chunk name for form, no import.html in folder. Mission impossible.');
}

// get resource files
$optList = getInputFiles('csv', $importDir, $dataFile);
if (!empty($optList)) {
	$fileChoice = implode($optList);	
} else {
	$fileChoice = '<h3>No CSV files have been named or placed in assets/import folder</h3>';
}

// get Tables
$optList = getInputFiles('sql', $importDir, $tableFile);
if (!empty($optList)) {
	$tableChoice = implode($optList);	
} else {
	$tableChoice = '<h3>No SQL files have been named or placed in assets/import folder</h3>';
}

// Process submitted form
if (isset($_REQUEST['Go'])) {
	$fields = $_REQUEST; 

	$report  = ProcessForm('csv', $fields);
	$report .= ProcessForm('sql', $fields);
	$report .= "Refresh site tree to view result</p>\n";

}  else {
	$report = $html;
	$ph = array(
		'modId' => $modId, 
		'fileChoice' => $fileChoice, 
		'tableChoice' => $tableChoice
		);

	foreach ($ph as $key => $value) { 
	   $report = str_replace("[+$key+]", $value, $report);
	}
	
//	comment out undresolved placeholders
	$report = preg_replace('#(\[\+.*?\+\])#', '<!-- $1 -->', $report);
}

return $report;

/*************************************************************************/

function getInputFiles($fileType, $importDir, $dataFile=NULL) {

// template for radio button
	switch ($fileType) {
		case 'csv':
			$opt = '<input id="file%d" class="radio-button" name="importFile" type="radio" value="%s" /><label for="file%d"> %s </label><br />';
			$noneMsg = 'No resource files';
			$input = 'inputCSV';
			break;
		case 'sql':
			$opt = '<input id="table%d" class="radio-button" name="importTable" type="radio" value="%s" /><label for="table%d"> %s </label><br />';
			$noneMsg = 'No table files';
			$input = 'inputSQL';
			break;
		default: die("We don't do $fileType types of file");
	}

	$fileList = glob($importDir . '*.' . $fileType);

	$buttonIndex = 0;
	$optList = array();

	if (is_array($fileList) && count($fileList) > 0) {
		foreach ($fileList as $fileListFile) {
			$fileName = basename($fileListFile);
			$optList[] = sprintf($opt, ++$buttonIndex, $fileListFile, $buttonIndex, $fileName);
		}
	}

// add in files set in configuration
	if (isset($dataFile) && !in_array($dataFile, $fileList)) {
		$optList[] = sprintf($opt, ++$buttonIndex, $dataFile, $buttonIndex, $dataFile);
	}
	
	$inputField = '<input type="text" name="' . $input . '" placeholder="Enter full path to file" />';
	$inputFile = sprintf($opt, ++$buttonIndex, $input, $buttonIndex, "");
	$inputFile = str_replace('<br />', $inputField . '<br />', $inputFile);
	$optList[] = $inputFile;

	if (!empty($optList)) {
		$checked = 'none" checked="checked';
		$optList[] = sprintf($opt, ++$buttonIndex, $checked, $buttonIndex, $noneMsg);
	}

	return $optList;	
}

function ProcessForm($fileType, $fields) { 
	
	switch ($fileType) {
		case 'csv':
			$tryFile = $fields['importFile'];
			$inputFile = 'inputCSV';
			$inputType = ' resource files ';
			$process = 'processPages';
			break;
		case 'sql':
			$tryFile = $fields['importTable'];
			$inputFile = 'inputSQL';
			$inputType = ' database table files ';
			$process = 'processTables';
			break;
	}

	if (!empty($tryFile)) { 
		if ($tryFile == $inputFile) {
			$tryFile = $fields[$inputFile];
			if (empty($tryFile)) {
				$tryFile = 'none';
			}
		}

		if ($tryFile == 'none') {
			$report .= "No $inputType selected for import <br />\n";

		} else if (file_exists($tryFile)) {
			$report .= call_user_func($process, $tryFile);

		} else {
			$report .= '<b>File not found: </b>' . $tryFile . "<br />\n";
		}
	}

	return $report;
}

function processPages($dataFile) {
	global $modx;
	$records = array();
	$idSwaps = array();

// get all CSV records into an array, original ID as key
	$f = fopen($dataFile, 'r');
	while ($record = fgetcsv($f)) {
		$records[$record[0]] = $record;
	}
	fclose($f);

// get ID of new root, assume remainder will be sequential
	$doc = new Document();
	$doc->save();
	$newRoot = $doc->get('id');
	$newId = $newRoot;

// create index to substitute new IDs in references
	foreach ($records as $rec) {
		$idSwaps[$rec[0]] = $newId++;
	}

// replace link IDs for Ditto parents, PubKit folder & postid params
	foreach ($records as $rec) {
		$content = $rec[14];

		if (preg_match_all('/(parents|folder|postid)=`(\d+)`/', $content, $matches) > 0)  {
			$oldIds = $matches[2];
// new for old ID references, placeholders for backticks to avoid mismatches in next step
			foreach($oldIds as $oldId) {
				$content = str_replace('`' . $oldId . '`', '%%' . $idSwaps[$oldId] . '%%', $content);
			}
		}

		if (preg_match('/postid=`.+ \d+[,:`]/', $content, $matches) > 0)  {
			preg_match_all('/(\d+)([,:`])/', $matches[0], $subMatches);
			$oldIds = $subMatches[1];
			foreach($oldIds as $oldId) {
				$newRef = $idSwaps[$oldId];
// space is separator for each tag/ID pair
				$content = str_replace(' ' . $oldId, ' ' . $newRef, $content);
			}
		}

		$content = str_replace('%%', '`', $content);

// populate new documents, updating parent field. Root doc is already open
		$doc->set('content', $modx->db->escape($content));
		$doc->set('parent', $idSwaps[$rec[11]]);

		$doc->set('pagetitle', $modx->db->escape(htmlspecialchars($rec[3])));
		$doc->set('longtitle', $modx->db->escape(htmlspecialchars($rec[4])));
		$doc->set('alias', $rec[6]);
		$doc->set('published', $rec[8]);
		$doc->set('pub_date', $rec[9]);
		$doc->set('unpub_date', $rec[10]);
		$doc->set('isfolder', $rec[12]);
		$doc->set('introtext', $modx->db->escape(htmlspecialchars($rec[13])));
		$doc->set('richtext', $rec[15]);
		$doc->set('template', $rec[16]);
		$doc->set('menuindex', $rec[17]);
		$doc->set('menutitle', $modx->db->escape(htmlspecialchars($rec[29])));
		$doc->set('hidemenu', $rec[36]);
// populate TVs, saved as tvname=>value
		$tvs = array_slice($rec, 37);
		while ($tv = array_shift($tvs)) {
			$tvPair = explode('=>', $tv);
			$tvName = 'tv' . $tvPair[0];
			$doc->set($tvName, $modx->db->escape($tvPair[1]));
		}
		$doc->Save();

		$doc = new Document;
	}

// bring root document to top of tree
	$doc = new Document($newRoot, 'parent, pagetitle');
	$doc->set('parent', 0);
	$doc->Save();

	$report = '<p>' . count($idSwaps) . " pages added under root id $newRoot <br />\n";
	if ($doc->get('pagetitle') == 'SITE TRANSPLANT') {
		$report .= "<p>Use standard MODX manager tools to move subfolders to site root</p>";		
	}

	return $report;
}

function processTables($tables) {
	global $modx, $table_prefix;

	$dump = file_get_contents ($tables);
	$dump = str_replace('{PREFIX}', $table_prefix, $dump);
	$sql = explode(';', $dump);

	$n = count ($sql) - 1;
	for ($i = 0; $i < $n; $i++) {
		$query = $sql[$i];
		$result = $modx->db->query ($query);
	}

	$report = "Tables imported. ";

	return $report;
}

?>
