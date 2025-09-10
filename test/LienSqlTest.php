<?php

//require_once 'PHPUnit/Framework.php';

require_once dirname(__FILE__).'/../Graphe/Classe.php';
require_once dirname(__FILE__).'/../Graphe/Feuillu.php';
require_once dirname(__FILE__).'/../Graphe/LienSql.php';

use eu_outters_guillaume\Util\Graphe\Classe;
use eu_outters_guillaume\Util\Graphe\Nœud;
use eu_outters_guillaume\Util\Graphe\Nommeur;
use eu_outters_guillaume\Util\Graphe\Feuillu;

class NœudTest extends Nœud
{
	public static $Feuillu;
	public static $Automatique = true;
	
	protected $_nom;
	protected $_d = array();
	
	public function __construct($classe, $nom)
	{
		if(!isset(self::$Feuillu))
		{
			self::$Feuillu = new Feuillu;
			self::$Feuillu->filtre = function($o) { return in_array(basename(strtr(get_class($o), '\\', '/')), array('Pain', 'Gusse')); };
		}
		parent::__construct($classe);
		$this->_nom = $nom;
	}
	
	public function __toString()
	{
		return get_class($this).' '.$this->_nom;
	}
	
	public function __isset($champ)
	{
		return isset($this->_d[$champ]);
	}
	
	public function __get($champ)
	{
		return $this->_d[$champ];
	}
	
	public function __set($champ, $valeur)
	{
		if(self::$Automatique)
			$this->_changer($champ, $valeur);
		else
			$this->_d[$champ] = $valeur;
	}
	
	public function __unset($champ)
	{
		if(self::$Automatique)
			$this->_changer($champ, null);
		else
			unset($this->_d[$champ]);
	}
	
	public function _ajouter($champ, $valeur, $inverseDéjàFait = false)
	{
		$ancienne = isset($this->_d[$champ]) ? $this->_d[$champ] : ($champ[0] == '<' ? array() : null);
		$diff = self::$Feuillu->diffAjout($ancienne, $valeur);
		$this->_appliquerDiff($champ, $diff, $inverseDéjàFait);
	}
	
	public function _retirer($champ, $valeur, $inverseDéjàFait = false)
	{
		$ancienne = isset($this->_d[$champ]) ? $this->_d[$champ] : ($champ[0] == '<' ? array() : null);
		$diff = self::$Feuillu->diffSuppression($ancienne, $valeur);
		$this->_appliquerDiff($champ, $diff, $inverseDéjàFait);
	}
	
	public function _changer($champ, $valeur)
	{
		$ancienne = isset($this->_d[$champ]) ? $this->_d[$champ] : ($champ[0] == '<' ? array() : null);
		$diff = self::$Feuillu->diff($ancienne, $valeur);
		$this->_appliquerDiff($champ, $diff);
	}
	
	protected function _appliquerDiff($champ, $diff, $inverseDéjàFait = false)
	{
		// À FAIRE: déterminer si le résultat doit être en mode unitaire ou tableau non seulement d'après l'existant, mais aussi en interrogeant la static::$Classe qui doit savoir comment la relation a été déclarée.
		
		if(!$inverseDéjàFait && (count($diff['-']['o']) || count($diff['+']['o'])))
			$nomInverse = $this->_classe->relation($champ, false)->inverse()->noms[0];
		
		if(!$inverseDéjàFait)
			foreach($diff['-']['o'] as $o)
				$o->_retirer($nomInverse, $this, true);
		
		$this->_d[$champ] = $diff['r'];
		
		if(!$inverseDéjàFait)
			foreach($diff['+']['o'] as $o)
				$o->_ajouter($nomInverse, $this, true);
	}
}

class Pain extends NœudTest
{
	public static $Classe = null;
	
	public function __construct($nom)
	{
		parent::__construct(self::$Classe, $nom);
	}
	
	public function publierDans()
	{
		$fournils = func_get_args();
		foreach($fournils as $fournil)
			if(!$this->peutIl('êtrePubliéDans', $fournil))
				throw new \Exception('Impossible de publier '.$this.' dans '.$fournil);
		$this->publiéDans = func_get_args();
		return $this;
	}
}

class Gusse extends NœudTest
{
	public static $Classe = null;
	
	public function __construct($nom)
	{
		parent::__construct(self::$Classe, $nom);
	}
	
	public function écrire($titre)
	{
		$pain = new Pain($titre);
		$pain->auteur = $this;
		return $pain;
	}
	
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

class AutTest extends PHPUnit_Framework_TestCase
{
	public function testDroitsMém()
	{
return;
		return $this->_testDroits(0);
	}
	
	public function testDroitsMémInverses()
	{
		return $this->_testDroits(1);
	}
	
	public function testDroitsSql()
	{
return;
		return $this->_testDroits(2);
	}
	
	public function testDroitsOrme()
	{
return;
		return $this->_testDroits(3);
	}
	
