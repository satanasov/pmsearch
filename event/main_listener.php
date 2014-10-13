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
	public function __construct(\phpbb\auth\auth $auth, \phpbb\cache\service $cache, \phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\request\request $request, \phpbb\template\template $template, \phpbb\user $user, \anavaro\pmsearch\search\fulltext_native $fulltext_search, $root_path, $php_ext, $table_prefix)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->db = $db;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->fulltext_search = $fulltext_search;
		$this->root_path = $root_path;
		$this->php_ext = $php_ext;
		$this->table_prefix = $table_prefix;
	}

	public function	pm_search_main($event)
	{
		if ($this->config['pmsearch_pm_index'])
		{
			$data = $event['data'];
			$subject = $event['subject'];
			var_dump($event['subject']);
			$this->fulltext_search->index($event['mode'], (int) $data['msg_id'], $data['message'], $subject, (int) $data['from_user_id']);
		}
	}

	//Let's delete indexes of deleted PM's
	public function pm_delete_index($event)
	{
		// Get PM Information for later deleting
		$sql = 'SELECT msg_id, pm_unread, pm_new
			FROM ' . PRIVMSGS_TO_TABLE . '
			WHERE ' . $this->db->sql_in_set('msg_id', array_map('intval', $event['msg_ids'])) . '
				AND folder_id = ' . $event['folder_id'] . '
				AND user_id = ' . $event['user_id'];
		$result = $this->db->sql_query($sql);

		$delete_rows = array();
		while ($row = $this->db->sql_fetchrow($result))
		{
			$delete_rows[$row['msg_id']] = 1;
		}
		$this->db->sql_freeresult($result);

		if (!sizeof($delete_rows))
		{
			return false;
		}
		// If no one has read it (the delete_pm function will delete all data
		if ($event['folder_id'] == PRIVMSGS_OUTBOX)
		{
			$this->fulltext_search->index_remove($event['msg_ids']);
		}
		else
		{
			$sql = 'SELECT COUNT(msg_id) as count, msg_id
				FROM ' . PRIVMSGS_TO_TABLE . '
				WHERE ' . $this->db->sql_in_set('msg_id', array_keys($delete_rows));
			$result = $this->db->sql_query($sql);
			while ($row = $this->db->sql_fetchrow($result))
			{
				if ($row['count'] == 2)
				{
					unset($delete_rows[$row['msg_id']]);
				}
			}
			$this->db->sql_freeresult($result);

			$delete_ids = array_keys($delete_rows);
			var_dump($delete_ids);
			if (sizeof($delete_ids))
			{
				$this->fulltext_search->index_remove($delete_ids);
			}
		}
	}
}
