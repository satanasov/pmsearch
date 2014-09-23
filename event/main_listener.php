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
			'core.submit_pm_after'	       => 'pm_admin_main',
			'core.delete_pm_before'	=> 'pm_delete_index',
			'core.delete_user_before'	=> 'delete_users',
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
		if ($this->config['pmadmin_use_black'])
		{
			// Copy almost all of submit_pm function
			// We do not handle erasing pms here
			if ($event['mode'] == 'delete')
			{
				return false;
			}

			$current_time = time();
			
			// Collect some basic information about which tables and which rows to update/insert
			$sql_data = array();
			$root_level = 0;

			// Recipient Information
			$recipients = $to = $bcc = array();

			if ($event['mode'] != 'edit')
			{
				// Build Recipient List
				// u|g => array($user_id => 'to'|'bcc')
				$_types = array('u', 'g');
				foreach ($_types as $ug_type)
				{
					if (isset($event['data']['address_list'][$ug_type]) && sizeof($event['data']['address_list'][$ug_type]))
					{
						foreach ($event['data']['address_list'][$ug_type] as $id => $field)
						{
							$id = (int) $id;

							// Do not rely on the address list being "valid"
							if (!$id || ($ug_type == 'u' && $id == ANONYMOUS))
							{
								continue;
							}

							$field = ($field == 'to') ? 'to' : 'bcc';
							if ($ug_type == 'u')
							{
								$recipients[$id] = $field;
							}
							${$field}[] = $ug_type . '_' . $id;
						}
					}
				}

				if (isset($event['data']['address_list']['g']) && sizeof($event['data']['address_list']['g']))
				{
					// We need to check the PM status of group members (do they want to receive PM's?)
					// Only check if not a moderator or admin, since they are allowed to override this user setting
					$sql_allow_pm = (!$this->auth->acl_gets('a_', 'm_') && !$this->auth->acl_getf_global('m_')) ? ' AND u.user_allow_pm = 1' : '';

					$sql = 'SELECT u.user_type, ug.group_id, ug.user_id
						FROM ' . USERS_TABLE . ' u, ' . USER_GROUP_TABLE . ' ug
						WHERE ' . $this->db->sql_in_set('ug.group_id', array_keys($event['data']['address_list']['g'])) . '
							AND ug.user_pending = 0
							AND u.user_id = ug.user_id
							AND u.user_type IN (' . USER_NORMAL . ', ' . USER_FOUNDER . ')' .
							$sql_allow_pm;
					$result = $this->db->sql_query($sql);

					while ($row = $this->db->sql_fetchrow($result))
					{
						$field = ($event['data']['address_list']['g'][$row['group_id']] == 'to') ? 'to' : 'bcc';
						$recipients[$row['user_id']] = $field;
					}
					$this->db->sql_freeresult($result);
				}

				if (!sizeof($recipients))
				{
					trigger_error('NO_RECIPIENT');
				}
			}

			// First of all make sure the subject are having the correct length.
			$event['subject'] = truncate_string($event['subject']);
			
			$this->db->sql_transaction('begin');
			
			$sql = '';

			switch ($event['mode'])
			{
				case 'reply':
				case 'quote':
					$root_level = ($event['data']['reply_from_root_level']) ? $event['data']['reply_from_root_level'] : $event['data']['reply_from_msg_id'];

					// Set message_replied switch for this user
					$sql = 'UPDATE black_privmsgs_to
						SET pm_replied = 1
						WHERE user_id = ' . $event['data']['from_user_id'] . '
							AND msg_id = ' . $event['data']['reply_from_msg_id'];

				// no break

				case 'forward':
				case 'post':
				case 'quotepost':
					$sql_data = array(
						'root_level'		=> $root_level,
						'author_id'			=> $event['data']['from_user_id'],
						'icon_id'			=> $event['data']['icon_id'],
						'author_ip'			=> $event['data']['from_user_ip'],
						'message_time'		=> $current_time,
						'enable_bbcode'		=> $event['data']['enable_bbcode'],
						'enable_smilies'	=> $event['data']['enable_smilies'],
						'enable_magic_url'	=> $event['data']['enable_urls'],
						'enable_sig'		=> $event['data']['enable_sig'],
						'message_subject'	=> $event['subject'],
						'message_text'		=> $event['data']['message'],
						'message_attachment'=> (!empty($event['data']['attachment_data'])) ? 1 : 0,
						'bbcode_bitfield'	=> $event['data']['bbcode_bitfield'],
						'bbcode_uid'		=> $event['data']['bbcode_uid'],
						'to_address'		=> implode(':', $to),
						'bcc_address'		=> implode(':', $bcc),
						'message_reported'	=> 0,
					);
				break;

				case 'edit':
					$sql_data = array(
						'icon_id'			=> $event['data']['icon_id'],
						'message_edit_time'	=> $current_time,
						'enable_bbcode'		=> $event['data']['enable_bbcode'],
						'enable_smilies'	=> $event['data']['enable_smilies'],
						'enable_magic_url'	=> $event['data']['enable_urls'],
						'enable_sig'		=> $event['data']['enable_sig'],
						'message_subject'	=> $event['subject'],
						'message_text'		=> $event['data']['message'],
						'message_attachment'=> (!empty($event['data']['attachment_data'])) ? 1 : 0,
						'bbcode_bitfield'	=> $event['data']['bbcode_bitfield'],
						'bbcode_uid'		=> $event['data']['bbcode_uid']
					);
				break;
			}

			if (sizeof($sql_data))
			{
				$query = '';
				$sql_data['msg_id'] = $event['data']['msg_id'];
				if ($event['mode'] == 'post' || $event['mode'] == 'reply' || $event['mode'] == 'quote' || $event['mode'] == 'quotepost' || $event['mode'] == 'forward')
				{
					$this->db->sql_query('INSERT INTO black_privmsgs ' . $this->db->sql_build_array('INSERT', $sql_data));
					$msg_id = $this->db->sql_nextid();
				}
				else if ($event['mode'] == 'edit')
				{
					$sql = 'UPDATE black_privmsgs
						SET message_edit_count = message_edit_count + 1, ' . $this->db->sql_build_array('UPDATE', $sql_data) . '
						WHERE msg_id = ' . $sql_data['msg_id'];
					$this->db->sql_query($sql);
				}
			}

			if ($event['mode'] != 'edit')
			{
				if ($sql)
				{
					$this->db->sql_query($sql);
				}
				unset($sql);

				$sql_ary = array();
				foreach ($recipients as $user_id => $type)
				{
					$sql_ary[] = array(
						'msg_id'		=> $msg_id,
						'user_id'		=> (int) $user_id,
						'author_id'		=> (int) $event['data']['from_user_id'],
						'folder_id'		=> PRIVMSGS_NO_BOX,
						'pm_new'		=> 1,
						'pm_unread'		=> 1,
						'pm_forwarded'	=> ($event['mode'] == 'forward') ? 1 : 0
					);
				}

				$this->db->sql_multi_insert('black_privmsgs_to', $sql_ary);
				
				// Put PM into outbox
				$put_in_outbox = true;
				if ($put_in_outbox)
				{
					$this->db->sql_query('INSERT INTO black_privmsgs_to ' . $this->db->sql_build_array('INSERT', array(
						'msg_id'		=> (int) $msg_id,
						'user_id'		=> (int) $event['data']['from_user_id'],
						'author_id'		=> (int) $event['data']['from_user_id'],
						'folder_id'		=> PRIVMSGS_OUTBOX,
						'pm_new'		=> 0,
						'pm_unread'		=> 0,
						'pm_forwarded'	=> ($event['mode'] == 'forward') ? 1 : 0))
					);
				}
			}
			$this->db->sql_transaction('commit');
		}
		if ($this->config['pmadmin_pm_index'])
		{
			$data = $event['data'];
			$this->fulltext_search->index($event['mode'], (int) $data['msg_id'], $data['message'], $data['subject'], (int) $data['from_user_id'], 'normal');
			$this->fulltext_search->index($event['mode'], (int) $data['msg_id'], $data['message'], $data['subject'], (int) $data['from_user_id'], 'black');
		}
	}
	
	//Let's delete indexes ot deleted PM's
	public function pm_delete_index($event)
	{
		$this->fulltext_search->index_remove($event['msg_ids']);
	}
	
	public function delete_users($event)
	{
		foreach ($event['user_ids'] AS $VAR)
		{
			$sql = 'SELECT username, username_clean, user_email FROM ' . USERS_TABLE . ' WHERE user_id = ' . $VAR;
			$row = $this->db->sql_fetchrow($this->db->sql_query($sql));
			$input = 'INSERT INTO ' . $this->table_prefix . 'users_deleted SET user_id = ' . $VAR . ', username = \'' . $row['username'] . '\', username_clean = \'' . $row['username_clean'] . '\', user_email = \'' . $row['user_email'] . '\'';
			$this->db->sql_query($input);
		}
	}
}