	public function _testDroits($mode)
	{
		/* On se crée un ensemble type Fournil:
		 * - le Fournil est privé
		 * - constitué de membres (qui peuvent lire et écrire)
		 * - par défaut, les membres diffusent vers chacun de leur fournils
		 * - ils peuvent aussi diffuser certains de leurs articles vers un site public
		 * - ce site public est consultable sans nécessité d'être membre
		 */
		
		if($mode == 2 || $mode == 3)
		{
			$cGusse = new ClasseSql('histoire'); // Un Gusse, c'est un Fournil (groupe) ou un membre.
			$cPain = new ClasseSql('gens');
		}
		else // Tout en mémoire.
		{
			$cGusse = new Classe;
			$cPain = new Classe;
		}
		$cGusse->nom = 'Classe Gusse';
		$cPain->nom = 'Classe Pain';
		if($mode == 1 || $mode == 0)
			NœudTest::$Automatique = $mode == 1;
		Gusse::$Classe = $cGusse;
		Pain::$Classe = $cPain;
		$cPain->traceur = $cGusse->traceur;
		
		// On doit déclarer les relations simples qui ont pour cible une autre classe. Ainsi groupes n'a pas besoin d'être déclarée, car elle pointe vers la même classe (un Gusse est membre d'un autre Gusse, puisque les Gusses regroupent aussi bien les personnes physiques que les Fournils familiaux).
		$cPain->relation('publiéDans', $cGusse);
		$cPain->relation('auteur', $cGusse);
		
		$cGusse->définir('lireEnTantQuePublic', '<publiéDans'); // Pour lire les articles publics d'un groupe, il faut simplement qu'ils aient été publiés sur le site "public" correspondant. Le quidam qui se connecte au site public est authentifié en tant que le site lui-même.
		$cGusse->définir('lireMiens', 'groupes <publiéDans | <auteur'); // Ceux des groupes dont je suis membre, les miens (des fois qu'ils ne seraient publiés dans aucun de mes groupes). On pourra rajouter plus tard 'abonnements <publiéDans'.
		$cGusse->définir('lire', 'lireMiens | lireEnTantQuePublic');
		$cGusse->définir('commenter', 'groupes <publiéDans');
		$cGusse->définir('modifier', '<auteur');
		$cGusse->définir('changerAuteurDe', '<proprio* <auteur');
		$cGusse->définir('changerAuteurEn', '<proprio*');
		$cPain->définir('êtrePubliéDans', 'auteur proprio* groupes <publicDe?');
		
		$gui = new Gusse('Guillaume');
		$clo = new Gusse('Clotilde');
		$gclo = new Gusse('GClo'); // À FAIRE: c'est un compte partagé entre gui et clo.
		$fo = new Gusse('Fournil Outters');
		$fd = new Gusse('Fournil Dognin');
		$po = new Gusse('Fournil Outters public');
		$po->publicDe = $fo;
		if($mode == 0)
			$fo->{'<publicDe'} = array($po);
		$agnès = new Gusse('Agnès');
		$nawak = new Gusse('Un mec qui passait par là');
		foreach(array($gui, $clo, $gclo) as $qui)
		{
			$qui->groupes = array($fo, $fd);
			if($mode == 0)
			{
				$fo->{'<groupes'}[] = $qui;
				$fd->{'<groupes'}[] = $qui;
			}
		}
		$agnès->groupes = array($fo);
		if($mode == 0)
			$fo->{'<groupes'}[] = $agnès;
		
		$hPubOutters = $gui->écrire('hPubOutters')->publierDans($fo);
		if($mode == 0)
			$fo->{'<publiéDans'}[] = $hPubOutters;
		$this->assertFalse($nawak->peutIl('lire', $hPubOutters));
		$this->assertFalse($po->peutIl('lire', $hPubOutters));
		$this->assertTrue($agnès->peutIl('lire', $hPubOutters));
		$this->assertTrue($clo->peutIl('lire', $hPubOutters));
		$hPubOutters->publierDans($fo, $po);
		if($mode == 0)
		{
			$fo->{'<publiéDans'}[] = $hPubOutters;
			$po->{'<publiéDans'}[] = $hPubOutters;
		}
		$this->assertFalse($nawak->peutIl('lire', $hPubOutters)); // Un mec qui passait par là ne peut lire les sites publics (il lui faut s'authentifier en tant que le site public auquel il souhaite accéder; s'il n'y avait cette sélection, les anonymes verraient d'un coup TOUS les articles de TOUS les sites publics).
		$this->assertTrue($po->peutIl('lire', $hPubOutters));
		$this->assertTrue($agnès->peutIl('lire', $hPubOutters));
		$this->assertTrue($clo->peutIl('lire', $hPubOutters));
		
		$hDognin = $clo->écrire('hDognin')->publierDans($fd);
		$this->assertFalse($agnès->peutIl('lire', $hDognin));
		$this->assertTrue($gui->peutIl('lire', $hDognin));
		
		$hOutters = $gui->écrire('hOutters')->publierDans($fo);
		$hGui = $gui->écrire('hGui'); // Pas de publication.
		$hPubAgnès = $agnès->écrire('hPubAgnès')->publierDans($po);
		
		$this->assertEquals(0, count($nawak->quiPeutIl('lire'))); // Rien, tant qu'il ne se déclare pas comme accédant à tel ou tel site public.
		$this->assertEquals(2, count($po->quiPeutIl('lire'))); // Les deux publics du Fournil Outters (d'Agnès et Guillaume)
		$this->assertEquals(3, count($agnès->quiPeutIl('lire'))); // + celui dans le Fournil privé Outters
		$this->assertEquals(3, count($clo->quiPeutIl('lire'))); // + celui dans le Fournil Dognin - le public d'Agnès (publié seulement côté public, donc non visible par défaut côté privé afin d'éviter de polluer).
		$this->assertEquals(4, count($gui->quiPeutIl('lire'))); // + son privé
	}
}

?>
