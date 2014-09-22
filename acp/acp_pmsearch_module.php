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
	function var_display($i)
	{
		echo "<pre>";
		print_r($i);
		echo "</pre>";
	}
	function main($id, $mode)
	{
		global $config, $user, $table_prefix, $db, $template, $request, $phpbb_root_path, $phpbb_admin_path, $phpEx;

		switch($mode)
		{
			default:
				//Let's see indexing
				$pm_index = $request->variable('pm_index', 0);

				if ($pm_index == '1')
				{
					$config->set('pmsearch_pm_index', 0);
				}
				elseif ($pm_index == '2')
				{
					$config->set('pmsearch_pm_index', 1);
				}

				//Do we want users to be able to search?
				$pm_search = $request->variable('pm_search', 0);

				if ($pm_search == '1')
				{
					$config->set('pmsearch_search', 0);
				}
				elseif ($pm_index == '2')
				{
					$config->set('pmsearch_search', 1);
				}
				$this->tpl_name		= 'acp_pmsearch';
				$this->page_title	= 'PM Admin';

				$template->assign_var('PM_INDEX', $config['pmsearch_pm_index']);
				$template->assign_var('PM_SEARCH', $config['pmsearch_search']);
				$template->assign_var('U_ACTION', append_sid("index.php?i=".$id."&mode=".$mode));

				if($config['pmsearch_pm_index'])
				{
				}
			break;
		}
	}
}

