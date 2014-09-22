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
class pmsearch_base extends \phpbb_functional_test_case
{
	static protected function setup_extensions()
	{
		return array('anavaro/pmsearch');
	}
	
	public function setUp()
	{
		parent::setUp();
	}
}