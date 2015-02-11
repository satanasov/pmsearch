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
	'PMSEARCH_TITLE'	=> 'Rechercher dans la messagerie',
	'PMSEARCH_KEYWORDS_EXPLAIN'	=>	'Placez un <b>+</b> devant un mot qui doit être trouvé et un <b>-</b> devant un mot qui doit être exclu. Tapez une suite de mots séparés par des <b>|</b> entre crochets si uniquement un des mots doit être trouvé. Utilisez un * comme joker pour des recherches partielles.',
	'SEARCH_ALL_TERMS'	=>	'Rechercher tous les termes ou utiliser une question comme entrée',
	'SEARCH_ANY_TERMS'	=>	'Rechercher n’importe lequel de ces termes',
	'NO_RESULTS_FOUND'	=> 'Aucun message ne correspond à vos critères de recherche.',
	'SEARCH_PMS'	=> 'Rechercher',
	'ACCESS_DENIED'	=> 'Vous n’avez pas l’autorisation pour chercher dans les messages privés',
));
