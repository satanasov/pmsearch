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
class pmsearch_acp extends pmsearch_base
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
		$message_id = $this->create_private_message('Test private message #1', 'This is a test private message sent by the testing framework. We need to check event indexing.', array($this->get_user_id('testuser1')));
		
		$this->admin_login();
		$this->add_lang_ext('anavaro/pmsearch', 'info_acp_pmsearch');
		$crawler = self::request('GET', 'adm/index.php?i=-anavaro-pmsearch-acp-acp_pmsearch_module&mode=main&sid=' . $this->sid);
		
		$this->assertContains('12', $crawler->filter('indexed_words')->text());
		$this->assertContains('12', $crawler->filter('relative_indexes')->text());
	}
}
?>