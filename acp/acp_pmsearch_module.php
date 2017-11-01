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
namespace anavaro\pmsearch\acp;

/**
* @package acp
*/
class acp_pmsearch_module
{
	var $state;
	var $search;
	var $max_post_id;
	var $batch_size = 200;
	var $u_action;
	function main($id, $mode)
	{
		global $config, $user, $table_prefix, $db, $template, $request, $phpbb_root_path, $phpbb_log, $phpbb_admin_path, $phpEx;

		switch($mode)
		{
			default:
				//Let's see indexing
				$pm_index = $request->variable('pm_index', 0);

				if ($pm_index == '1')
				{
					$config->set('pmsearch_pm_index', 0);
				}
				else if ($pm_index == '2')
				{
					$config->set('pmsearch_pm_index', 1);
				}

				//Do we want users to be able to search?
				$pm_search = $request->variable('pm_search', 0);

				if ($pm_search == '1')
				{
					$config->set('pmsearch_search', 0);
				}
				else if ($pm_index == '2')
				{
					$config->set('pmsearch_search', 1);
				}
				$this->tpl_name		= 'acp_pmsearch';
				$this->page_title	= 'PMSEARCH_ADMIN';

				$template->assign_var('PM_INDEX', $config['pmsearch_pm_index']);
				$template->assign_var('PM_SEARCH', $config['pmsearch_search']);
				$template->assign_var('U_ACTION', append_sid("index.php?i=".$id."&mode=".$mode));

				if($config['pmsearch_pm_index'])
				{
					$action_index = $request->variable('action_index', '');
					$this->state = explode(',', $config['search_pm_indexing_state']);
					if (isset($_POST['cancel']))
					{
						$action = '';
						$this->state = array();
						$this->save_state();
					}
					if ($action_index)
					{
						switch ($action_index)
						{
							case 'delete':
								$this->state[1] = 'delete';
							break;

							case 'create':
								$this->state[1] = 'create';
							break;

							default:
								trigger_error('NO_ACTION', E_USER_ERROR);
							break;
						}
						if (empty($this->state[0]))
						{
							$this->state[0] = $request->variable('target_index', 'normal');
						}
						$this->search = null;
						$error = false;
						$search_types = $this->get_search_types();
						if ($this->init_search($search_types[0], $this->search, $error))
						{
							trigger_error($error . adm_back_link($this->u_action), E_USER_WARNING);
						}
						$name = $this->search->get_name();
						$action_index = &$this->state[1];
						$this->max_post_id = $this->get_max_post_id();

						$post_counter = (isset($this->state[2])) ? $this->state[2] : 0;
						$this->state[2] = &$post_counter;
						$this->save_state();
						switch ($action_index)
						{
							case 'delete':
								if (method_exists($this->search, 'delete_index'))
								{
									$this->state = array('');
									$this->save_state();
									// pass a reference to myself so the $search object can make use of save_state() and attributes
									if ($error = $this->search->delete_index($this, append_sid("{$phpbb_admin_path}index.$phpEx", "i=$id&mode=$mode&action=delete", false)))
									{
										$this->state = array('');
										$this->save_state();
										add_log('admin', 'LOG_PMSEARCH_INDEX_REMOVED', $name);
										trigger_error($error . adm_back_link($this->u_action), E_USER_WARNING);
									}
								}
							break;
							case 'create':
								//$this->var_display($this->state);
								$starttime = explode(' ', microtime());
								$starttime = $starttime[1] + $starttime[0];
								$row_count = 0;

								while (still_on_time() && $post_counter <= $this->max_post_id)
								{
									$sql = 'SELECT msg_id, message_subject, message_text, author_id
										FROM ' . PRIVMSGS_TABLE . '
										WHERE msg_id >= ' . (int) ($post_counter + 1) . '
											AND msg_id <= ' . (int) ($post_counter + $this->batch_size);
									$result = $db->sql_query($sql);

									$buffer = $db->sql_buffer_nested_transactions();

									if ($buffer)
									{
										$rows = $db->sql_fetchrowset($result);
										$rows[] = false; // indicate end of array for while loop below

										$db->sql_freeresult($result);
									}

									$i = 0;
									while ($row = ($buffer ? $rows[$i++] : $db->sql_fetchrow($result)))
									{
										//	Indexing
										$this->search->index('post', (int) $row['msg_id'], $row['message_text'], $row['message_subject'], (int) $row['author_id']);
										$row_count++;
									}
									if (!$buffer)
									{
										$db->sql_freeresult($result);
									}
									//$this->var_display($this->state[2]);
									$post_counter += $this->batch_size;

									// pretend the number of posts was as big as the number of ids we indexed so far
									// just an estimation as it includes deleted posts
									$num_posts = $config['num_posts'];
									$config['num_posts'] = min($config['num_posts'], $post_counter);
									$this->search->tidy();
									$config['num_posts'] = $num_posts;

									if ($post_counter <= $this->max_post_id)
									{
										$mtime = explode(' ', microtime());
										$totaltime = $mtime[0] + $mtime[1] - $starttime;
										$rows_per_second = $row_count / $totaltime;
										$this->state[3] = 'continue';
										$this->save_state();
										meta_refresh(1, append_sid($this->u_action . '&amp;action_index=create&amp;skip_rows=' . $post_counter));
										trigger_error($user->lang('SEARCH_INDEX_CREATE_REDIRECT', (int) $row_count, $post_counter) . $user->lang('SEARCH_INDEX_CREATE_REDIRECT_RATE', $rows_per_second));
									}
									else
									{
										$this->state = array('');
										add_log('admin', 'LOG_PMSEARCH_INDEX_CREATED', $name);
									}
								}
							break;
						}
					}
					$search_types = $this->get_search_types();

					$search = null;
					$error = false;
					$search_options = '';

					if ($this->init_search($search_types[0], $search, $error) || !method_exists($search, 'index_created'))
					{
						continue;
					}

					//Let's build normal
					$name = $search->get_name();

					$data = array();
					if (method_exists($search, 'index_stats'))
					{
						$data = $search->index_stats();
					}

					$statistics = array();
					foreach ($data as $statistic => $value)
					{
						$n = sizeof($statistics);
						if ($n && sizeof($statistics[$n - 1]) < 3)
						{
							$statistics[$n - 1] += array('statistic_2' => $statistic, 'value_2' => $value);
						}
						else
						{
							$statistics[] = array('statistic_1' => $statistic, 'value_1' => $value);
						}
					}
					$template->assign_block_vars('normal', array(
						'L_NAME'			=> $name,
						'NAME'				=> $name,
						'DISSALOW'			=> ($this->state[0] == 'black' && $this->state[3] == 'continue') ? 1 : 0,
						'S_INDEXED'			=> (bool) $search->index_created(),
						'S_STATS'			=> (bool) sizeof($statistics),
						'STATISTIC_1'	=> $statistics[0]['statistic_1'],
						'VALUE_1'		=> $statistics[0]['value_1'],
						'STATISTIC_2'	=> (isset($statistics[0]['statistic_2'])) ? $statistics[0]['statistic_2'] : '',
						'VALUE_2'		=> (isset($statistics[0]['value_2'])) ? $statistics[0]['value_2'] : '',
						'CONTINUE'		=> ($this->state[0] == 'normal' && (isset($this->state[3]) && $this->state[3] == 'continue')) ? 1 : 0,
						'CONTINUE_URL'	=> append_sid($this->u_action . '&amp;action_index=create')
						)
					);
					//end building normal
				}
			break;
		}
	}
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
	function save_state($state = false)
	{
		global $config;
		if ($state)
		{
			$this->state = $state;
		}

		ksort($this->state);

		$config->set('search_pm_indexing_state', implode(',', $this->state), true);
	}

	function get_max_post_id()
	{
		global $db;

		$sql = 'SELECT MAX(msg_id) as max_post_id
			FROM '. PRIVMSGS_TABLE;
		$result = $db->sql_query($sql);
		$max_post_id = (int) $db->sql_fetchfield('max_post_id');
		$db->sql_freeresult($result);

		return $max_post_id;
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
