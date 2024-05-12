<?php
/*
 * Copyright (c) 2015,2019,2023 Guillaume Outters
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */


class Processus
{
	public function __construct($argv)
	{
		$prems = true;
		foreach($argv as & $arg)
			if($prems)
			{
				$arg = escapeshellcmd($arg);
				$prems = false;
			}
			else
				$arg = escapeshellarg($arg);
		
		$desc = array
		(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$this->_tubes = array();
		$this->_fils = proc_open(implode(' ', $argv), $desc, $this->_tubes);
	}
	
	public function attendre($stdin = null)
	{
		if(is_string($stdin) && !is_file($stdin))
			$stdin = new SourceProcessusChaîne($stdin);
		while(!is_numeric($reTour = $this->attendreQuelqueChose($stdin))) $stdin = null;
		return $reTour;
	}
	
	protected function _filsToujoursLà()
	{
		if(!isset($this->_fils)) return false;
		$état = proc_get_status($this->_fils);
		if(!$état) return false;
		if(!($this->_filsToujoursLà = $état['running']) && !isset($this->_filsStatut)) // D'après la doc: exitcode n'est lisible qu'à la première consultation en !running. On mémorise avant de perdre l'info.
			$this->_filsStatut = $état['exitcode'];
		return $this->_filsToujoursLà;
	}
	
	public function brancher($stdin = null)
	{
		if(!$stdin)
		{
		fclose($this->_tubes[0]);
		
		$entrees = array();
		}
		else
		{
			$entrees = array(0 => $this->_tubes[0]);
			$this->_initialiserÉcritures($stdin);
		}

		$sorties = array(1 => $this->_tubes[1], 2 => $this->_tubes[2]);
		$erreurs = array();
			$this->_ = [ $entrees, $sorties, $erreurs ];
	}
	
	/**
	 * Attend qu'il se passe quelque chose de particulier.
	 * Le "particulier" étant déterminé par renvoi par _sortie() d'autre chose que du null; par exemple un booléen, ou un objet (éviter les entiers, indistinctibles du retour de fin de processus).
	 * N.B.: pourrait s'apparenter à un yield en PHP 5.5.
	 */
	public function attendreQuelqueChose($stdin = null)
	{
		if(!isset($this->_))
			$this->brancher($stdin);
		else
			$pasLaPremièreFois = true;
			list($entrees, $sorties, $erreurs) = $this->_;
		if(isset($pasLaPremièreFois))
			if(isset($this->_source) && is_object($this->_source) && method_exists($this->_source, 'poursuivre'))
				$this->_source->poursuivre($stdin);
		
		while(count($this->_[0]) + count($this->_[1]))
			if(($qqc = self::Aniqsniq(array($this))))
				return $qqc[1]; // Sortie prématurée en cas d'événement notable.
		
		fclose($this->_tubes[1]);
		fclose($this->_tubes[2]);
		// De belle mort: si le fils a déjà quitté proprement (par exemple suite à notre fermeture d'entrée),
		// ou si on réussit la fermeture de notre côté (et on laisse le fils se débrouiller pour mourir tout seul).
		$this->_filsToujoursLà();
		$retour = proc_close($this->_fils);
		// Comme commenté dans https://fr.php.net/proc_close: parfois le code de retour est un peu foireux, notamment si le processus est mort de sa belle mort (par exemple détectant qu'on lui fermait l'entrée). proc_get_status() est plus fiable, on l'exploite donc.
		if($retour == -1) $retour = $this->_filsStatut;
		
		unset($this->_);
		
		return $retour;
	}
	
	/**
	 * Traite le premier événement sur flux (préalablement détecté par Aniqsniq par exemple).
	 * 
	 * @return null|[ $this, <retour> ]
	 */
	protected function _traiterPremierÉv()
	{
		if(!($év = array_shift($this->_évs))) return;
		
		list($sens, $fd, $flux) = $év;
		$r = null;
		switch($sens)
		{
			case self::PÀF:
				// On ne lit pas une pseudo-disponibilité (émulée si !$this->_filsToujoursLà, pour fermeture).
				$bloc = isset($flux) ? fread($flux, 0x4000) : null;
				$r = $this->_sortie($fd, $bloc);
				if(!isset($bloc) || strlen($bloc) == 0) // Fin de fichier, on arrête de le surveiller.
					unset($this->_[1][$fd]);
				break;
			case self::PÀM:
				if($this->_écrire($this->_tubes[$év[1]]) === false)
				{
					unset($this->_[0][0]);
					fclose($this->_tubes[$év[1]]);
					unset($this->_source);
				}
				break;
		}
		
		return array($this, $r);
	}
	
	/**
	 * Attendre N'Importe Quoi Sur N'Importe Qui.
	 * Attend qu'il se passe quelque chose pour n'importe lequel d'une liste de processus.
	 * 
	 * @param Processus[] $ps Surveillés.
	 */
	public static function Aniqsniq($ps)
	{
		/*- Précédent tour de boucle -*/
		
		// Si au précédent coup on avait précalculé des événements sans les gérer (1 seul retour disputé par 2 processus), on dépile maintenant.
		foreach($ps as $p)
			if(($qqc = $p->_traiterPremierÉv()))
				return $qqc;
		
		/*- Ce tour de boucle -*/
		
		$es = array(0 => array(), 1 => array()); // 0 regroupe les entrées de nos processus (donc là où on pourra écrire), 1 leurs sorties.
		$surv = array();
		$complémentÀVide = array();
		$nToujoursLà = 0;
		foreach($ps as $p)
		{
			if(($toujoursLà = $p->_filsToujoursLà()))
				++$nToujoursLà;
			foreach($es as $sens => $rien)
				foreach($p->_[$sens] as $fd => $ressource)
				{
					$numSurv = count($surv);
					$surv[$numSurv] = array($p, array($sens ? self::PÀF : self::PÀM, $fd));
					$es[$sens][$numSurv] = $ressource;
					// Si le processus est mort, on prépare un pseudo-événement de lecture à vide (EOF) qui sera agrégé à défaut de possibilité de lecture à plein.
					if(!$toujoursLà)
						$complémentÀVide[$numSurv] = null;
				}
		}
		$erreurs = array();
		// Temps d'attente: si un processus vient de mourir, pas la peine d'attendre, le stream_select n'est là que pour s'assurer en une dernière passe que ses tubes, qui ne risquent plus de se remplir, ont bien été consommés de notre côté.
		if(stream_select($es[1], $es[0], $erreurs, $nToujoursLà == count($ps) ? 1 : 0, 10000))
		{
			// Donc là maintenant $es[1] contient les sorties de processus prêtes à être lues, $es[0] leurs entrées prêtes à manger.
			// Le + $complémentÀVide fait que celui-ci ne transparaîtra que si le stream_select n'a rien détecté à faire sur cet identifiant.
			foreach($es[1] + $es[0] + $complémentÀVide as $numSurv => $flux)
			{
				$év = $surv[$numSurv][1];
				$év[] = $flux; // Soit le flux si c'est le stream_select qui voit du potentiel, soit null si c'est $complémentÀVide.
				$surv[$numSurv][0]->_évs[] = $év;
			}
		}
		
		foreach($ps as $p)
			if(($qqc = $p->_traiterPremierÉv()))
				return $qqc;
	}
	
	protected function _initialiserÉcritures($source)
	{
		stream_set_blocking($this->_tubes[0], false);
		/* À FAIRE: $source Resource; $source fonction. */
		if(is_string($source))
		{
			if(is_file($source))
				$source = new SourceProcessusFlux(fopen($source, 'rb'));
			else
				$source = new SourceProcessusChaîne($source, true);
		}
		$this->_source = $source;
		$this->_résiduSource = '';
	}
	
	protected function _écrire($stdinProcessus)
	{
		/* À FAIRE: $source Resource; $source fonction. */
		if(!isset($this->_source)) return; // null et non false: le false est réservé au processus cru ouvert mais en fait fermé; là on est su fermé depuis longtemps.
			if($this->_source === false) // Plus rien à entrer.
				return false;
		if(!strlen($this->_résiduSource))
			$this->_résiduSource = $this->_source->lire();
		if($this->_résiduSource === '' && !$this->_filsToujoursLà)
			unset($this->_résiduSource);
		
			// Si on n'a rien pu écrire (alors que nous avons été invoqués suite à un stream_select nous informant qu'on pouvait écrire),
			// c'est que l'autre côté a été fermé. Du moins c'est le comportement observé sur un psql tombant sur une instruction SQL invalide.
			if
			(
				isset($this->_résiduSource) && ($nÉcrits = strlen($this->_résiduSource))
				&& !($nÉcrits = fwrite($stdinProcessus, $this->_résiduSource))
			)
				unset($this->_résiduSource);
			
			if(!isset($this->_résiduSource))
			{
			$this->_source->terminer();
				$this->_source = false; // false et non null: un dernier tour pour ne pas oublier de fermer le symétrique (entrée du Processus dans lequel on déversait notre source).
				return false;
			}
				$this->_résiduSource = substr($this->_résiduSource, $nÉcrits);
	}
	
	protected function _sortie($fd, $bloc)
	{
		fwrite($fd == 1 ? STDOUT : STDERR, $bloc);
	}
	
	protected $_fils;
	protected $_filsToujoursLà;
	protected $_filsStatut;
	protected $_tubes;
	protected $_source;
	protected $_résiduSource;
	protected $_; // Contexte d'attendreQuelqueChose().
	protected $_évs = array(); // Événements détectés en attente de traitement.
	
	const PÀF = 'prêt à fournir'; // Notre processus a des choses à nous dire.
	const PÀM = 'prêt à manger'; // Notre processus a de la place pour qu'on lui balance de la donnée.
}

class ProcessusCauseur extends Processus
{
	public function __construct($argv)
	{
		$this->_contenuSortie = '';
		parent::__construct($argv);
	}
	
