<?php
/*
 * Copyright (c) 2003 Guillaume Outters
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

class SortieXml
{
	public $sortie;
	public $alinea;
	/* Lorsque $this->niveauAplati >= 0, on n'écrit pas d'alinéa. L'alinéa réel
	 * est alors une chaîne vide (au lieu d'un caractère retour suivi de
	 * tabulations). Mais on conserve l'alinéa théorique pour pouvoir retomber
	 * sur nos pattes au retour à la normale. */
	public $alineaTheorique;
	public $alineaVide = '';
	public $courants;
	public $dedans; // Dans la balise entrante
	public $contenu;
	/* Niveau depuis lequel on aplatit. Il est possible de demander à partir
	 * d'un niveau l'aplatissement total (pas de passage à la ligne lors de
	 * l'entrée dans un sous-élément XML), afin par exemple de préserver des
	 * caractères blancs.
	 * La variable vaut -1 pour un traitement normal, 0 si l'on traite un
	 * élément aplati, 1 si l'on est dans un sous-élément de celui pour lequel a
	 * été demandé l'aplatissement, etc. */
	public $niveauAplati;
	
	/*- Écriture -------------------------------------------------------------*/
	
	function commencerDans(&$chaine)
	{
		unset($this->sortie);
		$this->sortie = &$chaine;
		$this->commencer(null);
	}
	function commencer($sortie)
	{
		if($sortie) $this->sortie = $sortie;
		$this->niveauAplati = -1;
		$this->alineaTheorique = "\n";
		$this->alinea = &$this->alineaTheorique;
		$this->ecrire('<?xml version="1.0"?>');
	}
	
	function element($element, $contenu)
	{
		$this->entrer($element);
		$this->contenu($contenu);
		$this->sortir();
	}
	
	function entrer($element)
	{
		if($this->dedans) // Il nous reste à fermer la balise ouvrante de l'élément père.
		{
			$this->ecrire('>');
			$this->alineaTheorique .= '	'; // Une tabulation de plus.
			if($this->contenu !== null)
				$this->ecrire($this->alinea.htmlspecialchars($this->contenu, ENT_NOQUOTES));
			$this->dedans = false;
		}
		
		$this->ecrire($this->alinea.'<'.$element);
		$this->contenu = null;
		$this->dedans = true;
		$this->courants[] = $element;
		if($this->niveauAplati >= 0) ++$this->niveauAplati;
	}
	
	function aplatir()
	{
		if($this->niveauAplati < 0)
		{
			$this->niveauAplati = 0;
			$this->alinea = &$this->alineaVide;
		}
	}
	
	function attribut($attribut, $valeur)
	{
		if($this->dedans)
			$this->ecrire(' '.$attribut.'="'.$valeur.'"');
	}
	
	function contenu($contenu)
	{
		if($this->dedans)
		{
			if($this->contenu !== null) $this->contenu .= $contenu;
			else $this->contenu = $contenu;
		}
		else
			$this->ecrire(htmlspecialchars($contenu, ENT_NOQUOTES));
	}
	
	function sortir()
	{
		if($this->dedans === false)
		{
			$this->alineaTheorique = substr($this->alineaTheorique, 0, -1);
			$this->ecrire($this->alinea.'</'.$this->courants[count($this->courants) - 1].'>');
		}
		else if($this->contenu !== null)
			$this->ecrire('>'.htmlspecialchars($this->contenu, ENT_NOQUOTES).'</'.$this->courants[count($this->courants) - 1].'>');
		else
			$this->ecrire('/>');
		$this->dedans = false;
		if($this->niveauAplati >= 0)
			if(--$this->niveauAplati < 0)
				$this->alinea = &$this->alineaTheorique;
		array_pop($this->courants);
	}
	
	function terminer()
	{
		unset($this->sortie);
	}
	
	function ecrire($ceci)
	{
		if(is_string($this->sortie))
			$this->sortie .= $ceci;
		else if($this->sortie)
			fwrite($this->sortie, $ceci);
		else
			echo $ceci;
	}
}

?>