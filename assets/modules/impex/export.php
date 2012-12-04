<?php
/**************************************************** 
ExportCSV (module)
v1.0 Keith Penton, KP52, December 2012
****************************************************/

$modId = intval($_REQUEST['id']); 
$errMsgs = array();

if (isset($formTpl)) {
	$html = $modx->getChunk($formTpl);
} else {
	$html = file_get_contents(dirname(__FILE__) . '/export.html');
}

// templates to be stored by name, not ID
$tNames = $modx->db->select('id, templatename', $modx->getFullTableName('site_templates'));

$templates = array();

while ($template = $modx->db->getRow($tNames)) {
	$templates[$template['id']] = $template['templatename'];
}

if (isset($_REQUEST['Go'])) {
	$fields = $_REQUEST; 

	$root = intval($fields['rootDoc']); 

	if ($root == -1) {
		$root = 0;
	} else { 
		$rootExists = is_array($modx->getPageInfo($root, 0)); 
		if (! $rootExists) {
			$root = NULL;
			$errMsgs[] = 'Invalid root resource: ' . $fields['rootDoc'] ;
			}
		}
	
	$exportDir = (!empty($fields['exportDir'])) ? $fields['exportDir'] : $exportDir; 
	
	if (substr($exportDir, 0, 6) == 'assets') {
		$exportDir = $modx->config['base_path'] . $exportDir .'/'; 
		$exportDir = str_replace('//', '/', $exportDir);
	}

	if (!file_exists($exportDir)) {
		$errMsgs[] = 'Invalid directory: ' . $fields['exportDir'];
		$exportDir = "";
	}

	$exportFile = (!empty($fields['exportFile'])) ? $fields['exportFile'] : $exportFile;

	if (preg_match('/[^\w\-\.]+/', $exportFile) > 0 OR empty($exportFile)) {
		$errMsgs[] = 'Invalid filename: ' . $exportFile;
	}

	if (empty($errMsgs)) {

		$tree = $modx->getChildIds($root);

		$exportPath = str_replace('//', '/', $exportDir . $exportFile);
		$f = fopen($exportPath, 'w') or die('Cannot open ' .$exportPath);

		$exportList = array();
		
		if (!$root == 0) {
			$info = $modx->getPageInfo($root, 0, '*');
			$exportList[] = $info['id'] . chr(9) . $info['pagetitle'];
		} else {
			$info = array('0','document','text/html','SITE TRANSPLANT');
			$info = array_merge($info, array_fill(4,4, ''),	array_fill(8,4, '0'));
			$info[12] = '1'; // isFolder
		}
		fputcsv($f, $info);
		
		foreach($tree as $page) {
			$info = $modx->getDocumentObject('id', $page); 
			$exportList[] = $info['id'] . chr(9) . $info['pagetitle']; 

	// convert template ID to its name
			$info['template'] = $templates[$info['template']];
			
			$docFields = array_slice($info, 0, 37);
			$tplVars = array_slice($info, 37);

			while ($tv = array_shift($tplVars)) {
					$docFields[] = $tv[0] . '=>' . $tv[1];
			}

			fputcsv($f, $docFields);
		}

		fclose($f);
		
		$resultsList = implode('<br />', $exportList);
		$output = '<h3>Documents exported</h3>' . $resultsList;
	} else {
		$showForm = true;
	}
} else {
	$showForm = true;
}

if ($showForm) {
	$output = $html;
	$ph = array(
		'modId' => $modId, 
		'root' => $root,
		'exportDir' => $exportDir, 
		'exportFile' => $exportFile
		);

	if (count($errMsgs) > 0) {  
		$errorList = implode('<br />', $errMsgs); 
		$ph['errorList'] = '<p class="error">'. $errorList . "</p>\n";
	}

	foreach ($ph as $key => $value) { 
	   $output = str_replace("[+$key+]", $value, $output);
	}

	//	delete undresolved placeholders
	$output = preg_replace('#(\[\+.*?\+\])#', '', $output);
}

?>