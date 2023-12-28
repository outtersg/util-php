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
		while(!is_numeric($reTour = $this->attendreQuelqueChose($stdin))) $stdin = null;
		return $reTour;
	}
	
	/**
	 * Attend qu'il se passe quelque chose de particulier.
	 * Le "particulier" étant déterminé par renvoi par _sortie() d'autre chose que du null; par exemple un booléen, ou un objet (éviter les entiers, indistinctibles du retour de fin de processus).
	 * N.B.: pourrait s'apparenter à un yield en PHP 5.5.
	 */
	public function attendreQuelqueChose($stdin = null)
	{
		if(!isset($this->_))
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
		else
		{
			list($entrees, $sorties, $erreurs) = $this->_;
			if(is_object($this->_source) && method_exists($this->_source, 'poursuivre'))
				$this->_source->poursuivre($stdin);
		}
		
		while(count($sorties) + count($entrees))
		{
			$sortiesModif = $sorties; // Copie de tableau.
			$entréesModifiée = $entrees;
			if(is_object($this->_source) && method_exists($this->_source, 'plein') && $this->_source->plein() === false)
				unset($entréesModifiée[0]);
				/* À FAIRE: proposer à la SourceProcessusFlux de participer au stream_select, en alternative au plein() qui n'est pas temps réel. */
			if($nFlux = stream_select($sortiesModif, $entréesModifiée, $erreurs, 1))
			{
				foreach($sortiesModif as $flux)
				{
					foreach($sorties as $fd => $fluxSurveillé)
						if($fluxSurveillé == $flux)
							break;
					$bloc = fread($flux, 0x4000);
					$retourSortir = $this->_sortie($fd, $bloc);
					if(strlen($bloc) == 0) // Fin de fichier, on arrête de le surveiller.
						unset($sorties[$fd]);
					
					if(isset($retourSortir))
					{
						$this->_ = [ $entrees, $sorties, $erreurs ];
						return $retourSortir;
					}
				}
				if(count($entréesModifiée))
					if($this->_écrire($this->_tubes[0]) === false)
					{
						unset($entrees[0]); // Il n'y en a qu'une.
						fclose($this->_tubes[0]);
						unset($this->_source);
					}
			}
		}
		
		fclose($this->_tubes[1]);
		fclose($this->_tubes[2]);
		$retour = proc_close($this->_fils);
		
		unset($this->_);
		
		return $retour;
	}
	
	protected function _initialiserÉcritures($source)
	{
		stream_set_blocking($this->_tubes[0], false);
		/* À FAIRE: $source Resource; $source fonction. */
		if(is_string($source))
		{
			if(is_file($source))
				$this->_source = new SourceProcessusFlux(fopen($source, 'rb'));
			else
				$this->_source = new SourceProcessusChaîne($source);
		}
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
	protected $_tubes;
	protected $_source;
	protected $_résiduSource;
	protected $_; // Contexte d'attendreQuelqueChose().
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
			$fragments = array(array(array($bloc, 0)));
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
		
		$r =
			call_user_func($this->_sorteur, $ligne, $fd, $finDeLigne);
		
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
	public function __construct($chaîne = null)
	{
		$this->poursuivre($chaîne);
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
			$this->_chaîne = '';
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
			if(isset($this->_chaîne))
				$this->_chaîne .= $ajout;
			else
				$this->_chaîne = $ajout;
	}
	
	protected $_chaîne;
	protected $_dedans;
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
