<?php
/*
 * Copyright (c) 2005 Guillaume Outters
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

class LecteurHtml
{
	public $pile;
	public $tailleBloc = 0x1000;
	protected $pitinenfant; // Celui qui nous écoute raconter notre fichier à notre manière.
	
	function LecteurHtml($pourUntel)
	{
		$this->pitinenfant = $pourUntel;
	}
	
	function lire($fichier)
	{
		$this->pile = array();
		$mem = null;
		$memComm = null;
		$etat = 0; // 0: entre balises, 1: dans une balise; avec le masque 2 en plus: en commentaire.
		
		do
		{
			if(($donnees = fread($fichier, $this->tailleBloc)) == null)
			{
				$fin = true;
				$donnees = '';
			}
			else
				$fin = feof($fichier);
			$taille = strlen($donnees);
			$debut = 0;
			
			while($debut < $taille)
			{
				if($etat & 2)
				{
					if(($pos = strpos($donnees, '>', $debut)) === false)
					{
						$memComm = substr($donnees, $debut < $taille - 2 ? $taille - 2 : $debut);
						break;
					}
					if(($pos >= 2 && $donnees{$pos - 1} == '-' && $donnees{$pos - 2} == '-') || strstr('-->', ($memComm.substr($donnees, 0, 2)) !== false))
						$etat = 0;
					$debut = $pos + 1;
				}
				else if($etat)
				{
					/* Détection des commentaires. */
					if(($mem === null && substr($donnees, $debut, 4) == '<!--') || ($mem !== null && strstr('<!--', $mem.substr($donnees, $debut, 4)) !== false))
					{
						$etat |= 2;
						continue;
					}
					if(($pos = strpos($donnees, '>', $debut)) === false)
					{
						$cont = substr($donnees, $debut);
						if($mem === null) $mem = $cont; else $mem .= $cont;
						break;
					}
					$cont = substr($donnees, $debut, (++$pos) - $debut);
					$debut = $pos;
					$etat = 0;
					if($mem !== null) { $cont = $mem.$cont; $mem = null; }
					if($cont{1} == '/') // Balise fermante
					{
						$nom = $this->nom($cont);
						for($n = $i = count($this->pile); --$i >= 0;)
							if($this->nom($this->pile[$i]) == $nom)
								break;
						if($i >= 0) // Parce que sinon, une balise fermante sans ouvrante, c'est trop louche pour qu'on laisse passer.
						{
							while($n > $i)
							{
								$this->pitinenfant->sortir($this, $this->nom($this->pile[--$n]));
								array_pop($this->pile);
							}
						}
					}
					else if($cont{strlen($cont)-2} == '/') // Balise auto-fermante
					{
						$nom = $this->nom($cont);
						$this->pile[] = $cont;
						$this->pitinenfant->entrer($this, $nom);
						$this->pitinenfant->sortir($this, $nom);
						array_pop($this->pile);
					}
					else // Balise ouvrante
					{
						$this->pile[] = $cont;
						$this->pitinenfant->entrer($this, $this->nom($cont));
					}
				}
				else
				{
					if(($pos = strpos($donnees, '<', $debut)) === false)
					{
						$cont = substr($donnees, $debut);
						if($mem === null) $mem = $cont; else $mem .= $cont;
						break;
					}
					$cont = substr($donnees, $debut, $pos - $debut);
					$debut = $pos;
					$etat = 1;
					if($mem !== null) { $cont = $mem.$cont; $mem = null; }
					if(preg_match("/[^	 \n\r]/", $cont))
						$this->pitinenfant->texte($this, $cont); 
				}
			}
		} while(!$fin);
		
		/* On ferme tout */
		
		if($mem !== null)
			$this->pitinenfant->texte($this, $mem);
		for($n = count($this->pile); --$n >= 0;)
		{
			$this->pitinenfant->sortir($this, $this->nom($this->pile[$n]));
			array_pop($this->pile);
		}
	}
	
	function nom($balise)
	{
		$debut = $balise{1} == '/' ? 2 : 1;
		if(($fin = strpos($balise, ' ')) === false)
			if($balise{$fin = strlen($balise) - 1} != '>' || $balise{--$fin} != '/')
				++$fin;
		return substr($balise, $debut, $fin - $debut);
	}
}

/*

class Test
{
	function entrer($lecteur, $balise) { echo('-> '.$balise."\n"); }
	function texte($lecteur, $texte) { echo('texte'."\n"); } // Appelée sur tout bloc de texte. ATTENTION: un bloc contenant un commentaire arrive en deux parties.
	function sortir($lecteur, $balise) { echo('<- '.$balise."\n"); }
}

$lecteur = new LecteurHtml(new Test());
$lecteur->lire(fopen('php://stdin', 'r'));

*/

?>
