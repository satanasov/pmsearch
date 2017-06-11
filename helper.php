<?php
/**
 * Created by PhpStorm.
 * User: lucifer
 * Date: 13.2.2017 г.
 * Time: 17:18
 */

namespace anavaro\pmsearch;


class helper
{
	public function __construct(\phpbb\extension\manager $ext_manager, \phpbb\auth\auth $auth, \phpbb\config\config $config,
		\phpbb\db\driver\driver_interface $db, \phpbb\user $user, \phpbb\language\language $language, \phpbb\event\dispatcher $dispatcher,
		$phpbb_root_path, $phpEx)
	{
		$this->ext_manager = $ext_manager;
		$this->auth = $auth;
		$this->config = $config;
		$this->db = $db;
		$this->user = $user;
		$this->lang = $language;
		$this->dispatcher = $dispatcher;
		$this->root_path = $phpbb_root_path;
		$this->php_ext = $phpEx;
	}

	public function get_search_types()
	{
		$finder = $this->ext_manager->get_finder();

		return $finder
			->extension_suffix('_backend1')
			->extension_directory('')
			->core_path('ext/anavaro/pmsearch/search/')
			->get_classes();
	}

	public function init_search($type, &$search, &$error)
	{
		if (!class_exists($type) || !method_exists($type, 'keyword_search'))
		{
			$error = $this->lang->lang('NO_SUCH_SEARCH_MODULE');
			return $error;
		}

		$error = false;
		$search = new $type($error, $this->root_path, $this->php_ext, $this->auth, $this->config, $this->db, $this->user, $this->dispatcher);

		return $error;
	}
}