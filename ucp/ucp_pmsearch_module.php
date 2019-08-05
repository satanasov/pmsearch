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
	private $search_helper;
	private $config;
	private $terms_ary = array(
		'all'	=> 1,
		'any'	=> 2,
		'nick'	=> 3,
	);
	function main($id, $mode)
	{
		global $db, $user, $auth, $template, $request, $phpbb_container, $config;
		$this->config = $config;
		$this->search_helper = $phpbb_container->get('anavaro.pmsearch.search.helper');
		if (!$auth->acl_get('u_pmsearch'))
		{
			trigger_error('ACCESS_DENIED');
		}
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
					$this->search = null;
					$error = false;
					$search_types = $this->search_helper->get_search_types();
					if ($this->search_helper->init_search($search_types[0], $this->search, $error))
					{
						trigger_error($error . adm_back_link($this->u_action), E_USER_WARNING);
					}
					$search_count = 0;
					$startFrom = $request->variable('start', 0);
					$id_ary = array();
					$user_id = array(
						'' => (int) $user->data['user_id']
					);
					//What are we searching for?
					if ($terms != 'nick')
					{
						$this->search->split_keywords($keywords, $terms);
						//$search_count = $this->search->keyword_search('norma', 'all', 'all', array('msg_id' => 'a'), 'msg_id', 'd', 0, array($user_id), '', $id_ary, $startFrom, 25);
						$search_count = $this->search->keyword_search('norma', 'all', 'all', array('msg_id' => 'a'), 'msg_id', 'd', 0, array(), '', '', $user_id, '', $id_ary, $startFrom, $this->config['search_block_size']);

					}
					else
					{
						// So do we have user_id or username?
						if (!is_numeric($keywords))
						{
							// It's username ... let's get ID
							$sql = 'SELECT user_id FROM ' . USERS_TABLE . ' WHERE username_clean LIKE \'' . utf8_clean_string($keywords) . '\'';
							$result = $db->sql_query($sql);
							$user_id = $db->sql_fetchrow($result);
							$keywords = $user_id['user_id'];
						}

						$search_count = $this->search->user_search($keywords, $id_ary, $startFrom, $this->config['search_block_size']);
					}

					if ($search_count > 0 )
					{
						// Let's get additional info
						$page_array = array();
						// As there seems to be some problem with POSTGRES SQL I'll split the info query in two separate queries - one for the message ...
						$sql_array = array(
							'SELECT'	=> 'msg.msg_id as msg_id, msg.message_subject as msg_subject, msg.message_time as msg_time, msg.author_id as msg_author, tmsg.msg_id, MAX(tmsg.pm_unread) as unread, MAX(tmsg.pm_replied) as replied',
							'FROM'	=> array(
								PRIVMSGS_TABLE => 'msg',
								PRIVMSGS_TO_TABLE => 'tmsg'
							),
							'WHERE'	=> 'msg.msg_id = tmsg.msg_id and msg.author_id = tmsg.author_id and ' . $db->sql_in_set('msg.msg_id', $id_ary),
							'GROUP_BY'	=> 'msg.msg_id, tmsg.msg_id',
							'ORDER_BY'	=> 'msg.msg_id DESC'
						);
						$sql = $db->sql_build_query('SELECT', $sql_array);
						//$this->var_display($sql);
						$result = $db->sql_query($sql);
						// Let's populate template
						$author_uid_arrray = array();
						while ($row = $db->sql_fetchrow($result))
						{
							$author_uid_arrray[] = (int) $row['msg_author'];
							$page_array[$row['msg_id']] = array(
								'msg_id'	=> $row['msg_id'],
								'msg_subject'	=>	$row['msg_subject'],
								'msg_author'	=>	$row['msg_author'],
								'msg_time'	=>	(int) $row['msg_time'],
								'unread'	=> $row['unread'],
								'replied'	=> $row['replied']
							);
						}
						if (is_numeric($keywords))
						{
							$author_uid_arrray[] = (int) $keywords;
						}
						$db->sql_freeresult($result);
						// ... one for the authors on this page
						$authors_array = array();
						$sql = 'SELECT user_id, username, user_colour FROM ' . USERS_TABLE . ' WHERE ' .  $db->sql_in_set('user_id', $author_uid_arrray);
						$result = $db->sql_query($sql);
						while ($row = $db->sql_fetchrow($result))
						{
							$authors_array[$row['user_id']] = array(
								'username'	=> $row['username'],
								'user_colour'	=> $row['user_colour']
							);
						}
						$count = 1;
						foreach ($page_array as $VAR) {
							$template->assign_block_vars('pm_results', array(
								'S_ROW_COUNT'	=> $count,
								'FOLDER_IMG_STYLE'	=> ($VAR['unread'] ? 'pm_unread' : 'pm_read'),
								'PM_CLASS'	=> ($VAR['replied'] ? 'pm_replied_colour' : ''),
								'U_VIEW_PM'	=> './ucp.php?i=pm&mode=view&p=' . $VAR['msg_id'],
								'SUBJECT'	=> $VAR['msg_subject'],
								'SENT_TIME'	=>	$user->format_date($VAR['msg_time']),
								'MESSAGE_AUTHOR_FULL'	=> ($authors_array[$VAR['msg_author']]['user_colour'] ? '<a href="./memberlist.php?mode=viewprofile&u=' . $VAR['msg_author'] . '" class="username-coloured" style="color: #' . $authors_array[$VAR['msg_author']]['user_colour'] . ';">' . $authors_array[$VAR['msg_author']]['username'] . '</a>' : '<a href="./memberlist.php?mode=viewprofile&u=' . $VAR['msg_author'] . '" class="username">' . $authors_array[$VAR['msg_author']]['username'] . '</a>'),
							));
							$count ++;
						}

						$pagination = $phpbb_container->get('pagination');
						$base_url = append_sid('ucp.php?i=' . $id . '&mode=' . $mode . '&keywords=' . $keywords . '&terms=' . $terms);
						$pagination->generate_template_pagination($base_url, 'pagination', 'start', $search_count, 1, $startFrom);
						$pageNumber = $pagination->get_on_page(25, $startFrom);
						if (is_numeric($keywords))
						{
							$template->assign_vars(array(
								'S_KEYWORDS'	=> $authors_array[$keywords]['username']
							));
						}
						else
						{
							$template->assign_vars(array(
								'S_KEYWORDS'	=>	$keywords
							));
						}
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
				$template->assign_vars(array(
					'SEARCH_TEARM_TYPE' => $this->terms_ary[$terms]
				));
		}
	}
}
