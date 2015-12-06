<?php

//require_once 'PHPUnit/Framework.php';

require_once dirname(__FILE__).'/../Graphe/Classe.php';
require_once dirname(__FILE__).'/../Graphe/Lien.php';

use eu_outters_guillaume\Util\Graphe\Classe;
use eu_outters_guillaume\Util\Graphe\Nœud;

class NœudTest extends Nœud
{
	public static $Graphe = null;
	
	public function __construct($nom)
	{
		parent::__construct(self::$Graphe);
		$this->_nom = $nom;
	}
	
	public function __toString()
	{
		return get_class($this).' '.$this->_nom;
	}
	
}

class Truc extends NœudTest
{
}

class Gusse extends NœudTest
{
	public function publier($fournils, $nom)
	{
		$pain = new Truc($nom);
		$this->est('<auteur', $pain);
		
		if(!is_array($fournils))
			$fournils = array($fournils);
		$this->diffuser($pain, $fournils);
		
		return $pain;
	}
	
	public function diffuser($pain, $fournils)
	{
		foreach($fournils as $fournil)
			if(!$this->peutIl('publier', $fournil))
				throw new \Exception($this.' ne peut pas publier dans '.$fournil);
		if(!$this->peutIl('<auteur', $pain))
			throw new \Exception($this.' n\'est pas auteur de '.$fournil);
		
		foreach($pain->quiEstIl('publié>') as $fournilActuel)
			$pain->nEstPas('publié>', $fournilActuel);
		
		foreach($fournils as $fournil)
			$pain->est('publié>', $fournil);
	}
}

class Employé extends NœudTest
{
	
}

class AutTest extends PHPUnit_Framework_TestCase
{
	public function testDroits()
	{
		/* On se crée un ensemble type Fournil:
		 * - le Fournil est privé
		 * - constitué de membres (qui peuvent lire et écrire)
		 * - par défaut, les membres diffusent vers chacun de leur fournils
		 * - ils peuvent aussi diffuser certains de leurs articles vers un site public
		 * - ce site public est consultable sans nécessité d'être membre
		 */
		
		$graphe = new Classe;
		NœudTest::$Graphe = $graphe;
		
		$graphe->définir('lecteur', 'membre> <publié', 'lisiblePar'); // membreDe, publiéDans.
		$graphe->définir('lire', 'membre> <publié | anonyme> <publié', 'êtreLuPar');
		$graphe->définir('lireOuMiens', 'lire | <publié');
		$graphe->définir('commenter', 'membre> <publié');
		$graphe->définir('publier', 'membre>'); // publierDans.
		$graphe->définir('modifier', '<auteur');
		
		$gui = new Gusse('Guillaume');
		$clo = new Gusse('Clotilde');
		$gclo = new Gusse('GClo'); // À FAIRE: c'est un compte partagé entre gui et clo.
		$fo = new Gusse('Fournil Outters');
		$fd = new Gusse('Fournil Dognin');
		$po = new Gusse('Public Outters');
		$po->est('anonyme>', $po); // Les sites publics peuvent se lire eux-mêmes (les anonymes qui lisent sont authentifiés en tant que le fournil lui-même).
		$agnès = new Gusse('Agnès');
		foreach(array($gui, $clo, $gclo) as $qui)
			foreach(array($fo, $fd, $po) as $groupe)
				$qui->est('membre>', $groupe);
		
		$pain0 = $gui->publier($fo, 'pain0');
		$this->assertTrue($clo->peutIl('lire', $pain0));
		$this->assertFalse($agnès->peutIl('lire', $pain0));
		
		$gui->diffuser($pain0, array($fo, $po));
		$this->assertFalse($agnès->peutIl('lire', $pain0));
		
		$gui->diffuser($pain0, array($fo, $fd));
		$agnès->est('membre>', $fo);
		$this->assertTrue($agnès->peutIl('lire', $pain0), 'un nouveau membre peut lire les anciens articles du fournil');
		
		$gui->diffuser($pain0, array($fo, $fd));
		$agnès->est('membre>', $fd);
		$agnès->nEstPas('membre>', $fo);
		$this->assertTrue($agnès->peutIl('lire', $pain0), 'un membre de deux fournils peut lire un article retiré de l\'un s\'il figure encore dans l\'autre');
		
		$agnès->nEstPas('membre>', $fd);
		$this->assertFalse($agnès->peutIl('lire', $pain0), 'un membre ne peut plus lire un article des fournils dont il a été éjecté');
		
		$pain1 = $gui->publier($fo, 'pain1');
		$this->assertFalse($po->peutIl('lire', $pain1));
		
		$gui->diffuser($pain1, array($fo, $po));
		$this->assertTrue($po->peutIl('lire', $pain1));
		
		$gui->diffuser($pain1, array($fo, $fd));
		$this->assertFalse($po->peutIl('lire', $pain1));
	}
}

?>