	protected function _sortie($fd, $bloc)
	{
		$this->_contenuSortie .= $bloc;
	}
	
	public function contenuSortie()
	{
		return $this->_contenuSortie;
	}
	
	protected $_contenuSortie;
}

class ProcessusLignes extends Processus
{
	public function __construct($argv, $sorteurLignes = null)
	{
		if(!isset($this->_fdls))
			$this->finDeLigne("#[\n]#");
		$this->_sorteur = $sorteurLignes;
		parent::__construct($argv);
	}
	
	public function finDeLigne($stdout, $stderr = null)
	{
		foreach(array(1 => $stdout, 2 => $stderr) as $fd => $expr)
		{
			$this->_fdls[$fd] = $expr;
			
			// Et un boucleur adapté.
			
			if(!isset($expr)) $this->_boucleurs[$fd] = null;
			else if(!isset($this->_boucleurs[$fd]) || !preg_match($expr, $this->_boucleurs[$fd]))
			{
				// Recherche du premier caractère qui se retrouverait dans $expr.
				$boucleur = substr(strtr($expr, '[]|*+', ''), 0, 1);
				if(!preg_match($expr, $boucleur))
					$boucleur = "\n";
				$this->_boucleurs[$fd] = $boucleur;
			}
		}
	}
	
	public function attendre($stdin = null)
	{
		foreach(array(1, 2) as $fd)
			$this->_contenuSorties[$fd] = null;
		$r = parent::attendre($stdin);
		foreach(array(1, 2) as $fd)
			if(isset($this->_contenuSorties[$fd]))
				$this->_sortie($fd, $this->_boucleurs[$fd]);
		return $r;
	}
	
