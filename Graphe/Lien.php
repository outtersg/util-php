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
	/* À FAIRE: contrôle de cohérence Classe départ / Classe arrivée (sur une répétition: départ == arrivée; sur une chaîne: arrivée n == départ n + 1; sur un OU: tous départs identiques, toutes arrivées identiques). Ceci doit être optionnel, car certains veulent pouvoir travailler hors hiérarchie de classes (avec de simples interfaces, voire des interfaces implicites du moment que l'on a tel champ). */
	/* À FAIRE: LienSql: optimiser le rendu. where id in (select id from toto) peut devenir inner join toto on id = toto.id dans certaines conditions:
	 * - la cardinalité est 1 à 1.
	 * - ou l'on se fiche de renvoyer trop de résultats (ex.: un simple peutIl, ou bien utilisé dans le cadre d'un Lien composite, qui fera son unicité en bout de chaîne).
	 */
	public function cible($source = null)
	{
		if(!isset($this->source))
			$this->source = $source;
		if(!isset($this->cible))
			$this->cible = $this->_cible($source ? $source : $this->source);
		return $this->cible;
	}
	
	protected function _cible($source)
	{
		// Si on a un inverse, peut-être peut-il nous dire de qui il part: on va vers ce qui.
		if(isset($this->_inverse) && isset($this->_inverse->source))
			return $this->_inverse->source;
		// Implémentation par défaut: la relation est entre objets de même classe.
		return $source;
	}
	
	protected $_inverse;
	public $args = array();
}

class LienSymbolique extends Lien
{
	public function __construct($noms = null)
	{
		$this->noms = !isset($noms) || is_array($noms) ? $noms : array($noms);
	}
	
	public function __toString()
	{
		return isset($this->noms) ? (count($this->noms) > 1 ? '['.implode('|', $this->noms).']' : $this->noms[0]) : get_class($this).' '.spl_object_hash($this);
	}
	
	public function _cible($source)
	{
		if(!isset($this->noms) || !count($this->noms))
			throw new \Exception('Oups! LienSymbolique sans nom');
		foreach($this->noms as $nom)
			if(($résolu = $source->trouver($nom, false)))
				break;
		// Si on n'a pas trouvé, on se résoud à une création.
		if(!$résolu)
			$résolu = true;
		// Et on retourne sur la Classe, pour lui permettre d'enregistrer le machin trouvé sous tous les noms déclarés.
		foreach($this->noms as $nom)
		{
			$résoluBis = $source->trouver($nom, $résolu);
			if(!$résoluBis)
				throw new \Exception('Oups! Impossible d\'obtenir un LienSimple de '.$source.' pour '.$nom);
			if($résolu === true)
				$résolu = $résoluBis;
			else if($résoluBis !== $résolu)
				throw new Exception('Oups! Deux liens différents ont le nom '.$nom.' aux yeux de '.$source);
		}
		$this->args = array($résolu);
		return $résolu->cible($source);
	}
	
	public function __call($méthode, $args)
	{
		return call_user_func_array(array($this->args[0], $méthode), $args);
	}
}

class LienSimple extends Lien
{
	public function __construct($noms = null)
	{
		if(isset($noms))
		$this->noms = is_array($noms) ? $noms : array($noms);
	}
	
	public function __toString()
	{
		return count($this->noms) > 1 ? '['.implode('|', $this->noms).']' : $this->noms[0];
	}
	
	public function peutIl($sujet, $cod)
	{
		$this->source->trace(get_class($this).': '.$sujet.' peut-il '.$this.' '.$cod.'?');
		$idCod = spl_object_hash($cod);
		foreach($this->noms as $nom)
			if(isset($sujet->$nom))
				if(is_array($sujet->$nom))
				{
					if(isset($sujet->{$nom}[$idCod]))
						return true;
					// À FAIRE: accepter aussi que les membres ne soient pas indexés par leur OID.
				}
				else if($sujet->$nom === $cod)
					return true;
				else
					$this->source->trace("a une relation $nom mais pas pour $cod");
			else
				$this->source->trace("ne connaît pas la relation $nom");
		return false;
	}
	
