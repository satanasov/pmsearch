<?php
/**
*
* PM Search extension for the phpBB Forum Software package.
* French translation by Galixte (http://www.galixte.com)
*
* @copyright (c) 2014
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
	'ACP_PMSEARCH_GRP'	=> 'Rechercher dans la messagerie',
	'ACP_PRVOPT'	=> 'Paramètres',

	//ACP PM Admin Settings page
	'PMSEARCH_ADMIN'	=> 'Rechercher dans la messagerie',
	'PMSEARCH_ADMIN_EXPLAIN'	=> 'Ici vous pouvez définir certaines options en lien avec la recherche dans les messages privés des utilisateurs.',
	'PMSEARCH_SETTINGS'	=> 'Options',
	'PMSEARCH_PM_INDEX'	=> 'Indexation des message privés',
	'PMSEARCH_PM_INDEX_EXPLAIN'	=> 'Ici vous autorisez l’indexation. <b>Attention ! La recherche ne fonctionnera pas sans indexation</b>.',
	'PMSEARCH_PM_SEARCH'	=>	'Autoriser la recherche',
	'PMSEARCH_PM_SEARCH_EXPLAIN'	=> 'Autoriser (ou refuser) la recherche dans les messages privés à l’ensemble des utilisateurs.',
	'DELETE_INDEX'	=> 'Effacer tous les index',
	'CREATE_INDEX'	=> 'Créer des index',

	//ACP ACL
	'ACL_U_PMSEARCH'	=> 'L’utilisateur peut rechercher dans ses messages privés',

	//Other ACP
	'LOG_PMSEARCH_INDEX_REMOVED'	=> '<strong>L’index de recherche a été supprimé pour </strong><br />» %s',
	'LOG_PMSEARCH_INDEX_CREATED'	=> '<strong>L’index de recherche a été créé pour </strong><br />» %s',
	'TOTAL_WORDS'							=> 'Total des mots indexés',
	'TOTAL_MATCHES'							=> 'Nombre total des mots en relation',
	'SEARCH_INDEX_CREATE_REDIRECT'			=> array(
		2	=> 'Tous les messages postés jusqu’à l’ID %2$d sont maintenant indexés. Parmi eux %1$d messages viennent nouvellement d’être indexés.<br />',
	),
	'SEARCH_INDEX_CREATE_REDIRECT_RATE'		=> array(
		2	=> 'Le taux actuel de l’indexation est d’environ %1$.1f messages par deconde.<br />Indexation en cours…',
	),
));
