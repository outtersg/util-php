<?php
/*
 * Copyright (c) 2018,2020 Guillaume Outters
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

class ChargeurXlsx extends Chargeur
{
	public function lire($chemin)
	{
		$this->d = new XlsxDécompressé($chemin);
		
		$compoStyles = new CompoStyles();
		$compoChaînes = new CompoChaînes();
		
		$this->charger('-', 'styleSheet', $compoStyles, false, $f = $this->d->styles());
		fclose($f);
		
		$this->charger('-', 'sst', $compoChaînes, false, $f = $this->d->chaînes());
		fclose($f);
		
		$this->formats = $compoStyles->formats;
		$this->chaînes = $compoChaînes->chaînes;
	}
	
	public function feuille($numFeuille)
	{
		if(!($f = $this->d->feuille($numFeuille))) return $f;
		$compoFeuille = new CompoFeuille();
		$this->charger('-', 'worksheet', $compoFeuille, false, $f);
		fclose($f);
		
		return $compoFeuille;
	}
}

class XlsxDécompressé extends Décompresseur
{
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

?>
