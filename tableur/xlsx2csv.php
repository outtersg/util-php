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

require_once dirname(__FILE__).'/../xml/chargeur.php';
require_once dirname(__FILE__).'/../xml/compo.php';
require_once dirname(__FILE__).'/../xml/composimple.php';

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
	
	public function styles()
	{
		return $this->_xml('xl/styles.xml');
	}
	
	public function chaînes()
	{
		return $this->_xml('xl/sharedStrings.xml');
	}
	
	public function feuille($n)
	{
		return $this->_xml("xl/worksheets/sheet$n.xml", false);
	}
}

/*- Analyse du XML -----------------------------------------------------------*/

class CompoStyles extends CompoSimple
{
	public function __construct()
	{
		parent::__construct(array());
		$this->formats = array();
	}
	
	public function &entrerDans(&$depuis, $nom, $attributs)
	{
		switch($nom)
		{
			case 'cellXfs': $r = 'formats'; return $r;
			case 'xf':
				if($depuis == 'formats')
					$this->formats[] = $attributs['numFmtId'] == 14 ? 'date' : null;
				break;
		}
		$r = null;
		return $r;
	}
}

class CompoChaînes extends CompoSimple
{
	public function __construct()
	{
		parent::__construct(array());
		$this->chaînes = array();
	}
	
	public function &entrerDans(&$depuis, $nom, $attributs)
	{
		switch($nom)
		{
			case 'si': $num = count($this->chaînes); $this->chaînes[$num] = ''; return $this->chaînes[$num];
			case 't': return $depuis; // Le <t> vient alimenter la chaîne créée par son <si> conteneur.
		}
		$r = null;
		return $r;
	}
}

class CompoFeuille extends CompoSimple
{
	public function __construct()
	{
		parent::__construct(array());
		$this->colonnes = array();
		$this->lignes = array();
	}
	
	public function colEnNum($col)
	{
		$n = 0;
		$A = ord('A');
		while(strlen($col) > 0)
		{
			$n *= 26;
			$n += ord(substr($col, 0, 1)) - $A + 1;
			$col = substr($col, 1);
		}
		--$n;
		return $n;
	}
	
	public function &entrerDans(&$depuis, $nom, $attributs)
	{
		switch($nom)
		{
			case 'sheetData': $r = 'd'; return $r;
			case 'col':
				$colonne = array();
				if(isset($attributs['style']))
					$colonne['style'] = 0 + $attributs['style'];
				for($i = $attributs['min']; $i <= $attributs['max']; ++$i)
					$this->colonnes[$i] = $colonne;
				break;
			case 'row':
				if($depuis == 'd')
				{
					$numLigne = $attributs['r'] - 1;
					$this->lignes[$numLigne] = array('d' => array(), 't' => array(), 's' => array()); // Nouvelle ligne, avec ses colonnes de données, et les éventuels types et styles.
					return $this->lignes[$numLigne];
				}
				break;
			case 'c':
				if(is_array($depuis))
				{
					if(isset($attributs['t']) && !in_array($attributs['t'], array('s', 'str', 'n')))
					{
						fprintf(STDERR, '# Je ne sais pas traiter le type de case \''.$attributs['t']."'\n");
						break;
					}
					$numColonne = $attributs['r'];
					if(!preg_match('#^([A-Z]+)[0-9]+$#', $attributs['r'], $rés))
					{
						fprintf(STDERR, '# Drôle de numéro de case: \''.$attributs['r']."'\n");
						break;
					}
					$numCol = $this->colEnNum($rés[1]);
					if(isset($attributs['t']))
						$depuis['t'][$numCol] = $attributs['t'];
					if(isset($attributs['s']))
						$depuis['s'][$numCol] = 0 + $attributs['s'];
					$depuis['d'][$numCol] = '';
					return $depuis['d'][$numCol];
				}
				break;
			case 'v':
				if(is_string($depuis))
					return $depuis; // Le <v> vient remplir la case qu'on avait précréée en mémoire.
				break;
		}
		$r = null;
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
			$cheminSortie = strtr($chemin, array('xlsx' => $numFeuille == 1 ? 'csv' : $numFeuille.'.csv'));
			if($cheminSortie == $chemin)
				throw new Exceprtion("# Oups, je m'apprêtais à écraser $chemin.");
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