	protected function _sortie($fd, $bloc)
	{
		$r = null;
		
		if(!isset($bloc)) { $touteFin = true; $bloc = ''; }
		if(isset($this->_contenuSorties[$fd]))
			$bloc = $this->_contenuSorties[$fd].$bloc;
		// preg_match_all est bien plus rapide qu'un strpos (24 ms puis 4 ms, contre 1,7 s, pour un fichier de 4 Mo).
		// php -r '$c = file_get_contents("0.sql"); for($i = 10; --$i >= 0;) { $t0 = microtime(true); preg_match_all("#\n#", $c, $re, PREG_OFFSET_CAPTURE); $r = []; foreach($re[0] as $re1) $r[] = $re1[1]; echo (microtime(true) - $t0)." p ".count($r)."\n"; $t0 = microtime(true); $r = []; for($d = 0; ($f = strpos($c, "\n", $d)) !== false; $d = $f + 1) $r[] = $d; echo (microtime(true) - $t0)." s ".count($r)."\n"; }'
		if(isset($this->_fdls[$fd]))
			preg_match_all($this->_fdls[$fd], $bloc, $fragments, PREG_OFFSET_CAPTURE);
		else
			$fragments = array(array(array('', strlen($bloc))));
		$début = 0;
		foreach($fragments[0] as $fragment)
		{
			$r = $this->_sortirLigne(substr($bloc, $début, $fragment[1] - $début), $fd, $fragment[0], $r);
			$début = $fragment[1] + strlen($fragment[0]);
		}
		$this->_contenuSorties[$fd] = $début < strlen($bloc) ? substr($bloc, $début) : null;
		if(isset($this->_contenuSorties[$fd]) && isset($touteFin))
			$r = $this->_sortirLigne($this->_contenuSorties[$fd], $fd, $this->_boucleurs[$fd], $r);
		
		return $r;
	}
	
