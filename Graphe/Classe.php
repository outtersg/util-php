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
	
	public function trace($quoi, $niveau = null)
	{
		return isset($this->_parent) ? $this->_parent->trace($quoi, $niveau) : $this->traceur->trace($quoi, $niveau);
	}
	
	public function définir($nomLien, $définition = null, $nomLienInverse = null)
	{
		$lien = $this->_compilo->compiler($this, isset($définition) ? $définition : array($nomLien));
		
		$this->_liens[$nomLien] = $lien;
		$lienInverse = $lien->inverse();
		$this->_liens[$this->nommeur->inverse($nomLien)] = $lienInverse;
		if(isset($nomLienInverse))
			$this->_liens[$nomLienInverse] = $lienInverse;
		
		return $this->_liens[$nomLien];
	}
	
	public function trouver($nomLien, $créerSiBesoin = true)
	{
		if(!isset($this->_liens[$nomLien]) && isset($this->_parent) && ($lien = $this->_parent->trouver($nomLien, false)))
			return $lien;
		if(!isset($this->_liens[$nomLien]))
			return $créerSiBesoin ? $this->définir($nomLien) : false;
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
