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
		
		while(count($sorties) + count($entrees))
		{
			$sortiesModif = $sorties; // Copie de tableau.
			$entréesModifiée = $entrees;
			if($nFlux = stream_select($sortiesModif, $entréesModifiée, $erreurs, 1))
			{
				foreach($sortiesModif as $flux)
				{
					foreach($sorties as $fd => $fluxSurveillé)
						if($fluxSurveillé == $flux)
							break;
					$bloc = fread($flux, 0x4000);
					$this->_sortie($fd, $bloc);
					if(strlen($bloc) == 0) // Fin de fichier, on arrête de le surveiller.
						unset($sorties[$fd]);
				}
				if(count($entréesModifiée))
					if($this->_écrire($stdin, $this->_tubes[0]) === false)
					{
						unset($entrees[0]); // Il n'y en a qu'une.
						fclose($this->_tubes[0]);
					}
			}
		}
		
		fclose($this->_tubes[1]);
		fclose($this->_tubes[2]);
		$retour = proc_close($this->_fils);
		
		return $retour;
	}
	
	protected function _initialiserÉcritures($source)
	{
		stream_set_blocking($this->_tubes[0], false);
		/* À FAIRE: $source Resource; $source fonction. */
		if(is_string($source))
		{
			if(is_file($source))
			$this->_source = fopen($source, 'rb');
			else
			{
				$this->_source = true;
				$this->_résiduSource = $source;
			}
		}
	}
	
	protected function _écrire($source, $stdinProcessus)
	{
		/* À FAIRE: $source Resource; $source fonction. */
		if(is_string($source) && isset($this->_source))
		{
			if($this->_source === false) // Plus rien à entrer.
				return false;
			if(!isset($this->_résiduSource))
			if(is_resource($this->_source))
					if
					(
						($this->_résiduSource = fread($this->_source, 0x100000)) === false
						|| (!strlen($this->_résiduSource) && feof($this->_source))
					)
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
			if(is_resource($this->_source))
				fclose($this->_source);
				$this->_source = false;
				return false;
			}
			if($nÉcrits == strlen($this->_résiduSource))
				unset($this->_résiduSource);
			else
				$this->_résiduSource = substr($this->_résiduSource, $nÉcrits);
		}
	}
	
	protected function _sortie($fd, $bloc)
	{
		fwrite($fd == 1 ? STDOUT : STDERR, $bloc);
	}
	
	protected $_fils;
	protected $_tubes;
	protected $_source;
	protected $_résiduSource;
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
	public $exprFinDeLigne = "#[\n]#";
	/**
	 * Ajouté en fin de fichier si absent.
	 * Doit être vérifié par $exprFinDeLigne.
	 */
	public $boucleur = "\n";
	/* À FAIRE: vérifier qu'il est vérifié par la regex. */
	
	public function __construct($argv, $sorteurLignes = null)
	{
		$this->_sorteur = $sorteurLignes;
		parent::__construct($argv);
	}
	
	public function attendre($stdin = null)
	{
		foreach(array(1, 2) as $fd)
			$this->_contenuSorties[$fd] = null;
		$r = parent::attendre($stdin);
		foreach(array(1, 2) as $fd)
			if(isset($this->_contenuSorties[$fd]))
				$this->_sortie($fd, $this->boucleur);
		return $r;
	}
	
	protected function _sortie($fd, $bloc)
	{
		if(!isset($bloc)) { $touteFin = true; $bloc = ''; }
		if(isset($this->_contenuSorties[$fd]))
			$bloc = $this->_contenuSorties[$fd].$bloc;
		// preg_match_all est bien plus rapide qu'un strpos (24 ms puis 4 ms, contre 1,7 s, pour un fichier de 4 Mo).
		// php -r '$c = file_get_contents("0.sql"); for($i = 10; --$i >= 0;) { $t0 = microtime(true); preg_match_all("#\n#", $c, $re, PREG_OFFSET_CAPTURE); $r = []; foreach($re[0] as $re1) $r[] = $re1[1]; echo (microtime(true) - $t0)." p ".count($r)."\n"; $t0 = microtime(true); $r = []; for($d = 0; ($f = strpos($c, "\n", $d)) !== false; $d = $f + 1) $r[] = $d; echo (microtime(true) - $t0)." s ".count($r)."\n"; }'
		preg_match_all($this->exprFinDeLigne, $bloc, $fragments, PREG_OFFSET_CAPTURE);
		$début = 0;
		foreach($fragments[0] as $fragment)
		{
			$this->_sortirLigne(substr($bloc, $début, $fragment[1] - $début), $fd, $fragment[0]);
			$début = $fragment[1];
		}
		$this->_contenuSorties[$fd] = $début < strlen($bloc) ? substr($bloc, $début) : null;
		if(isset($this->_contenuSorties[$fd]) && isset($touteFin))
			$this->_sortirLigne($this->_contenuSorties[$fd], $fd, $this->boucleur);
	}
	
	protected function _sortirLigne($ligne, $fd, $finDeLigne)
	{
		if(isset($this->_sorteur))
			call_user_func($this->_sorteur, $ligne, $fd, $finDeLigne);
	}
	
	protected $_sorteur;
	protected $_contenuSorties;
}

?>
