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
	
}
?>