	public function quiPeutIl($sujets)
	{
		$trace = $this->source->trace(get_class($this).': '.count($sujets).' élément(s): qui peu(ven)t-il(s) '.$this.'?');
		$r = array();
		foreach((is_array($sujets) ? $sujets : array($sujets)) as $sujet)
			foreach($this->noms as $nom)
				if(isset($sujet->$nom))
					if(is_array($sujet->$nom))
						$r += $sujet->$nom;
					else
						$r[spl_object_hash($sujet->$nom)] = $sujet->$nom;
		$trace->clore(count($r).' élément(s)');
		return $r;
	}
	
	public function cible($source = null)
	{
		$r = parent::cible($source);
		
		// C'est le moment de la résolution de noms!
		
		if(!isset($this->noms) && isset($this->_inverse))
		{
			$noms = array();
			foreach($this->_inverse->noms as $nomInverse)
				$noms[] = $r->nommeur->inverse($nomInverse);
			$this->noms = $noms;
		}
		
		// Un LienSimple est implicitement déclaré dans sa Classe.
		
/*
		if(isset($this->noms))
			foreach($this->noms as $nom)
				$source->définir($nom, $this);
*/
		// À FAIRE: inscrire de même le lien retour; et combiner les divers LienSimple pour n'en avoir qu'un.
		
		// Retour.
		
		return $r;
	}
	
	public function inverse()
	{
		if(!isset($this->_inverse))
		{
			$this->_inverse = new LienSimple;
			$this->_inverse->_inverse = $this;
		}
		return $this->_inverse;
	}
}

class LienChaîne extends Lien
{
	public function __toString()
	{
		$r = array();
		foreach($this->args as $lien)
			$r[] = $lien->__toString();
		return '('.implode(' ', $r).')';
	}
	
	public function peutIl($sujet, $cod)
	{
		$trace = $this->source->trace(get_class($this).': '.$sujet.' peut-il '.$this.' '.$cod.'?');
		
		$possibles = array($sujet);
		$n = count($this->args);
		foreach($this->args as $élément)
		{
			if(--$n == 0)
			{
				foreach($possibles as $possible)
					if($élément->peutIl($possible, $cod))
					{
						$trace->clore('oui');
						return true;
					}
			}
			else
			{
				$suivants = array();
				foreach($possibles as $possible)
					$suivants += $élément->quiPeutIl($possible);
				
				if(!count($suivants))
				{
					$trace->clore('non');
					return false;
				}
				$possibles = $suivants;
			}
		}
		$trace->clore('non');
		return false;
	}
	
	public function inverse()
	{
		if(!isset($this->_inverse))
		{
			$this->_inverse = new LienChaîne;
			$this->_inverse->_inverse = $this;
			foreach(array_reverse($this->args) as $lien)
				$this->_inverse->args[] = $lien->inverse();
		}
		return $this->_inverse;
	}
	
	protected function _cible($source)
	{
		$dernier = $this->source = $source;
		foreach($this->args as $num => $élément)
		{
			if($élément instanceof Référence)
			{
				$élément = $this->args[$num] = $dernier->trouver($élément->noms);
			}
			if(isset($élément->source) && $élément->source !== $dernier)
				throw new \Exception('Oups! LienChaîne "'.$this.'": l\'élément '.$num.' "'.$élément.'" part de '.$élément->source.' plutôt que de '.$dernier.', point d\'arrivée du précédent');
			$dernier = $élément->cible($dernier);
		}
		return $dernier;
	}
}

class LienRépét extends Lien
{
	public function __toString()
	{
		$r = '('.$this->args[0].')';
		$min = isset($this->min) ? $this->min : 0;
		$max = isset($this->max) ? $this->max : '∞';
		$minMax = '{'.$min.','.$max.'}';
		switch($minMax)
		{
			case '{0,∞}': $minMax = '*'; break;
			case '{1,∞}': $minMax = '+'; break;
		}
		return $r.$minMax;
	}
	
