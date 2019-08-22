<?php
/**
 *
 * phpBB PM Search events test
 *
 * @copyright (c) 2015 Lucifer <https://www.anavaro.com>
 * @license GNU General Public License, version 2 (GPL-2.0)
 *
 */
namespace anavaro\pmsearch\tests\search;
/**
 * @group event
 */
class main_event_test extends \phpbb_database_test_case
{
	protected $listener;

	/**
	 * Define the extensions to be tested
	 *
	 * @return array vendor/name of extension(s) to test
	 */
	static protected function setup_extensions()
	{
		return array('anavaro/pmsearch');
	}

	protected $db;

	/**
	 * Get data set fixtures
	 */
	public function getDataSet()
	{
		return $this->createXMLDataSet(dirname(__FILE__) . '/fixtures/fixture.xml');
	}

	/**
	 * Setup test environment
	 */
	public function setUp()
	{
		parent::setUp();

		$this->template = $this->getMockBuilder('\phpbb\template\template')
			->getMock()
		;
		$this->config = new \phpbb\config\config(array());
		$this->db = $this->new_dbal();
		$this->search_helper = $this->getMockBuilder('\anavaro\pmsearch\helper')
			->disableOriginalConstructor()
			->getMock();
	}

	/**
	 * Create our controller
	 */
	protected function set_listener()
	{
		$this->listener = new \anavaro\pmsearch\event\main_listener(
			$this->config,
			$this->db,
			$this->template,
			$this->search_helper
		);
	}

	/**
	 * Test the event listener is subscribing events
	 */
	public function test_getSubscribedEvents()
	{
		$this->assertEquals(array(
			'core.permissions',
			'core.submit_pm_after',
			'core.delete_pm_before',
			'core.memberlist_view_profile'
		), array_keys(\anavaro\pmsearch\event\main_listener::getSubscribedEvents()));
	}

	/**
	 * Test user_profile_galleries
	 */
	public function test_user_profile_galleries()
	{
		$member = array('user_id' => 2);
		$event_data = array('member');
		$event = new \phpbb\event\data(compact($event_data));
		$this->set_listener();

		$this->template->expects($this->once())
			->method('assign_vars')
			->with(array(
				'S_SEARCH_WITH_USER'	=> true,
				'U_SEARCH_WITH_USER'	=> 'http://ucp.php?i=\anavaro\pmsearch\ucp\ucp_pmsearch_module&mode=search&terms=nick&keywords=2'
			));

		$dispatcher = new \Symfony\Component\EventDispatcher\EventDispatcher();
		$dispatcher->addListener('core.memberlist_view_profile', array($this->listener, 'pm_search_with_user'));
		$dispatcher->dispatch('core.memberlist_view_profile', $event);
	}
}
