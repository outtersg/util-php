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
	
	function testComparer()
	{
		$d0 = array(2012, 06, 22, 15, 26, 13);
		$d1 = array(2012, 06, 22, null, null, null);
		
		$this->assertEquals(0, Date::comparer($d0, $d1)); // Sans plus de précision, on élide les HMS de la première date puisque la seconde n'offre aucun point de comparaison: les parties renseignées de part et d'autre concordent, on estime donc l'égalité.
		$this->assertGreaterThan(0, Date::comparer($d0, $d1, false)); // Par défaut, il est 0 h 00 si on n'a pas de précision.
		$this->assertLessThan(0, Date::comparer($d0, $d1, false, 0.25)); // … sauf si la date indéfinie ne couvre que le dernier quart de sa journée (de 18 à 24 h).
		$this->assertLessThan(0, Date::comparer($d0, $d1, true)); // Si la seconde date est une fin (à date incluse), elle s'étend jusqu'à la fin de la plage (ici la journée).
		$this->assertGreaterThan(0, Date::comparer($d0, $d1, true, 0.25)); // Bon bien sûr si la « fin de journée » n'en couvre qu'un quart, elle s'arrête à 8 h du mat', c'est-à-dire avant la date fixe.
		$this->assertEquals(0, Date::comparer($d1, $d0)); // Et dans l'autre sens (la date indéfinie passe en tant que début)?
		$this->assertLessThan(0, Date::comparer($d1, $d0, false));
		$this->assertLessThan(0, Date::comparer($d1, $d0, true));
		$this->assertGreaterThan(0, Date::comparer($d1, $d0, false, 0.25));
	}
	
	function testUnionSi()
	{
		$t = array
		(
			// Ils se touchent.
			array
			(
				array
				(
					array(array(2012, 3, null, null, null, null), array(2012, 5, null, null, null, null)),
					array(array(2012, 6, null, null, null, null), array(2012, 8, null, null, null, null)),
				),
				array
				(
					array(array(2012, 3, null, null, null, null), array(2012, 8, null, null, null, null)),
				),
			),
			// Ils ne se touchent pas.
			array
			(
				array
				(
					array(array(2012, 3, null, null, null, null), array(2012, 4, null, null, null, null)),
					array(array(2012, 6, null, null, null, null), array(2012, 8, null, null, null, null)),
				),
				array
				(
					array(array(2012, 3, null, null, null, null), array(2012, 4, null, null, null, null)),
					array(array(2012, 6, null, null, null, null), array(2012, 8, null, null, null, null)),
				),
			),
			// L'un englobe l'autre.
			array
			(
				array
				(
					array(array(2012, 3, null, null, null, null), array(2012, 5, null, null, null, null)),
					array(array(2011, 11, null, null, null, null), array(2012, 8, null, null, null, null)),
				),
				array
				(
					array(array(2011, 11, null, null, null, null), array(2012, 8, null, null, null, null)),
				),
			),
		);
		
		foreach($t as $cas)
			$this->assertEquals($cas[1], Periode::unionSi($cas[0]));
	}
}

?>
