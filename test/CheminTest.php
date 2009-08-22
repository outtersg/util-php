<?php

require_once 'PHPUnit/Framework.php';

require_once dirname(__FILE__).'/../chemin.inc';

class CheminTest extends PHPUnit_Framework_TestCase
{
	function test0()
	{
		$racine = new Chemin('http://www.apple.com/');
		$chem = new Chemin('../../toto/titi/ghup', $racine);
		$bidule = new Chemin('../../toto/miarps/zip', $racine);
		$this->assertEquals('../miarps/zip', $bidule->cheminDepuis($chem)->cheminComplet());
		$this->assertEquals('http://www.apple.com/toto/titi/ghup', $chem->absolu()->cheminComplet());
	}
		
	function test2()
	{
		$chem = new Chemin('/Users/gui/tmp/');
		$chem = $chem->et('../chose/');
		$this->assertEquals('/Users/gui/chose/', $chem->cheminComplet());
	}
	
	function test3()
	{
		$chem = new Chemin('http://www.apple.fr/images/truc.png');
		$chem = $chem->et('../chose/');
		$this->assertEquals('http://www.apple.fr/chose/', $chem->cheminComplet());
	}
	
	function test4()
	{
		$chem = new Chemin('../chose/truc/');
		$chem = $chem->et('../chose/');
		$this->assertEquals('../chose/chose/', $chem->cheminComplet());
	}
	
	function test5()
	{
		$chem = new Chemin('../chose/');
		$chem = $chem->et('../../rien/');
		$this->assertEquals('../../rien/', $chem->cheminComplet());
	}
	
	function test6()
	{
		$racine = new Chemin('http://www.apple.com/');
		$racine = new Chemin('ici/là/fichier', $racine);
		$chem = new Chemin('chose/../', $racine);
		$chem = new Chemin('../', $chem);
		$this->assertEquals('là', $chem->chemin[count($chem->chemin) - 1]);
	}
	
	function test7()
	{
		$chem = new Chemin('chose/index.html');
		$chem = $chem->et('../rien/');
		$this->assertEquals('rien/', $chem->cheminComplet());
	}
	
	function test8()
	{
		$cheminDepart = $GLOBALS['cheminDepart'];
		$GLOBALS['cheminDepart'] = null;
		$GLOBALS['cheminDepart'] = new Chemin('php/chamrousse/');
		$chem = new Chemin();
		$chem->decouper('toto/');
		$chem2 = $chem;
		$this->assertEquals('toto/', $chem->cheminDossier());
		$chem2->decouper('../../titi/');
		$chem2 = $chem2->dossierInverse();
		$this->assertEquals('../chamrousse/', $chem2->cheminDossier());
		$chem = new Chemin('../chamrousse/index.xhtml');
		$this->assertEquals('./', $chem->cheminDossier());
		$chem = $chem->dossierInverse();
		$this->assertEquals('./', $chem->cheminDossier());
		$GLOBALS['cheminDepart'] = $cheminDepart;
	}
}

?>
