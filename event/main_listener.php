<?php

/**
*
* @package Anavaro.com PM Admin
* @copyright (c) 2014 Lucifer
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

namespace anavaro\pmsearch\event;

/**
* @ignore
*/

if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* Event listener
*/
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class main_listener implements EventSubscriberInterface
{
	static public function getSubscribedEvents()
	{
		return array(
			'core.submit_pm_after'	       => 'pm_search_main',
			'core.delete_pm_before'	=> 'pm_delete_index',
		);
	}
	/**
	* Constructor
	* NOTE: The parameters of this method must match in order and type with
	* the dependencies defined in the services.yml file for this service.
	*
	* @param \phpbb\auth		$auth		Auth object
	* @param \phpbb\cache\service	$cache		Cache object
	* @param \phpbb\config	$config		Config object
	* @param \phpbb\db\driver	$db		Database object
	* @param \phpbb\request	$request	Request object
	* @param \phpbb\template	$template	Template object
	* @param \phpbb\user		$user		User object
	* @param \phpbb\content_visibility		$content_visibility	Content visibility object
	* @param \phpbb\controller\helper		$helper				Controller helper object
	* @param string			$root_path	phpBB root path
	* @param string			$php_ext	phpEx
	*/
	public function __construct(\phpbb\auth\auth $auth, \phpbb\cache\service $cache, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \phpbb\controller\helper $helper, \anavaro\pmadmin\search\fulltext_native $fulltext_search, $root_path, $php_ext, $table_prefix)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->helper = $helper;
		$this->fulltext_search = $fulltext_search;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->table_prefix = $table_prefix;
	}

	public function	pm_admin_main($event)
	{
		if ($this->config['pmsearch_pm_index'])
		{
			$data = $event['data'];
			$this->fulltext_search->index($event['mode'], (int) $data['msg_id'], $data['message'], $data['subject'], (int) $data['from_user_id']);
		}
	}

	//Let's delete indexes ot deleted PM's
	public function pm_delete_index($event)
	{
		$this->fulltext_search->index_remove($event['msg_ids']);
	}
}
