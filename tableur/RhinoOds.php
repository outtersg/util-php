<?php
/*
 * Copyright (c) 2020 Guillaume Outters
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

/**
 * Charge les fichiers .ods
 */
class RhinoOds extends Chargeur
{
	public function lire($chemin)
	{
		$this->chemin = $chemin;
		
		$this->d = new OdsDécompressé($chemin);
		
		$compoStyles = new OdsCompoStyles();
		$this->charger($this->chemin.':styles.xml', null, $compoStyles, false, $f = $this->d->styles());
		fclose($f);
		
		$this->formats = $compoStyles->formats;
		$this->chaînes = null;
	}
	
	public function feuille($numFeuille)
	{
		if(!isset($this->feuilles))
		{
			if(!($f = $this->d->contenu())) return $f;
			$compoContenu = new OdsCompoContenu();
			$this->charger($this->chemin.':content.xml', null, $compoContenu, false, $f);
			fclose($f);
			
			$this->feuilles = $compoContenu->feuilles;
		}
		
		return isset($this->feuilles[$numFeuille - 1]) ? $this->feuilles[$numFeuille - 1] : false;
	}
}

class OdsDécompressé extends Décompresseur
{
	public function styles()
	{
		return $this->_xml('styles.xml');
	}
	
	public function contenu()
	{
		return $this->_xml("content.xml");
	}
}

/*- Analyse du XML -----------------------------------------------------------*/

class OdsCompoStyles extends CompoSimple
{
	public function __construct()
	{
		parent::__construct(array());
		$this->formats = array();
	}
	
	public function &entrerDans(&$depuis, $nom, $attributs)
	{
		$r = null;
		return $r;
	}
}

class OdsCompoChaînes extends CompoSimple
{
	public function __construct()
	{
		parent::__construct(array());
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

class OdsCompoContenu extends CompoSimple
{
	public function __construct()
	{
		parent::__construct(array());
		$this->feuilles = array();
	}
	
	public function &entrerDans(&$depuis, $nom, $attributs)
	{
		$r = null;
		switch($nom)
		{
			case 'office:document-content':
				if($depuis !== null) return $r;
				$this->feuilles = array();
				return $this->feuilles;
			case 'table:table':
				if($depuis != $this->feuilles) return $r;
				$this->feuilles[$numFeuille = count($this->feuilles)] = new OdsCompoFeuille();
				return $this->feuilles[$numFeuille];
		}
		return $r;
	}
}

class OdsCompoFeuille extends CompoSimple
{
	public function __construct()
	{
		parent::__construct(array());
		$this->colonnes = array();
		$this->lignes = array();
	}
	
	public function &entrerDans(&$depuis, $nom, $attributs)
	{
		switch($nom)
		{
			case 'table:table-column':
				$colonne = array();
				$nColonnes = 1;
				if(isset($attributs['table:number-columns-repeated']))
					$nColonnes = 0 + $attributs['table:number-columns-repeated'];
				if(isset($attributs['table:default-cell-style-name']))
					$colonne['style'] = $attributs['table:default-cell-style-name'];
				for($i = count($this->colonnes); --$nColonnes >= 0; ++$i)
					$this->colonnes[$i] = $colonne;
				break;
			case 'table:table-row':
				$this->lignes[$numLigne = count($this->lignes)] = array('d' => array(), 't' => array(), 's' => array()); // Nouvelle ligne, avec ses colonnes de données, et les éventuels types et styles.
				return $this->lignes[$numLigne];
			case 'table:table-cell':
				$this->_répét = 1;
				if(is_array($depuis))
				{
					$numCol = count($depuis['d']);
					if(isset($attributs['table:number-columns-repeated']))
					{
						$this->_répét = 0 + $attributs['table:number-columns-repeated'];
						$this->_ligne = &$depuis;
					}
					if(isset($attributs['office:value-type']))
						$depuis['t'][$numCol] = $attributs['office:value-type'];
					if(isset($attributs['table:style-name']))
						$depuis['s'][$numCol] = $attributs['table:style-name'];
					$depuis['d'][$numCol] = isset($attributs['office:value']) ? $attributs['office:value'] : '';
					return $depuis['d'][$numCol];
				}
				break;
			case 'text:p':
				// En théorie le <p> vient confirmer une valeur numérique si présente.
				if(is_string($depuis))
				{
					if($depuis !== '')
					{
						$this->_réfP = $depuis;
						$depuis = '';
					}
					else
						unset($this->_réfP);
					return $depuis; // Le contenu du <text:p> vient remplir la case qu'on avait précréée en mémoire.
				}
				break;
		}
		$r = null;
		return $r;
	}
	
	public function sortirDe(&$de, $balise)
	{
		switch($balise)
		{
			case 'table:table-cell':
				if($this->_répét > 1)
					for($numCol = count($this->_ligne['d']); --$this->_répét > 0; ++$numCol)
					{
						$this->_ligne['d'][$numCol] = $this->_ligne['d'][$numCol - 1];
						if(isset($this->_ligne['t'][$numCol - 1]))
							$this->_ligne['t'][$numCol] = $this->_ligne['t'][$numCol - 1];
						if(isset($this->_ligne['s'][$numCol - 1]))
							$this->_ligne['s'][$numCol] = $this->_ligne['s'][$numCol - 1];
					}
				break;
			case 'text:p':
				if(isset($this->_réfP) && $this->_réfP != $de)
					if(strtr($de, ',', '.') === $this->_réfP)
						$de = $this->_réfP;
					else
						throw new Exception('Incohérence: valeur à '.$this->_réfP.', contenu affiché à '.$de);
				break;
		}
	}
}

?>
