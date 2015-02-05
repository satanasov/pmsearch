<?php
/**
*
* PM Search extension for the phpBB Forum Software package.
* Swedish translation by Holger (http://www.maskinisten.net)
*
*
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

/**
* DO NOT CHANGE
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

if (empty($lang) || !is_array($lang))
{
	$lang = array();
}

// DEVELOPERS PLEASE NOTE
//
// All language files should use UTF-8 as their encoding and the files must not contain a BOM.
//
// Placeholders can now contain order information, e.g. instead of
// 'Page %s of %s' you can (and should) write 'Page %1$s of %2$s', this allows
// translators to re-order the output of data while ensuring it remains correct
//
// You do not need this where single placeholders are used, e.g. 'Message %d' is fine
// equally where a string contains only two placeholders which are used to wrap text
// in a url you again do not need to specify an order e.g., 'Click %sHERE%s' is fine
//
// Some characters you may want to copy&paste:
// ’ » “ ” …
//

$lang = array_merge($lang, array(
	// ACP general language
	'ACP_PMSEARCH_GRP'	=> 'Sök inom PM',
	'ACP_PRVOPT'	=> 'Inställningar',

	//ACP PM Admin Settings page
	'PMSEARCH_ADMIN'	=> 'Sök inom PM',
	'PMSEARCH_ADMIN_EXPLAIN'	=> 'Här kan du ändra inställningarna för sökning inom PM',
	'PMSEARCH_SETTINGS'	=> 'Inställningar',
	'PMSEARCH_PM_INDEX'	=> 'PM-indexering',
	'PMSEARCH_PM_INDEX_EXPLAIN'	=> 'Här kan du tillåta indexering<br /><b>(Varning! Sökningen fungerar ej om indexering ej utförs!)</b>',
	'PMSEARCH_PM_SEARCH'	=>	'Tillåt sökning',
	'PMSEARCH_PM_SEARCH_EXPLAIN'	=> 'Tillåt eller förbjud sökning i PM globalt',
	'DELETE_INDEX'	=> 'Radera index',
	'CREATE_INDEX'	=> 'Skapa index',

	//ACP ACL
	'ACL_U_PMSEARCH'	=> 'Användare kan söka inom PM',

	//Other ACP
	'LOG_PMSEARCH_INDEX_REMOVED'	=> '<strong>Raderade sökindex för </strong><br />» %s',
	'LOG_PMSEARCH_INDEX_CREATED'	=> '<strong>Skapade sökindex för </strong><br />» %s',
	'TOTAL_WORDS'							=> 'Totalt antal indexerade ord',
	'TOTAL_MATCHES'							=> 'Totalt antal ordrelationer',
	'SEARCH_INDEX_CREATE_REDIRECT'			=> array(
		2	=> 'Alla inlägg upp till inläggs-ID %2$d har nu indexerats, %1$d av dessa inlägg indexerades i detta steg.<br />',
	),
	'SEARCH_INDEX_CREATE_REDIRECT_RATE'		=> array(
		2	=> 'Den aktuella hastigheten för indexeringen är ungefär %1$.1f inlägg per sekund.<br />Indexeringen pågår …',
	),
));
