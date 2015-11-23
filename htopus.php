<?php
/*
 * Copyright (c) 2015 Guillaume Outters
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

/**
 * Lecteur d'une représentation simple d'une structure (un peu façon YAML).
 * HTOpus = Hyper Text Objects Plutôt Ultra Simplifiés (pour remplacer le HTML).
 */
class HTOpus
{
	protected $abonné;
	
	public function abonner($abonné)
	{
		$this->abonné = $abonné;
	}
	
	public function lire($texte)
	{
		if(!isset($this->abonné))
			$this->abonné = new HTOpus_Goulp;
		$élémentActuel = null;
		$indentationActuel = '';
		$indentationActuelEstimée = true;
		$actuelPapa = true; // Le nœud sur lequel on travaille accepte-t-il des fils?
		
		$pile = array();
		
		$errs = array();
		$lignes = explode("\n", $texte);
		foreach($lignes as $num => $ligne)
		{
			// Analyse textuelle de la ligne.
			
			if(!preg_match('/^([ 	]*)([^ :]*)([ 	]+[^ :]+=(?:[^ :]*|"[^"]*"))*(:)? *(#.*)?$/', $ligne, $r))
			{
				$errs[] = 'ligne '.$ligne.': ininterprétable ('.$ligne.')';
				continue;
			}
			$r += array('', '', '', '', '');
			if(!$r[2])
				continue;
			$indentation = $r[1];
			if($indentationActuelEstimée)
			{
				if(strlen($indentation) >= strlen($indentationActuel))
					$indentationActuel = $indentation;
				$indentationActuelEstimée = false;
			}
			if(strncmp($indentationActuel, $indentation, min(strlen($indentationActuel), strlen($indentation))))
			{
				$errs[] = 'ligne '.$ligne.': incohérence dans l\'utilisation des espaces (au même niveau que la précédente, mais avec des caractères différents)';
				continue;
			}
			/* À FAIRE: savoir aussi détecter les incohérences d'espacement. Ceci peut se faire au troisième niveau (au premier, on détermine le 0, car pour des raisons d'indentation le niveau 0 peut très bien commencer ailleurs qu'au caractère 0; au second, la différence d'avec le premier nous donne l'indentation standard. Au troisième, on peut commencer à gueuler s'il y a incohérence). */
			$élément = $r[2];
			preg_match_all('/[    ]+([^ ]+)=(?:([^ :]*)|"([^"]*)")/', $r[3], $résAttrs);
			$attrs = array();
			foreach($résAttrs[0] as $numRésAttrs => $rien)
				$attrs[$résAttrs[1][$numRésAttrs]] = $résAttrs[2][$numRésAttrs] ? $résAttrs[2][$numRésAttrs] : $résAttrs[3][$numRésAttrs];
			$structure = $r[4] ? true : false;
			
			// Prise en compte de l'indentation.
			
			while(strlen($indentation) < strlen($indentationActuel))
			{
				if(!count($pile))
				{
					$errs[] = 'ligne '.$num.': impossible de revenir plus avant la racine';
					continue 2;
				}
				
				$this->abonné->sortirDe('#');
				
				$dernierBloc = array_shift($pile);
				$élémentActuel = $dernierBloc[0];
				$indentationActuel = $dernierBloc[1];
				$actuelPapa = $dernierBloc[2];
			}
			if($indentation > $indentationActuel)
			{
				$errs[] = 'ligne '.$num.': impossible d\'affecter un sous-élément '.$élément.' à un scalaire ('.$élémentActuel.')';
				continue;
			}
			if($structure)
			{
				array_unshift($pile, array($élémentActuel, $indentationActuel, $actuelPapa));
				$this->abonné->entrerDans($élément,$attrs);
				$indentationActuelEstimée = true; // On sait qu'on doit entrer dans un fils, mais tant qu'on n'a pas ce fils, on ne connaît pas le niveau exact d'indentation dont il va se servir pour se démarquer de nous (niveau que ses frères devront adopter).
				$indentationActuel .= ' '; // D'où un nouveau niveau estimé à un espace de plus que le niveau actuel.
				$actuelPapa = true;
			}
			else
			{
				$this->abonné->ajouter($élément, $attrs);
				$actuelPapa = false;
			}
		}
		
		$this->abonné->errs = $errs;
		
		return $this->abonné;
	}
}

class HTOpus_Goulp
{
	public function entrerDans($élément, $attrs)
	{
		
	}
	
	public function ajouter($élément, $attrs)
	{
	
	}
	
	public function sortirDe($élément)
	{
	
	}
}

/**
 * L'équivalent HTOpus du DOM. Sauf que nous c'est un Document Object pas modèle du tout, du coup un Document Object… comment le qualifier? Bon, ben en tout cas c'est un Document Object Document Object!
 */
class Dodo
{
	public $document = array();
	protected $_pile = array();
	protected $_actuel;
	
	public function __construct()
	{
		$this->_actuel = & $this->document;
	}
	
	public function entrerDans($élément, $attrs)
	{
		$this->_pile[] = & $this->_actuel;
		$objet = array('fils' => array());
		if($attrs)
			$objet['attrs'] = $attrs;
		$this->_actuel['fils'][$élément] = $objet;
		$this->_actuel = & $this->_actuel['fils'][$élément];
	}
	
	public function ajouter($élément, $attrs)
	{
		$objet = array();
		if($attrs)
			$objet['attrs'] = $attrs;
		$this->_actuel['fils'][$élément] = $objet;
	}
	
	public function sortirDe($élément)
	{
		$this->_actuel = & $this->_pile[count($this->_pile) - 1];
		array_pop($this->_pile);
	}
}

?>
