<?php
/**
 * native_test.php
 * part of pmsearch
 * Created by Stanislav Atanasov.
 * Date: 8/5/19
 * Time: 3:51 PM
 *
 * File description:
 *
 *
 */

namespace anavaro\pmsearch\tests\search;


require_once dirname(__FILE__) . '/../../../../../../tests/test_framework/phpbb_search_test_case.php';
/**
 * @group search
 */


class native_test extends \phpbb_search_test_case
{
	protected $db;
	protected $search;
	protected $user;
	/**
	 * Define the extensions to be tested
	 *
	 * @return array vendor/name of extension(s) to test
	 */
	static protected function setup_extensions()
	{
		return array('anavaro/pmsearch');
	}
	/**
	 * Get data set fixtures
	 */
	public function getDataSet()
	{
		return $this->createXMLDataSet(dirname(__FILE__) . '/fixtures/fixture.xml');
	}

	protected function setUp()
	{
		global $phpbb_root_path, $phpEx, $config, $user, $cache;
		parent::setUp();
		// dbal uses cache
		$cache = new \phpbb_mock_cache();
		$this->db = $this->new_dbal();
		$phpbb_dispatcher = new \phpbb_mock_event_dispatcher();
		$error = null;
		$class = self::get_search_wrapper('\anavaro\pmsearch\search\pm_search_fulltext_native');
		$user->data['user_id'] = 2;
		$this->search = new $class($error, $phpbb_root_path, $phpEx, null, $config, $this->db, $user, $phpbb_dispatcher);
	}

	public function test_user_search()
	{
		$count = 0;
		$test_ary = array();
		$all_ids = $this->search->user_search('test', $test_ary, $count, 5);
		$this->assertEquals(false, $all_ids);
		$all_ids = $this->search->user_search(5, $test_ary, $count, 5);
		$this->assertEquals(1, $all_ids);
	}
}