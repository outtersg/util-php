<?php
/*
 * Copyright (c) 2022 Guillaume Outters
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
 * Affichage en mode texte.
 * Repose sur les séquences d'échappement ANSI pour faire du pseudo-curses.
 */
class AffT
{
	public function __construct($sortie = null)
	{
		$this->_sortie = $sortie;
		$this->màjDims();
		// On suppose le curseur être sur une ligne "à nous".
		$this->nl = 1;
	}
	
	public function màjDims()
	{
		// Nombre de lignes de l'écran, nombre de colonnes.
		// https://stackoverflow.com/questions/2203437
		$this->_nlaff = exec('tput lines');
		$this->_ncaff = exec('tput cols');
	}
	
	/**
	 * Affiche une ligne à un endroit précis.
	 */
	public function affl($numL, $texte, $fin = null)
	{
		/* Déplacement */
		
		if($numL === null)
			$numL = isset($this->y) ? $this->nl : 0;
		isset($this->y) || $this->y = 0;
		
		// Faut-il rajouter des lignes?
		if($numL >= $this->nl)
		{
			// On va à la dernière ligne "existante".
			if($this->y < $this->nl - 1)
				$this->_sortir('['.($this->nl - 1 - $this->y).'B');
			// Et sur sa dernière colonne.
			$this->_sortir('['.$this->_ncaff.'G');
			$this->_sortir(str_repeat("\n", $numL + 1 - $this->nl));
			$this->y = $numL;
			$this->nl = $numL + 1;
		}
		
		// Inutile de tenter d'écrire sur une ligne hors écran, le terminal refusera d'y aller et ça nous cassera tous nos repères.
		if($numL < $this->nl - $this->_nlaff)
			return;
			
		$this->_sortir("\r");
		if(($dépl = $numL - $this->y))
			$this->_sortir('['.($dépl < 0 ? -$dépl.'A' : $dépl.'B'));
		
		/* Protection */
		
		if(($pos = strpos($texte, "\n")) !== false)
		{
			$dépassement = substr($texte, $pos + 1);
			$texte = substr($texte, 0, $pos);
		}
		
		/* Découpe */
		
		$tailleDispo = $this->_ncaff;
		if($fin) $tailleDispo -= $this->tailleAff($fin);
		$aff = $this->chaîneBornée($texte, $tailleDispo);
		if($fin) $aff .= $fin;
		
		$this->_sortir($aff);
		
		$this->y = $numL;
		
		/* Casseroles */
		
		if(isset($dépassement))
			$this->affl($numL + 1, $dépassement);
	}
	
	public function chaîneBornée($chaîne, $tailleMax)
	{
		list($t, $d, $p) = $this->tailleAff($chaîne, true);
		if($t <= $tailleMax)
			return $chaîne;
		
		$rempl = '[…]';
		$tailleMax -= mb_strlen($rempl);
		
		// Partant de la fin, on retire les bouts jusqu'à tenir sous $tailleMax (qui a encore réduit pour caser des points de suspension).
		$i = count($d);
		if($i % 2) ++$i;
		while(($i -= 2) >= 0)
		{
			if(($t -= $p[$i]) <= $tailleMax)
			{
				$d[$i] = $t == $tailleMax ? '' : mb_substr($d[$i], 0, $tailleMax - $t);
				$d[$i] .= $rempl;
				break;
			}
			unset($d[$i]);
		}
		
		return implode('', $d);
	}
	
	public function tailleAff($chaîne, $détail = false)
	{
		$t = 0;
		if($détail) $positions = array();
		for($d = $this->découpe($chaîne), $n = count($d), $i = -2; ($i += 2) < $n;)
		{
			$t += ($ti = mb_strlen($d[$i]));
			if($détail)
			{
				$positions[] = $ti;
				$positions[] = 0; // La prochaine chaîne est nécessairement chaîne de contrôle, de taille 0.
			}
		}
		return $détail ? array($t, $d, $positions) : $t;
	}
	
	/**
	 * Découpe une chaîne en alternance de segments de texte et de caractères de contrôle.
	 *
	 * @return array [ 'texte', 'séquence de contrôle', 'texte', etc. ]
	 *               N.B.: commence toujours par un texte (vide si besoin). Les indices pairs désignent donc les textes.
	 */
	public function découpe($chaîne)
	{
		$r = array();
		preg_match_all('#\[[0-9;]*[a-zA-Z]#', $chaîne, $contrôles, PREG_OFFSET_CAPTURE);
		$pos = 0;
		foreach($contrôles[0] as $contrôle)
		{
			$r[] = substr($chaîne, $pos, $contrôle[1] - $pos);
			$r[] = $contrôle[0];
			$pos = $contrôle[1] + strlen($contrôle[0]);
		}
		if(strlen($fin = substr($chaîne, $pos)))
			$r[] = $fin;
		return $r;
	}
	
	protected function _sortir($chaîne)
	{
		if(isset($this->_sortie))
			fprintf($this->_sortie, '%s', $chaîne);
		else
		echo $chaîne;
	}
}

?>