	protected function _sortirLigne($ligne, $fd, $finDeLigne, $accuRés)
	{
		if(!isset($this->_sorteur)) return;
		
		$params = array($ligne, $fd, $finDeLigne);
		if(is_array($sorteur = $this->_sorteur) && count($sorteur) > 2)
			$params = array_merge($params, array_splice($sorteur, 2));
		$r = call_user_func_array($sorteur, $params);
		
		return $r !== null || $accuRés === null ? $r : $accuRés;
	}
	
	protected $_fdls;
	/**
	 * Ajouté en fin de fichier si absent.
	 * Doit être vérifié par $_fdls.
	 */
	protected $_boucleurs;
	protected $_sorteur;
	protected $_contenuSorties;
}

class SourceProcessus
{

}

class SourceProcessusChaîne extends SourceProcessus
{
	public function __construct($chaîne = null, $réalimentable = false)
	{
		$this->poursuivre($chaîne);
		$this->_réalimentable = $réalimentable;
	}
	
	public function plein()
	{
		return isset($this->_chaîne) && strlen($this->_chaîne) > 0;
	}
	
	public function lire()
	{
		if(isset($this->_chaîne))
		{
			$r = $this->_chaîne;
			$this->_chaîne = $this->_réalimentable ? '' : null;
			return $r;
		}
	}
	
	public function ouverte()
	{
		return $this->_dedans;
	}
	
	public function terminer()
	{
	}
	
	/**
	 * Pour réalimenter la source.
	 */
	public function poursuivre($ajout)
	{
		if($this->_dedans = isset($ajout))
		{
			if(!$this->_réalimentable) throw new Exception('Source non réalimentable');
			if(isset($this->_chaîne))
				$this->_chaîne .= $ajout;
			else
				$this->_chaîne = $ajout;
		}
	}
	
	protected $_chaîne;
	protected $_dedans;
	protected $_réalimentable = true;
}

class SourceProcessusFlux extends SourceProcessus
{
	public function __construct($fd)
	{
		$this->_flux = $fd;
	}
	
	public function lire()
	{
		if(!isset($this->_flux)) return null;
		if(!strlen($r = fread($this->_flux, 0x100000)) && !$this->ouverte())
		{
			$this->terminer();
			$r = null;
		}
		
		return $r;
	}
	
	public function ouverte()
	{
		return isset($this->_flux) && !feof($this->_flux);
	}
	
	public function terminer()
	{
		if(isset($this->_flux)) fclose($this->_flux);
		$this->_flux = null;
	}
	
	protected $_flux;
}

?>