	public function quiPeutIl($sujets)
	{
		if(!is_array($sujets))
			$sujets = array(spl_object_hash($sujets) => $sujets);
		$n = -1;
		$ceTour = $sujets;
		$valides = array();
		while(true)
		{
			if(++$n >= (isset($this->min) ? $this->min : 0))
				$valides += $ceTour;
			if(isset($this->max) && $n >= $this->max)
				break;
			$ceTour = $this->args[0]->quiPeutIl($ceTour);
			// Si on n'a plus de nouveauté, on quitte.
			if(!count(array_diff_key($ceTour, $valides)))
				break;
		}
		
		return $valides;
	}
	
	public function inverse()
	{
		if(!isset($this->_inverse))
		{
			$this->_inverse = new LienRépét;
			$this->_inverse->_inverse = $this;
			foreach(array_reverse($this->args) as $lien)
				$this->_inverse->args[] = $lien->inverse();
		}
		return $this->_inverse;
	}
	
	protected function _cible($source)
	{
		$cible = $this->args[0]->cible($source);
		if($this->args[0]->source !== $cible)
			throw new \Exception('Oups! LienRépét: l\'élément répété '.$this->args[0].' doit avoir même cible que source');
		return $cible;
	}
}

class LienOu extends Lien
{
	public function __toString()
	{
		$r = array();
		foreach($this->args as $lien)
			$r[] = $lien->__toString();
		return implode(' | ', $r);
	}
	
	public function peutIl($sujet, $cod)
	{
		$this->source->trace(get_class($this).': '.$sujet.' peut-il '.$this.' '.$cod.'?');
		
		foreach($this->args as $chemin)
			if($chemin->peutIl($sujet, $cod))
				return true;
		return false;
	}
	
	public function quiPeutIl($sujets)
	{
		if(!is_array($sujets))
			$sujets = array(spl_object_hash($sujets) => $sujets);
		$r = array();
		foreach($this->args as $chemin)
			$r += $chemin->quiPeutIl($sujets);
		return $r;
	}
	
	public function inverse()
	{
		if(!isset($this->_inverse))
		{
			$this->_inverse = new LienOu;
			$this->_inverse->_inverse = $this;
			foreach($this->args as $lien)
				$this->_inverse->args[] = $lien->inverse();
		}
		return $this->_inverse;
	}
	
	protected function _cible($source)
	{
		$cible = null;
		foreach($this->args as $num => $élément)
			if(!isset($cible))
				$cible = $élément->cible($source);
			else
				if($élément->cible($source) !== $cible)
					throw new \Exception('Oups! LienOu: incohérence sur la cible entre les différents chemins');
		return $cible;
	}
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
		if(!isset($this->$quoi))
			$this->$quoi = array();
		if(is_array($this->$quoi))
		{
			$oid = spl_object_hash($qui);
			$this->{$quoi}[$oid] = $qui;
		}
		else
			$this->$quoi = $qui;
	}
	
	public function nePeutPas($quoi, $qui, $inverser = true)
	{
		if($inverser === true)
		{
			$quoiInverse = $this->_classe->nommeur->inverse($quoi);
			$qui->nePeutPas($quoiInverse, $this, false);
		}
		if(isset($this->$quoi) && is_array($this->$quoi))
			unset($this->{$quoi}[spl_object_hash($qui)]);
		else if(isset($this->$quoi) && $this->$quoi === $qui)
			unset($this->$quoi);
	}
	
	public function peutIl($quoi, $qui)
	{
		$trace = $this->_classe->trace($this.' peut-il '.$quoi.' '.$qui.'?');
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
		return $this->_classe->quiPeutIl($this, $quoi);
	}
	
	public $liens = array();
	protected $_classe;
}

?>
