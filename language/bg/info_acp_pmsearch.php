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
	'ACP_PMSEARCH_GRP'	=> 'Търсене в лични съобщения',
	'ACP_PRVOPT'	=> 'Настройки',

	//ACP PM Admin Settings page
	'PMSEARCH_ADMIN'	=> 'Търсене в лични съобщения',
	'PMSEARCH_ADMIN_EXPLAIN'	=> 'От тук можете да настроите някои от нещата свързани с потребителското търсене в лични съобщения',
	'PMSEARCH_SETTINGS'	=> 'Настройки',
	'PMSEARCH_PM_INDEX'	=> 'Индексация на лични съобщения',
	'PMSEARCH_PM_INDEX_EXPLAIN'	=> 'От тук можете да пуснете индексацята на лични съобщения <br /><b>(Внимание! Търсенето няма да раоти без активирана индексация)</b>',
	'PMSEARCH_PM_SEARCH'	=>	'Разреши търсенето',
	'PMSEARCH_PM_SEARCH_EXPLAIN'	=> 'Глобално разрешаване (или забраняване) на търсенето в личните съобщения',

	//ACP ACL
	'ACL_U_PMSEARCH'	=> 'Потребителя може да търси в личните си съобщения',
));
