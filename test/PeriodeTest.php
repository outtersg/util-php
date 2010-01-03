<?php

require_once 'PHPUnit/Framework.php';

require_once dirname(__FILE__).'/../periode.inc';

class PeriodeTest extends PHPUnit_Framework_TestCase
{
	function testAff()
	{
		$periodes = array
		(
			array(array(1979, 10, 10, 8, 30, null), array(1979, 10, 10, 18, 0, null), '10/10/1979 08:30 - 18:00', 'le 10 octobre 1979 de 8h30 à 18h00'),
			array(array(1979, 10, 10, null, null, null), array(1979, 12, 23, null, null, null), '10/10 - 23/12/1979', 'du 10 octobre au 23 décembre 1979'),
			array(array(1979, 10, 10, 8, 30, null), array(1979, 12, 23, 14, 30, null), '10/10/1979 08:30 - 23/12 14:30', 'du 10 octobre 1979 à 8h30 au 23 décembre à 14h30'),
		);
		
		foreach($periodes as $ensemble)
		{
			$this->assertEquals($ensemble[2], Periode::aff($ensemble[0], $ensemble[1], Periode::$NUMERIQUE));
			$this->assertEquals($ensemble[3], Periode::aff($ensemble[0], $ensemble[1], Periode::$REDIGE));
		}
	}
}

?>
