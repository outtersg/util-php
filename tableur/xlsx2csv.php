<?php
/*
 * Copyright (c) 2018 Guillaume Outters
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

/*- Commençons par décompresser ----------------------------------------------*/

class Décompresseur
{
	public function __construct($chemin)
	{
		$this->archive = new ZipArchive();
		if(!($this->archive->open($chemin)))
			throw new Exception('Impossible d\'ouvrir '.$chemin);
	}
	
	protected function _xml($chemin, $bloquant = true)
	{
		/* À FAIRE: essayer en plus de ZipArchive: le gestionnaire de flux zip://#chemin, 7za, unzip. */
		if(!($r = $this->archive->getStream($chemin)) && $bloquant)
			throw new Exception('Impossible de lire '.$chemin.' dans l\'archive');
		return $r;
	}
}

/*- Ponte de CSV -------------------------------------------------------------*/

class Poule
{
	public function __construct($sortie, $formats, $chaînes)
	{
		$this->sortie = $sortie;
		$this->formats = $formats;
		$this->chaînes = $chaînes;
	}
	
	public function pondre($colonnes, $lignes)
	{
		$formatsColonnes = array();
		foreach($colonnes as $numCol => $colonne)
			if(isset($colonne['style']) && isset($this->formats[$colonne['style']]))
				$formatsColonnes[$numCol] = $this->formats[$colonne['style']];
		$maxCol = 0;
		foreach($lignes as $ligne)
		{
			$numsColonne = array_keys($ligne['d']);
			$maxColLigne = count($numsColonne) > 1 ? call_user_func_array('max', $numsColonne) : $numsColonne[0];
			if($maxColLigne > $maxCol)
				$maxCol = $maxColLigne;
		}
		$this->ligneVide = array_fill(0, $maxCol + 1, '');
			
		ksort($lignes);
		foreach($lignes as $ligne)
			$this->sortie->sortir($this->pondreLigne($formatsColonnes, $ligne));
	}
	
	public function pondreLigne($formatsColonnes, $ligne)
	{
		$csv = $ligne['d'];
		// Les références aux chaînes en dur.
		foreach($ligne['t'] as $numCol => $type)
			if($type == 's')
				$csv[$numCol] = $this->chaînes[$csv[$numCol]];
		// Style: celui de la cellule s'il y en a un propre, à défaut celui de sa colonne.
		$formats = array();
		foreach($ligne['s'] as $numCol => $idFormat)
			if(isset($this->formats[$idFormat]))
				$formats[$numCol] = $this->formats[$idFormat];
		$formats += $formatsColonnes;
		foreach($formats as $numCol => $format)
			switch($format)
			{
				case 'date':
					if(empty($csv[$numCol])) break; // On ne va pas retraiter les cases vides, non plus.
					$d = new DateTime('1900-01-01T06:00:00+00:00');
					$d->add(new DateInterval('P'.($csv[$numCol] - 2).'D'));
					$csv[$numCol] = $d->format('d/m/Y');
					break;
			}
		// On fournit des valeurs pour les colonnes vides.
		$csv += $this->ligneVide;
		// Et tout ce petit monde est ordonné.
		ksort($csv);
		return $csv;
	}
}

class Panier
{
	public function ouvrir($chemin)
	{
		$this->sortie = fopen($chemin, 'w');
	}
	
	public function sortir($ligne)
	{
		fputcsv($this->sortie, $ligne, ';');
	}
	
	public function fermer()
	{
		fclose($this->sortie);
	}
}

/*- Processus général --------------------------------------------------------*/

class Traiteur
{
	public function traiter($chemin)
	{
		$c = $this->_rhino($chemin);
		
		$panier = new Panier();
		$poule = new Poule($panier, $c->formats, $c->chaînes);
		
		for($numFeuille = 0; $compoFeuille = $c->feuille(++$numFeuille);)
		{
			$cheminSortie = preg_replace('/[.][^.]*$/', $numFeuille == 1 ? '.csv' : '.'.$numFeuille.'.csv', $chemin);
			if($cheminSortie == $chemin)
				throw new Exception("# Oups, je m'apprêtais à écraser $chemin.");
			$panier->ouvrir($cheminSortie);
			$poule->pondre($compoFeuille->colonnes, $compoFeuille->lignes);
			$panier->fermer();
		}
	}
	
	/**
	 * Le rhino charge.
	 */
	protected function _rhino($chemin)
	{
		/* À FAIRE: pour certains suffixes, une liste de chargeurs, qu'on essaiera les uns après les autres (par exemple avec une fonction peut()). Le premier à répondre emporte le pactole (et si ça lui a coûté de calculer peut(), libre à lui de mémoriser son prétravail quelque part chez lui où le lire() n'aura plus grand-chose à faire). */
		$classe = 'ChargeurXlsx';
		
		require_once dirname(__FILE__).'/'.$classe.'.php';
		$c = new $classe();
		$c->lire($chemin);
		
		return $c;
	}
}

class Sorteur
{
	public function err($errno, $errstr, $errfile, $errline, $errcontext)
	{
		throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
	}
}

try
{
	$sorteur = new Sorteur();
	set_error_handler(array($sorteur, 'err'));
	
	$t = new Traiteur();
	array_shift($argv);
	foreach($argv as $arg)
		$t->traiter($arg);
}
catch(Exception $e)
{
	fprintf(STDERR, "\033[31m# ".$e->getFile().':'.$e->getLine().': '.$e->getMessage()."\033[0m\n");
	fprintf(STDERR, print_r(array_map('affTrace', array_slice($e->getTrace(), 0, 8)), true));
	exit(1);
}

function affTrace($x)
{
	return (isset($x['file']) && isset($x['line']) ? $x['file'].':'.$x['line'].': ' : '').$x['function'];
}

?>
