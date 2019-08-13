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
			'core.memberlist_view_profile'	       => 'pm_search_with_user',
		);
	}

	private $config;

	private $db;

	private $template;

	private $search_helper;

	/**
	 * Constructor
	 * NOTE: The parameters of this method must match in order and type with
	 * the dependencies defined in the services.yml file for this service.
	 *
	 * @param \phpbb\config|\phpbb\config\config                 $config    Config object
	 * @param \phpbb\db\driver|\phpbb\db\driver\driver_interface $db        Database object
	 * @param \phpbb\template|\phpbb\template\template           $template  Template object
	 * @internal param \phpbb\content_visibility $content_visibility Content visibility object
	 */
	public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db,
		\phpbb\template\template $template,
		\anavaro\pmsearch\helper $search_helper)
	{
		$this->config = $config;
		$this->db = $db;
		$this->template = $template;
		$this->search_helper = $search_helper;

		$error = false;
		$search_types = $this->search_helper->get_search_types();
		if ($this->search_helper->init_search($search_types[0], $this->fulltext_search, $error))
		{
			trigger_error($error, E_USER_WARNING);
		}
	}

	public function	pm_search_main($event)
	{
		if ($this->config['pmsearch_pm_index'])
		{
			$data = $event['data'];
			$subject = $event['subject'];
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
				WHERE ' . $this->db->sql_in_set('msg_id', array_keys($delete_rows)) .
				' GROUP BY msg_id';
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
			if (sizeof($delete_ids))
			{
				$this->fulltext_search->index_remove($delete_ids);
			}
		}
	}

	public function pm_search_with_user($event)
	{
		$target = $event['member']['user_id'];
		$url = generate_board_url() . '/ucp.php?i=\anavaro\pmsearch\ucp\ucp_pmsearch_module&mode=search&terms=nick&keywords=' . $target;
		$this->template->assign_vars(array(
			'S_SEARCH_WITH_USER'	=> true,
			'U_SEARCH_WITH_USER'	=> append_sid($url),
		));
	}
}
