<?php

/**
*
* @package Anavaro.com PM Search
* @copyright (c) 2014 Lucifer
* @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
*
*/

/**
* @ignore
*/

namespace anavaro\pmsearch\ucp;

class ucp_pmsearch_info
{
	function module()
	{
		return array(
			'filename' => '\anavaro\pmsearch\ucp\ucp_pmsearch_module',
			'title' => 'PMSEARCH_TITLE',
			'version' => '1.0.0',
			'modes' => array(
				'search' => array(
					'title' => 'PMSEARCH_TITLE',
					'auth' => 'ext_anavaro/pmsearch',
					'cat' => array('UCP_PM')
				),
			),
		);
	}
}
