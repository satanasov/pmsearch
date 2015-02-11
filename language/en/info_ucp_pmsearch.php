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
	'PMSEARCH_TITLE'	=> 'Search in PMs',
	'PMSEARCH_KEYWORDS_EXPLAIN'	=>	'Place + in front of a word which must be found and - in front of a word which must not be found. Put a list of words separated by | into brackets if only one of the words must be found. Use * as a wildcard for partial matches.',
	'SEARCH_ALL_TERMS'	=>	' Search for all terms or use query as entered',
	'SEARCH_ANY_TERMS'	=>	'Search for any terms',
	'NO_RESULTS_FOUND'	=> 'No results found.',
	'SEARCH_PMS'	=> 'Search PMs',
	'ACCESS_DENIED'	=> 'You have no authority to search in PMs',
));
