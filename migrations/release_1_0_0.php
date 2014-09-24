<?php
/**
*
* @package migration
* @copyright (c) 2014 phpBB Group
* @license http://opensource.org/licenses/gpl-license.php GNU Public License v2
*
*/

namespace anavaro\pmsearch\migrations;

class release_1_0_0 extends \phpbb\db\migration\migration
{
	public function update_data()
	{
		return array(
			//Add extension ACP module
			array('module.add', array(
				'acp',
				'ACP_CAT_DOT_MODS',
				'ACP_PMSEARCH_GRP'
			)),
			array('module.add', array(
				'acp',
				'ACP_PMSEARCH_GRP',
				array(
					'module_basename'	=> '\anavaro\pmsearch\acp\acp_pmsearch_module',
					'module_mode'		=> array('main'),
					'module_auth'        => 'ext_anavaro/pmsearch && acl_a_user',
				)
			)),
			//Add extension UCP module
			array('module.add', array(
				'ucp',
				'UCP_PM',
				array(
					'module_basename'	=> '\anavaro\pmsearch\ucp\ucp_pmsearch_module',
					'module_modes' => array('search'),
					'module_auth'	=> 'ext_anavaro/pmsearch',
				),

			)),
			//set configs
			array('config.add', array('pmsearch_version', '1.0.0')),
			array('config.add', array('pmsearch_pm_index', true)),
			array('config.add', array('pmsearch_search', true)),
			//add permissions
			array('permission.add', array('u_pmsearch', true, 'u_readpm')),
		);
	}

	//lets create the needed table
	public function update_schema()
	{
		return array(
			'add_tables'    => array(
				$this->table_prefix . 'privmsgs_swl'	=> array(
					'COLUMNS'	=> array(
						'word_id'	=> array('UINT', null, 'auto_increment'),
						'word_text'	=> array('VCHAR_UNI', ''),
						'word_common'	=> array('BOOL', 0),
						'word_count'	=> array('UINT', 0),
					),
					'PRIMARY_KEY'	=> 'word_id',
					'KEYS'	=> array(
						'wrd_txt'	=> array('UNIQUE', 'word_text'),
						'wrd_cnt'	=> array('INDEX', 'word_count'),
					),
				),
				$this->table_prefix . 'privmsgs_swm'	=> array(
					'COLUMNS'	=> array(
						'post_id'	=> array('UINT', 0),
						'word_id'	=> array('UINT', 0),
						'title_match'	=> array('BOOL', 0),
					),
					'KEYS'	=> array(
						'unq_mtch'	=> array('UNIQUE', array('word_id', 'post_id', 'title_match')),
						'word_id'	=> array('INDEX', 'word_id'),
						'post_id'	=> array('INDEX', 'post_id')
					),
				),
			),
		);
	}
/*
	public function revert_schema()
	{
		return array(
			'drop_tables'		=> array(
				$this->table_prefix . 'privmsgs_swl',
				$this->table_prefix . 'privmsgs_swm',
			),
		);
	}
*/
}
