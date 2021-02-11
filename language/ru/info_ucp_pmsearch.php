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
	'PMSEARCH_TITLE'	=> 'Поиск по ЛС',
	'PMSEARCH_KEYWORDS_EXPLAIN'	=>	'Добавьте знак + перед словом, которое должно быть найдено, и знак - перед словом, которого не должно быть. Поместите список слов, разделённых вертикальной чертой | в скобки, если должно быть найдено только одно из них. Используйте * для частичного совпадения.',
	'SEARCH_ALL_TERMS'	=>	'Поиск всех слов',
	'SEARCH_ANY_TERMS'	=>	'Поиск любого слова',
	'NO_RESULTS_FOUND'	=> 'Ничего не найдено.',
	'SEARCH_PMS'	=> 'Найти сообщения',
	'ACCESS_DENIED'	=> 'Вам не разрешён поиск по личным сообщениям',
));
