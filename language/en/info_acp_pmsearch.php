<?php
/**
*
* PM Search extension for the phpBB Forum Software package.
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
	// ACP general langauge
	'ACP_PMSEARCH_GRP'	=> 'Search in PMs',
	'ACP_PRVOPT'	=> 'Settings',

	//ACP PM Admin Settings page
	'PMSEARCH_ADMIN'	=> 'Search in PMs',
	'PMSEARCH_ADMIN_EXPLAIN'	=> 'From here you can set some of options related to user search in PMs.',
	'PMSEARCH_SETTINGS'	=> 'Options',
	'PMSEARCH_PM_INDEX'	=> 'PM Indexing',
	'PMSEARCH_PM_INDEX_EXPLAIN'	=> 'From here you can allow indexing <br /><b>(Warning! Search will not work if you have no indexing)</b>.',
	'PMSEARCH_PM_SEARCH'	=>	'Allow search',
	'PMSEARCH_PM_SEARCH_EXPLAIN'	=> 'Globally allow (or deny) search in PMs.',
	'DELETE_INDEX'	=> 'Delete indexes',
	'CREATE_INDEX'	=> 'Create indexes',

	//ACP ACL
	'ACL_U_PMSEARCH'	=> 'User can search in PMs',

	//Other ACP
	'LOG_PMSEARCH_INDEX_REMOVED'	=> '<strong>Removed search index for </strong><br />» %s',
	'LOG_PMSEARCH_INDEX_CREATED'	=> '<strong>Created search index for </strong><br />» %s',
	'TOTAL_WORDS'							=> 'Total indexed words',
	'TOTAL_MATCHES'							=> 'Total count of word relations',
	'SEARCH_INDEX_CREATE_REDIRECT'			=> array(
		2	=> 'All posts up to post id %2$d have now been indexed, of which %1$d posts were within this step.<br />',
	),
	'SEARCH_INDEX_CREATE_REDIRECT_RATE'		=> array(
		2	=> 'The current rate of indexing is approximately %1$.1f posts per second.<br />Indexing in progress…',
	),
));
