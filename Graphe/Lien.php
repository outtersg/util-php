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

namespace eu_outters_guillaume\Util\Graphe;

class Lien
{
	public function __construct(Classe $graphe)
	{
		$this->_graphe = $graphe;
	}
	
	public function inverse()
	{
		throw new \Exception(get_class($this)." ne sait pas se retourner comme une vieille chaussette");
	}
	
	protected $_inverse;
}

class LienSimple extends Lien
{
	public function __construct(Classe $graphe, $noms)
	{
		parent::__construct($graphe);
		$this->noms = $noms;
	}
	
	public function __toString()
	{
		return count($this->noms) > 1 ? '['.implode('|', $this->noms).']' : $this->noms[0];
	}
	
	public function peutIl($sujet, $cod)
	{
		$this->_graphe->trace(get_class($this).': '.$sujet.' peut-il '.$this.' '.$cod.'?');
		foreach($this->noms as $nom)
			if(isset($sujet->liens[$nom]))
				if(isset($sujet->liens[$nom][spl_object_hash($cod)]))
					return true;
				else
					$this->_graphe->trace("a une relation $nom mais pas pour $cod");
			else
				$this->_graphe->trace("ne connaît pas la relation $nom");
		return false;
	}
	
	public function quiPeutIl($sujet)
	{
		$r = array();
		foreach($this->noms as $nom)
			if(isset($sujet->liens[$nom]))
				$r += $sujet->liens[$nom];
		return $r;
	}
	
	public function inverse()
	{
		if(!isset($this->_inverse))
		{
			$nomsInverses = array();
			foreach($this->noms as $nom)
				$nomsInverses[] = $this->_graphe->nommeur->inverse($nom);
			$this->_inverse = new LienSimple($this->_graphe, $nomsInverses);
		}
		return $this->_inverse;
	}
}

class LienChaîne extends Lien
{
	public function __toString()
	{
		$r = array();
		foreach($this->chaîne as $lien)
			$r[] = $lien->__toString();
		return '('.implode(' ', $r).')';
	}
	
	public function peutIl($sujet, $cod)
	{
		$this->_graphe->trace(get_class($this).': '.$sujet.' peut-il '.$this.' '.$cod.'?');
		
		$possibles = array($sujet);
		$n = count($this->chaîne);
		foreach($this->chaîne as $élément)
		{
			if(--$n == 0)
			{
				foreach($possibles as $possible)
					if($élément->peutIl($possible, $cod))
						return true;
			}
			else
			{
				$suivants = array();
				foreach($possibles as $possible)
					$suivants += $élément->quiPeutIl($possible);
				
				if(!count($suivants))
					return false;
				$possibles = $suivants;
			}
		}
		return false;
	}
	
	public function inverse()
	{
		if(!isset($this->_inverse))
		{
			$this->_inverse = new LienChaîne($this->_graphe);
			foreach(array_reverse($this->chaîne) as $lien)
				$this->_inverse->chaîne[] = $lien->inverse();
		}
		return $this->_inverse;
	}
	
	public $chaîne = array();
}

class LienOu extends Lien
{
	public function __toString()
	{
		$r = array();
		foreach($this->chemins as $lien)
			$r[] = $lien->__toString();
		return implode(' | ', $r);
	}
	
	public function peutIl($sujet, $cod)
	{
		$this->_graphe->trace(get_class($this).': '.$sujet.' peut-il '.$this.' '.$cod.'?');
		
		foreach($this->chemins as $chemin)
			if($chemin->peutIl($sujet, $cod))
				return true;
		return false;
	}
	
	public function inverse()
	{
		if(!isset($this->_inverse))
		{
			$this->_inverse = new LienOu($this->_graphe);
			foreach($this->chemins as $lien)
				$this->_inverse->chemins[] = $lien->inverse();
		}
		return $this->_inverse;
	}
	
	public $chemins = array();
}

/**
 * Nœud du graphe.
 * Peut être utilisé soit en sous-classe directe, soit comme membre des entités réelles (qui sera leur point d'attache à la Classe dédiée).
 */
class Nœud
{
	public function __construct(Classe $classe)
	{
		$this->_classe = $classe;
	}
	
	public function est($quoi, $pourQui)
	{
		return $this->peut($quoi, $pourQui); // est(lecteur, article) == peut(lire, article).
	}
	
	public function nEstPas($quoi, $pourQui)
	{
		return $this->nePeutPas($quoi, $pourQui);
	}
	
	public function peut($quoi, $qui, $inverser = true)
	{
		if($inverser === true)
		{
			$quoiInverse = $this->_classe->nommeur->inverse($quoi);
			$qui->peut($quoiInverse, $this, false);
		}
		$this->liens[$quoi][spl_object_hash($qui)] = $qui;
	}
	
	public function nePeutPas($quoi, $qui, $inverser = true)
	{
		if($inverser === true)
		{
			$quoiInverse = $this->_classe->nommeur->inverse($quoi);
			$qui->nePeutPas($quoiInverse, $this, false);
		}
		unset($this->liens[$quoi][spl_object_hash($qui)]);
	}
	
	public function peutIl($quoi, $qui)
	{
		$trace = $this->_classe->trace($this.' peut-il '.$quoi.' '.$qui.'?');
		// Déjà si on l'a en relation directe on ne se pose presque pas la question.
		if(isset($this->liens[$quoi]))
			if(isset($this->liens[$quoi][spl_object_hash($qui)]))
			{
				$trace->clore('oui, en direct');
				return true;
			}
		// Bon, donc il va falloir rechercher par le jeu des relations.
		$r = $this->_classe->peutIl($this, $quoi, $qui);
		$trace->clore();
		return $r;
	}
	
	public function quiEstIl($quoi)
	{
		return $this->quiPeutIl($quoi);
	}
	
	public function quiPeutIl($quoi)
	{
		if(isset($this->liens[$quoi]))
			return $this->liens[$quoi];
		return $this->_classe->quiPeutIl($this, $quoi);
	}
	
	public $liens = array();
}

?>
