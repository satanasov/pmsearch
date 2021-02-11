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
	'ACP_PMSEARCH_GRP'	=> 'Поиск по ЛС',
	'ACP_PRVOPT'	=> 'Настройки',

	//ACP PM Admin Settings page
	'PMSEARCH_ADMIN'	=> 'Поиск по ЛС',
	'PMSEARCH_ADMIN_EXPLAIN'	=> 'Здесь вы можете настроить параметры пользовательского поиска по личным сообщениям',
	'PMSEARCH_SETTINGS'	=> 'Параметры',
	'PMSEARCH_PM_INDEX'	=> 'Индексация ЛС',
	'PMSEARCH_PM_INDEX_EXPLAIN'	=> 'Здесь вы можете включить индексирование <br /><b>(Внимание! Поиск не будет работать, если индексирование отключено)</b>.',
	'PMSEARCH_PM_SEARCH'	=>	'Разрешить поиск',
	'PMSEARCH_PM_SEARCH_EXPLAIN'	=> 'Глобальное разрешение (или запрет) на поиск по ЛС',
	'DELETE_INDEX'	=> 'Удалить индексы',
	'CREATE_INDEX'	=> 'Создать индексы',

	//ACP ACL
	'ACL_U_PMSEARCH'	=> 'Пользователь может искать ЛС',

	//Other ACP
	'LOG_PMSEARCH_INDEX_REMOVED'	=> '<strong>Удалён индекс для </strong><br />» %s',
	'LOG_PMSEARCH_INDEX_CREATED'	=> '<strong>Создан индекс для </strong><br />» %s',
	'TOTAL_WORDS'							=> 'Всего проиндексировано слов',
	'TOTAL_MATCHES'							=> 'Всего совпадений',
	'SEARCH_INDEX_CREATE_REDIRECT'			=> array(
		2	=> 'Сообщения вплоть до %2$d проиндексированы, из них %1$d за текущий шаг.<br />',
	),
	'SEARCH_INDEX_CREATE_REDIRECT_RATE'		=> array(
		2	=> 'Текущая скорость индексирования &mdash; %1$.1f ЛС в секунду.<br />Индексирование продолжается…',
	),
));
