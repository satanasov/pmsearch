<?php

/**
*
* @package Anavaro.com PM Search
* @copyright (c) 2013 Lucifer
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* @ignore
*/
namespace anavaro\pmsearch\ucp;

class ucp_pmsearch_module
{
	var $u_action;
	function var_display($i)
	{
		echo "<pre>";
		print_r($i);
		echo "</pre>";
	}
	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $cache, $request, $phpbb_container;
		global $config, $SID, $phpbb_root_path, $phpbb_admin_path, $phpEx, $k_config, $table_prefix;
		//$this->var_display($action);
		switch ($mode)
		{
			case 'search':
				$this->tpl_name	= 'ucp_pmsearch';
				$template->assign_vars(array(
					'S_UCP_ACTION'	=>	append_sid("ucp.php?i=".$id."&mode=".$mode)
				));

				$terms = $request->variable('terms', 'any');
				$keywords = utf8_normalize_nfc($request->variable('keywords', '', true));

				if ($keywords)
				{
					$template->assign_vars(array(
						'S_KEYWORDS'	=>	$keywords
					));

					$this->search = null;
					$error = false;
					$search_types = $this->get_search_types();
					if ($this->init_search($search_types[0], $this->search, $error))
					{
						trigger_error($error . adm_back_link($this->u_action), E_USER_WARNING);
					}
					$search_count = 0;
					$startFrom = $request->variable('start', 0);
					$this->search->split_keywords($keywords, $terms);
					$id_ary = array();

					$user_id = array(
						'' => (int) $user->data['user_id']
					);
					$search_count = $this->search->keyword_search('all', 'all', 'a', 0, $user_id, $id_ary, $startFrom, 25);
					if ($search_count > 0)
					{
						// Let's get additional info
						$page_array = array();
						$sql_array = 'SELECT msg.msg_id as msg_id, msg.message_subject as msg_subject, msg.message_text as msg_text, msg.message_time as msg_time, msg.author_id as msg_author, u.user_id, u.username as msg_author_uname, u.user_colour as msg_author_colour, tmsg.msg_id, MAX(tmsg.pm_unread) as unread, MAX(tmsg.pm_replied) as replied
								FROM ' . PRIVMSGS_TABLE . ' as msg, ' . USERS_TABLE . ' as u, ' . PRIVMSGS_TO_TABLE . ' as tmsg
								WHERE u.user_id = msg.author_id and (msg.msg_id = tmsg.msg_id and msg.author_id = tmsg.author_id) and ' . $db->sql_in_set('msg.msg_id', $id_ary) . '
								GROUP BY msg.msg_id, u.user_id, tmsg.msg_id
								ORDER BY msg.msg_id DESC';
						//$sql = $db->sql_build_query('SELECT', $sql_array);
						$result = $db->sql_query($sql_array);
						// Let's populate template
						$count = 1;
						while ($row = $db->sql_fetchrow($result))
						{
						/*	$page_array[$row['msg_id']] = array(
								'msg_id'	=> $row['msg_id'],
								'msg_subject'	=>	$row['msg_subject'],
								'msg_author'	=>	$row['msg_author'],
								'msg_time'	=>	$user->format_date($row['msg_time']),
								'msg_author_uname'	=> $row['msg_author_uname'],
								'msg_author_colour'	=> $row['msg_author_colour']
							);*/
							$template->assign_block_vars('pm_results', array(
								'S_ROW_COUNT'	=> $count,
								'FOLDER_IMG_STYLE'	=> ($row['unread'] ? 'pm_unread' : 'pm_read'),
								'PM_CLASS'	=> ($row['replied'] ? 'pm_replied_colour' : ''),
								'U_VIEW_PM'	=> './ucp.php?i=pm&mode=view&p=' . $row['msg_id'],
								'SUBJECT'	=> $row['msg_subject'],
								'SENT_TIME'	=>	$user->format_date($row['msg_time']),
								'MESSAGE_AUTHOR_FULL'	=> ($row['msg_author_colour'] ? '<a href="./memberlist.php?mode=viewprofile&u=' . $row['msg_author'] . '" class="username-coloured" style="color: #' . $row['msg_author_colour'] . ';">' . $row['msg_author_uname'] . '</a>' : '<a href="./memberlist.php?mode=viewprofile&u=' . $row['msg_author'] . '" class="username">' . $row['msg_author_uname'] . '</a>'),
							));
							$count ++;
						}

						$pagination = $phpbb_container->get('pagination');
						$base_url = append_sid('ucp.php?i=' . $id . '&mode=' . $mode . '&keywords=' . $keywords . '&terms=' . $terms);
						$pagination->generate_template_pagination($base_url, 'pagination', 'start', $search_count, 25, $startFrom);
						$pageNumber = $pagination->get_on_page(25, $startFrom);
						$template->assign_vars(array(
							'PAGE_NUMBER'	=> $pagination->on_page($search_count, 25, $startFrom),
							'TOTAL_MESSAGES'	=> $search_count,
							'HAS_RESULTS'	=> 1,
						));
					}

					else
					{
						trigger_error('NO_RESULTS_FOUND');
					}
					// After we got the the search count we go deeper
				}
			break;
		}
		//$this->var_display($tid);
	}
	//Define some helper functions
	function get_search_types()
	{
		global $phpbb_root_path, $phpEx, $phpbb_extension_manager;

		$finder = $phpbb_extension_manager->get_finder();

		return $finder
			->extension_suffix('_backend1')
			->extension_directory('')
			->core_path('ext/anavaro/pmsearch/search/')
			->get_classes();
	}

	/**
	* Initialises a search backend object
	*
	* @return false if no error occurred else an error message
	*/
	function init_search($type, &$search, &$error)
	{
		global $phpbb_root_path, $phpEx, $user, $auth, $config, $db, $table_prefix;

		if (!class_exists($type) || !method_exists($type, 'keyword_search'))
		{
			$error = $user->lang['NO_SUCH_SEARCH_MODULE'];
			return $error;
		}

		$error = false;
		$search = new $type($auth, $config, $db, $user, $table_prefix, $phpbb_root_path, $phpEx);

		return $error;
	}
}
