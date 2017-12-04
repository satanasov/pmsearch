<?php
/**
*
* PM Search extension for the phpBB Forum Software package.
* Brazilian Portuguese translation by eunaumtenhoid (c) 2017 [ver 1.0.0] (https://github.com/phpBBTraducoes)
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
	'ACP_PMSEARCH_GRP'	=> 'Pesquise em MPs',
	'ACP_PRVOPT'	=> 'Configurações',

	//ACP PM Admin Settings page
	'PMSEARCH_ADMIN'	=> 'Pesquise em MPs',
	'PMSEARCH_ADMIN_EXPLAIN'	=> 'A partir daqui, você pode definir algumas das opções relacionadas à pesquisa de usuários em MPs.',
	'PMSEARCH_SETTINGS'	=> 'Opções',
	'PMSEARCH_PM_INDEX'	=> 'Índice de MP',
	'PMSEARCH_PM_INDEX_EXPLAIN'	=> 'A partir daqui, você pode permitir a indexação<br /><b>(Aviso! A pesquisa não funcionará se você não tiver indexação)</b>.',
	'PMSEARCH_PM_SEARCH'	=>	'Permitir pesquisa',
	'PMSEARCH_PM_SEARCH_EXPLAIN'	=> 'Permitir globalmente (ou negar) pesquisa em MPs.',
	'DELETE_INDEX'	=> 'Deletar índices',
	'CREATE_INDEX'	=> 'Criar índices',

	//ACP ACL
	'ACL_U_PMSEARCH'	=> 'O usuário pode pesquisar em MPs',

	//Other ACP
	'LOG_PMSEARCH_INDEX_REMOVED'	=> '<strong>Índice de pesquisa removido por </strong><br />» %s',
	'LOG_PMSEARCH_INDEX_CREATED'	=> '<strong>Índice de pesquisa criado por </strong><br />» %s',
	'TOTAL_WORDS'							=> 'Total de palavras indexadas',
	'TOTAL_MATCHES'							=> 'Contagem total de relações de palavras',
	'SEARCH_INDEX_CREATE_REDIRECT'			=> array(
		2	=> 'Todas as postagens até o post id %2$d agora foram indexadas, das quais %1$d posts estavam dentro desta etapa.<br />',
	),
	'SEARCH_INDEX_CREATE_REDIRECT_RATE'		=> array(
		2	=> 'A taxa atual de indexação é aproximadamente %1$.1f posts por segundo.<br />Indexação em andamento...',
	),
));
