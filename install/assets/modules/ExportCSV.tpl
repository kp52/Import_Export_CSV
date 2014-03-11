/**
 * ExportCSV
 *
 * Export sub-tree as CSV file
 *
 * @category	module
 * @version		1.1
 * @internal	@modx_category Manager and Admin
 * @internal	@properties &exportDir=Export to;string;assets/site/ &exportFile=File;string;sitePages.csv &root=Root;int; &formTpl=Input Form;string;
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 */
/**************************************************** 
ExportCSV (module)

v1.1 Keith Penton, KP52, December 2012

Create CSV file from resource given as root and all its children
Preserve data from site_content table, with template by name, plus 
template variables listed by name with current values. Intended for use with 
module ImportCSV to copy blocks of pages across sites. 
NB export is complete set of table data, but import is selective.

Input: ID of root document (or -1 to export all resources);
Export directory (full path, or path beginning "assets", slash is optional)
Export filename

Configuration: preset parameters using: 
	&exportDir=Export to;string;assets/export/ 
	&exportFile=File;string;myPages.csv 
	&root=Root;int; 

Form to input parameters defined in export.html or chunk named in config:
	&formTpl=Input Form;string;
Placeholders: 
	[+modId+] module ID (hidden field, set by export.php)
	[+errors+] error messages
	[+root+], [+exportDir+], [+exportFile+] current value of parameters

Requires: export.html or chunk for parameter input;
*******************************************************/

require_once $modx->config['base_path'] . 'assets/modules/impex/export.php';

return $output;
