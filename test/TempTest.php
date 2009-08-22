<?php

require_once 'PHPUnit/Framework.php';

require_once dirname(__FILE__).'/../temp.inc';

class TempTest extends PHPUnit_Framework_TestCase
{
	function testGardeDeMain()
	{
		$chemin = '/tmp/zogzog';
		touch($chemin);
		$v = Remplacant::Verrouille($chemin);
		fwrite($v->e, 'coucou');
		$this->assertTrue($v->remplacer(false, true));
		$this->assertEquals('coucou', file_get_contents($chemin));
		fwrite($v->e, 'bonjour');
		$this->assertTrue($v->remplacer());
		$this->assertEquals('bonjour', file_get_contents($chemin));
	}
	
	function testMd5Renvoye()
	{
		$chemin = "/tmp/zogzog";
		@unlink($chemin);
		touch($chemin);
		$v = Remplacant::Verrouille($chemin);
		fwrite($v->e, 'salut');
		$this->assertEquals(md5('salut'), $v->remplacer(md5(''), true));
		fwrite($v->e, 'bonjour');
		$this->assertEquals(md5('bonjour'), $v->remplacer(''));
		$v = Remplacant::Verrouille($chemin);
		fwrite($v->e, 'eh oh');
		$this->assertEquals(md5('eh oh'), $v->remplacer(''));
	}
	
	function testEcritureAvecSauvegardeConditionnelle()
	{
		$chemin = '/tmp/zogzog';
		@unlink($chemin);
		@unlink($chemin.'.1');
		touch($chemin);
		$v = Remplacant::Verrouille($chemin);
		$this->assertFalse(file_exists($chemin.'.1'), 'pas de sauvegarde prématurée');
		fwrite($v->e, 'hola');
		$v->remplacer(false, true);
		$this->assertFalse(file_exists($chemin.'.1'), 'pas de sauvegarde non demandée');
		fwrite($v->e, 'coucou');
		$v->remplacer(md5('hola'), true);
		$this->assertFalse(file_exists($chemin.'.1'), 'pas de sauvegarde lorsque le md5 montre que l\'on maîtrise la précédente version' );
		fwrite($v->e, 'bonjour');
		$v->remplacer(md5('hola'));
		$this->assertTrue(file_exists($chemin.'.1'), 'sauvegarde demandée');
		$this->assertEquals('coucou', file_get_contents($chemin.'.1'));
	}
}

?>
