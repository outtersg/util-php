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

require_once dirname(__FILE__).'/Compilo.php';
require_once dirname(__FILE__).'/Nommeur.php';
require_once dirname(__FILE__).'/Traceur.php';
require_once dirname(__FILE__).'/Lien.php';

/**
 * Classe de Graphe.
 * La classe regroupe un ensemble de définitions.
 * Deux schémas communs:
 * - soit une Classe par classe de Nœud (en ce cas chaque type de Nœud a des relations propres, sans ambiguïté).
 * - soit une Classe globale, en fait le graphe, permettant à n'importe quel nœud de suivre n'importe quelle relation.
 */
class Classe
{
	public function __construct($parent = null, $nommeur = null)
	{
		$this->_parent = $parent;
		$this->_compilo = new Compilo;
		$this->nommeur = isset($nommeur) ? $nommeur : new Nommeur;
		$this->traceur = new Traceur;
	}
	
	public function __toString()
	{
		$r = get_class($this);
		if(isset($this->nom))
			$r .= ' '.$this->nom;
		return $r;
	}
	
	public function trace($quoi, $niveau = null)
	{
		return isset($this->_parent) ? $this->_parent->trace($quoi, $niveau) : $this->traceur->trace($quoi, $niveau);
	}
	
	public function définir($nomLien, $définition = null, $nomLienInverse = null, $classeCible = null)
	{
		if(is_object($définition))
			$lien = $définition;
		else
		$lien = isset($définition) ? $this->_compilo->compiler($this, $définition) : $this->_compilo->compilerSimple($nomLien);
		if(isset($classeCible))
			$lien->cible = $classeCible;
		
		$this->_liens[$nomLien] = $lien;
		
		// Résolution de liens.
		
		$classeCible = $lien->cible($this);
		
		// Si on est déjà dans la création de l'inverse, nul besoin de créer l'inverse de l'inverse.
		
		if(is_object($définition))
			return;
		
		// Création de l'inverse.
		
		$lienInverse = $lien->inverse();
		$classeCible->définir($this->nommeur->inverse($nomLien), $lienInverse, null, $this);
		if(isset($nomLienInverse))
			$classeCible->définir($nomLienInverse, $lienInverse, null, $this);
		
		return $this->_liens[$nomLien];
	}
	
	public function relation($nom, $cible = null)
	{
		return $this->trouver($nom, isset($cible) ? $cible : $this);
	}
	
	public function trouver($nomLien, $créerSiBesoin = true)
	{
		if(!isset($this->_liens[$nomLien]) && isset($this->_parent) && ($lien = $this->_parent->trouver($nomLien, false)))
			return $lien;
		if(!isset($this->_liens[$nomLien]))
			return $créerSiBesoin ? $this->définir($nomLien, null, null, is_object($créerSiBesoin) ? $créerSiBesoin : null) : false;
		return $this->_liens[$nomLien];
	}
	
	public function peutIl($qui, $quoi, $surQui)
	{
		$this->trace(get_class($this).': '.$qui.' peut-il '.$quoi.' '.$surQui.'?');
		if(isset($this->_liens[$quoi]))
			return $this->_liens[$quoi]->peutIl($qui, $surQui);
		if($this->_parent)
			return $this->_parent->peutIl($qui, $quoi, $surQui);
		throw new \Exception("règle $quoi non définie");
	}
	
	public function quiPeutIl($qui, $quoi)
	{
		if(isset($this->_liens[$quoi]))
			return $this->_liens[$quoi]->quiPeutIl($qui);
		if($this->_parent)
			return $this->_parent->quiPeutIl($qui, $quoi);
		throw new \Exception("règle $quoi non définie");
	}
}

?>
