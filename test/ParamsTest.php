<?php

require_once dirname(__FILE__).'/../params.inc';

class ParamsTest extends PHPUnit_Framework_TestCase
{
	function testSimplificationDesTableaux()
	{
		$this->assertEquals('&chose[]=truc&chose[]=machin', params_decomposer(null, array('chose' => array(0 => 'truc', 1 => 'machin'))));
		$this->assertEquals('&chose[1]=truc&chose[2]=machin', params_decomposer(null, array('chose' => array(1 => 'truc', 2 => 'machin'))));
		$this->assertEquals('&chose[1]=truc&chose[0]=machin', params_decomposer(null, array('chose' => array(1 => 'truc', 0 => 'machin'))));
	}
}

?>
