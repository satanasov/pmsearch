<?php
/**
*
* PM Search
*
* @copyright (c) 2014 Stanislav Atanasov
* @license GNU General Public License, version 2 (GPL-2.0)
*
*/

namespace anavaro\pmsearch\tests\functional;

/**
* @group functional
*/
class pmsearch_acp_test extends pmsearch_base
{
	public function test_install()
	{
		//add users so we can send messages and search
		$this->create_user('testuser1');
		$this->add_user_group('NEWLY_REGISTERED', array('testuser1'));
		
		$this->login();
		$this->admin_login();
		
		$this->add_lang_ext('anavaro/pmsearch', 'info_acp_pmsearch');
		$crawler = self::request('GET', 'adm/index.php?i=-anavaro-pmsearch-acp-acp_pmsearch_module&mode=main&sid=' . $this->sid);
		
		$this->assertContainsLang('PMSEARCH_ADMIN', $crawler->text());
		
		$this->logout();
	}
	public function test_event_auto_index()
	{
		$this->login();
		$message_id = $this->create_private_message('Test private message', 'This test private message sent testing framework. need check event indexing.', array($this->get_user_id('testuser1')));
		
		$this->admin_login();
		$this->add_lang_ext('anavaro/pmsearch', 'info_acp_pmsearch');
		$crawler = self::request('GET', 'adm/index.php?i=-anavaro-pmsearch-acp-acp_pmsearch_module&mode=main&sid=' . $this->sid);
		
		$this->assertContains('11', $crawler->filter('#indexed_words')->text());
		$this->assertContains('14', $crawler->filter('#relative_indexes')->text());
		
		$this->logout();
	}
	
	public function test_acp_delete_index()
	{
		$this->login();
		$this->admin_login();
		$this->add_lang_ext('anavaro/pmsearch', 'info_acp_pmsearch');
		$crawler = self::request('GET', 'adm/index.php?i=-anavaro-pmsearch-acp-acp_pmsearch_module&mode=main&sid=' . $this->sid);
		
		$form = $crawler->selectButton($this->lang('DELETE_INDEX'))->form();
		$crawler = self::submit($form);
		
		//test step 3 begins
		$this->assertContains('0', $crawler->filter('#indexed_words')->text());
		$this->assertContains('0', $crawler->filter('#relative_indexes')->text());
		
		$this->logout();
	}
	public function test_acp_build_index()
	{
		$this->login();
		$this->admin_login();
		$this->add_lang_ext('anavaro/pmsearch', 'info_acp_pmsearch');
		$crawler = self::request('GET', 'adm/index.php?i=-anavaro-pmsearch-acp-acp_pmsearch_module&mode=main&sid=' . $this->sid);
		
		$form = $crawler->selectButton($this->lang('CREATE_INDEX'))->form();
		$crawler = self::submit($form);
		
		//test step 3 begins
		$this->assertContains('11', $crawler->filter('#indexed_words')->text());
		$this->assertContains('14', $crawler->filter('#relative_indexes')->text());
		
		$this->logout();
	}
	public function test_search()
	{
		$this->login();
		$message_id = $this->create_private_message('Test PM', 'This test PM will not contain words that stand for pm, so we can search for them.', array($this->get_user_id('testuser1')));
		$message_id = $this->create_private_message('Test PM 1', 'This test PM will not contain words that stand for pm, so we can search for them. And it is the second pm', array($this->get_user_id('testuser1')));
		$message_id = $this->create_private_message('Test PM 3', 'This test PM will not contain words that stand for pm, so we can search for them. And it is the third pm', array($this->get_user_id('testuser1')));
		$message_id = $this->create_private_message('Test PM 4', 'This test PM will not contain words that stand for pm, so we can search for them. And it is the fourth pm', array($this->get_user_id('testuser1')));
		
		$this->logout();
		
		//get user to log in
		$this->login('testuser1');
		
		$this->add_lang_ext('anavaro/pmsearch', 'info_ucp_pmsearch');
		$crawler = self::request('GET', 'ucp.php?i=\anavaro\pmsearch\ucp\ucp_pmsearch_module&mode=search');
		
		$form = $crawler->selectButton($this->lang('SEARCH_PMS'))->form();
		$form['keywords'] = 'Test';
		
		
		//$crawler = self::submit($form);
		
		//$this->assertContains('5', $crawler->filter('.pagination')->text());
		
		//$crawler = self::request('GET', 'ucp.php?i=\anavaro\pmsearch\ucp\ucp_pmsearch_module&mode=search');
		
		//$form = $crawler->selectButton($this->lang('SEARCH_PMS'))->form();
		//$form['keywords'] = 'private message';
		
		//$this->assertContains('1', $crawler->filter('.pagination')->text());
		$this->assertContains('alalaalalalalala', $crawler->text());
		$this->logout();
	}
}
?>