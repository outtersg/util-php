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

class Compilo
{
	public static $exprLien = '[^ |]+';
	
	public function compiler($conteneur, $définition = null)
	{
		if(!isset($définition))
		{
			$définition = $conteneur;
			$conteneur = null;
		}
		
		$bouts = $this->_découper($définition);
		$this->_empaqueter($bouts);
		return $this->_assembler($conteneur, $bouts);
	}
	
	public function compilerSimple($nom)
	{
		return new LienSimple($nom);
	}
	
	protected function _assembler(Classe $conteneur, $bouts)
	{
		$prios = array
		(
			'|' => 7,
			'*' => 3,
		);
		
		/* Où va-t-on découper? On recherche le point de découpe le plus prioritaire. */
		
		$prioMax = 0;
		$posPrioMax = -1;
		foreach($bouts as $numBout => $bout)
			if($bout[0] == self::SYNTAXE && isset($prios[$bout[1]]) && $prios[$bout[1]] > $prioMax)
			{
				$prioMax = $prios[$bout[1]];
				$posPrioMax = $numBout;
			}
		
		/* Bon, rien de spécial, juste une chaîne. */
		
		if($prioMax <= 0)
		{
			$compil = new LienChaîne;
			foreach($bouts as $bout)
				switch($bout[0])
				{
					case self::ESPACE:
						break;
					case self::AUTRE:
						$compil->args[] = new LienSymbolique($bout[1]); // A priori ça terminera en LienSimple… sauf si un lien plus élaboré est au préalable déclaré dans la classe avec ce nom. D'où en tout cas le besoin de ne pas coder en dur trop vite un LienSimple: la décision se fera au moment de la résolution.
						break;
					case self::BLOC:
						$compil->args[] = $this->_assembler($conteneur, $bout[1]);
						break;
					default:
						throw new \Exception('Oups! Il ne devrait pas y avoir ici de '.$bout[0]);
				}
			if(count($compil->args) == 1) // Et on laisse le cas 0 libre de survenir (ex.: "(toto|titi|)").
				$compil = $compil->args[0];
			return $compil;
		}
		
		/* Hop, les cas particuliers maintenant. */
		
		switch($bouts[$posPrioMax][1])
		{
			case '|':
				$compil = new LienOu;
				$compil->args[0] = $this->_assembler($conteneur, array_slice($bouts, 0, $posPrioMax));
				$compil->args[1] = $this->_assembler($conteneur, array_slice($bouts, $posPrioMax + 1));
				return $compil;
				/* À FAIRE: regrouper certains opérateurs, type |: a|b|c, c'est tout autant |(a, b, c) que |(a, |(b, c)). Attention, ça ne marche pas si un autre opérateur de même priorité intervient, par exemple a|b&c|d. */
				break;
			case '*':
				// En début de bloc, l'* porte sur tout le bloc. Sinon sur son prédécesseur.
				if($posPrioMax == 0)
				{
					$compil = new LienRépét;
					$compil->args = array($this->_assembler($conteneur, array_slice($bouts, 1)));
					$compil->min = 0;
					$compil->max = null;
					return $compil;
				}
				else // Le plus simple dans ce cas est de réordonner la source et relancer la compil.
				{
					$bout = array(self::BLOC, array($bouts[$posPrioMax - 1]), null);
					array_splice($bouts, $posPrioMax, 2, array($bout));
					return $this->_assembler($conteneur, $bouts);
				}
				break;
			default:
				throw new \Exception('Oups! Je ne gère pas l\'opérateur '.$bouts[$posPrioMax][1]);
		}
		
		throw new \Exception('Je ne sais pas assembler: '.print_r($bouts, true));
	}
	
	protected function _découper($chaîne)
	{
		$r = array();
		
		/* À FAIRE: inclure le caractère de négation proposé par le Nommeur. Pour l'heure, la négation est gérée comme faisant partie du nom, donc interprétée en toute dernière instance. Cependant on pourrait vouloir faire des -(père père), qui devraient donner un (fils fils): le - n'est pas au niveau du Lien unitaire. */
		preg_match_all('/([()*+|])|([ 	]+)|([^()*+| 	]+)/', $chaîne, $découpage, PREG_OFFSET_CAPTURE);
		$types = array(1 => self::SYNTAXE, 2 => self::ESPACE, 3 => self::AUTRE);
		
		$dernièrePos = 0;
		foreach($découpage[0] as $numBout => $bout)
		{
			if($bout[1] != $dernièrePos)
				throw new \Exception('Oups! Élément incasable "'.substr($chaîne, $dernièrePos, $bout[1] - $dernièrePos).'" (position '.$dernièrePos.', "'.$chaîne.'")');
			do
			{
				foreach($types as $champ => $type)
					if(isset($découpage[$champ][$numBout][1]) && $découpage[$champ][$numBout][1] >= 0)
					{
						$r[] = array($type, $découpage[$champ][$numBout][0], $découpage[$champ][$numBout][1]);
						break 2;
					}
				throw new \Exception('Oups! Élément incasable "'.$bout[0].'" (position '.$bout[1].', "'.$chaîne.'")');
			} while(false);
			$dernièrePos += strlen($bout[0]);
		}
		if(strlen($chaîne) != $dernièrePos)
			throw new \Exception('Oups! Élément incasable "'.substr($chaîne, $dernièrePos).'" (position '.$dernièrePos.', "'.$chaîne.'")');
		
		return $r;
	}
	
	/**
	 * Détecte les ouvrants / fermants (parenthèses, guillemets, etc.) et réunifie leur contenu.
	 */
	protected function _empaqueter(& $bouts, $départ = 0, $sortieAttendue = false)
	{
		/* À FAIRE: c'est ici qu'on pourra aussi décider qu'un guillemet ouvrant compacte tout ce qui suit en une chaîne de caractères, quel que soit son type apparent. */
		$mode = -1;
		
		for($i = $départ; $i < count($bouts); ++$i)
		{
			if($sortieAttendue && array_intersect_key($bouts[$i], array(0 => true, 1 => true)) == $sortieAttendue)
				return $i;
			switch($bouts[$i][0])
			{
				case self::SYNTAXE:
					switch($bouts[$i][1])
					{
						case '(':
							$fin = $this->_empaqueter($bouts, $i + 1, $sousSortieAttendue = array(self::SYNTAXE, ')'));
							if(!isset($bouts[$fin]) || array_intersect_key($bouts[$fin], array(0 => true, 1 => true)) != $sousSortieAttendue)
								throw new \Exception('Oups! Imbrication mal fermée (position '.$bouts[$i][2].')');
							$extraction = array_splice($bouts, $i + 1, $fin - $i - 1);
							array_splice($bouts, $i, 2, array(array(self::BLOC, $extraction, null)));
							break;
						case ')':
							// Une parenthèse fermante ne devrait jamais parvenir jusqu'ici. Elle a dû être passée en tant que $sortieAttendue à un appel récursif.
							throw new \Exception('Oups! Fermeture sans ouverture (position '.$bouts[$i][2].')');
							break;
					}
					break;
			}
		}
	}
	
	const SYNTAXE = '.';
	const ESPACE = '_';
	const AUTRE = 'A';
	const BLOC = '@';
}

?>
