<?php
/*
 * Copyright (c) 2003-2004 Guillaume Outters
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

require_once('compo.php');

/* Charge un XML. Fonctionne en pile à état: l'état courant est l'interprète du
 * nœud en cours, sur réception d'un sous-élément XML il peut soit décider de
 * garder la main sur la suite, soit renvoyer une référence sur un autre
 * interprète qui prend la suite (et la rendra lorsque l'on sortira du nœud XML
 * par lequel on y est entré).
 * L'interprète doit implémenter l'interface Compo. */

class Chargeur
{
	public $pile = array(); /* Pile des appels. */
	public $mem = null;
	public $tailleBloc = 0x1000;
	
	# Charge un fichier XML
	# Paramètres:
	#   chemin: chemin du fichier à charger. Chemin ou chaîne de caractères.
	#   nomRacine: nom prévu pour la racine XML. Si ce paramètre est null, le
	#     traiteurRacine sera appelé directement. Sinon, il sera appelé pour les
	#     fils de la racine (il est donc utile de le mettre à null si on
	#     s'intéresse aux attributs de la racine).
	#   traiteurRacine: objet de type Compo qui recevra les événements d'entrée
	#     et sortie de la racine ou de ses fils (en fonction de nomRacine).
	#   fatal: si true, une erreur de lecture tue le programme.
	function charger($chemin, $nomRacine, &$traiteurRacine, $fatal = false)
	{
		$r = true;
		if(is_a($chemin, Chemin)) $chemin = $chemin->cheminComplet();
		
		if(!($fichier = @fopen($chemin, 'r')))
			if($fatal)
				die('Ouverture de fichier impossible: '.$chemin);
			else
				return false;
		
		$this->pile[] = $nomRacine === null ? $traiteurRacine : new GobeurRacine($nomRacine, &$traiteurRacine);
		$interprete = xml_parser_create();
		xml_parser_set_option($interprete, XML_OPTION_CASE_FOLDING, FALSE);
		xml_set_object($interprete, &$this);
		xml_set_element_handler($interprete, 'entrer', 'sortir');
		xml_set_character_data_handler($interprete, 'contenu');
		
		$fin = false;
		
		do
		{
			if(($donnees = fread($fichier, $this->tailleBloc)) == null)
				$donnees = '';
			$fin = feof($fichier);
			
			/* Grâce à cet abruti d'expat qui ne sait pas constituer des fragments
			* continus de texte, à cet abruti d'expat qui ne sait pas faire un
			* xml_parse($donnees, $entre, $et), et à cet abruti de PHP 4.3 qui ne
			* sait pas faire de substr sans faire une allocation mémoire, nous
			* nous retrouvons avec un beau bout de code qui passe son temps en
			* malloc. Mais il le faut. */
			
			if($fin)
			{
				$taille = 1; // On peut le mettre à 1, ça n'a pas d'importance, la seule obligation est d'avoir $posFermeture = $taille > 0
				$posFermeture = 1;
			}
			else
			{
				$taille = strlen($donnees);
				if(($posFermeture = strrpos($donnees, '>')) === false) $posFermeture = 0; // On cherche la dernière occurence de caractère fermant de balise.
				else if($posFermeture > $taille - 0x40) // Si on se trouve vers la fin, il se peut que la fin du buffer atterrisse en pleine balise. La coupure en milieu de balise est gérée en interne par expat, on peut donc la laisser passer et ça nous arrange car ça nous laisse une mémoire vide.
				{
					if(($posFermeture2 = strpos($donnees, '<', $posFermeture)) !== false) // Une ouverture de balise entre la dernière fermeture de balise et la fin, donc la fin atterit dans une balise.
						$posFermeture = $taille;
				}
			}
			if($posFermeture > 0) // Si on trouve une occasion de césure.
			{
				$aFaire = $posFermeture == $taille ? $donnees : substr($donnees, 0, $posFermeture);
				if(!xml_parse($interprete, $mem === null ? $aFaire : $mem.$aFaire, $fin))
					if($fatal)
						die(sprintf('Erreur XML: %s, ligne %d: %s', $chemin, xml_get_current_line_number($interprete), xml_error_string(xml_get_error_code($interprete))));
					else
						$r = false;
				$mem = null;
			}
			if($posFermeture < $taille) // Reste du bazar à mettre en mémoire pour la prochaine fois
			{
				$aFaire = substr($donnees, $posFermeture - $taille);
				$mem = ($mem === null ? $aFaire : $mem.$aFaire);
			}
		} while(!$fin);
		
		xml_parser_free($interprete);
		fclose($fichier);
		
		return $r;
	}
	
	function chargerSansGroupage($chemin, $nomRacine, &$traiteurRacine, $fatal = false)
	{
		$r = true;
		if(is_a($chemin, Chemin)) $chemin = $chemin->cheminComplet();
		
		if(!($fichier = @fopen($chemin, 'r')))
			if($fatal)
				die('Ouverture de fichier impossible: '.$chemin);
			else
				return false;
		
		$this->pile[] = $nomRacine === null ? $traiteurRacine : new GobeurRacine($nomRacine, &$traiteurRacine);
		$interprete = xml_parser_create();
		xml_parser_set_option($interprete, XML_OPTION_CASE_FOLDING, FALSE);
		xml_set_object($interprete, &$this);
		xml_set_element_handler($interprete, 'entrer', 'sortir');
		xml_set_character_data_handler($interprete, 'contenu');
		
		while($donnees = fread($fichier, 4096))
			if(!xml_parse($interprete, $donnees, feof($fichier)))
				if($fatal)
					die(sprintf('Erreur XML: %s, ligne %d: %s', $chemin, xml_get_current_line_number($interprete), xml_error_string(xml_get_error_code($interprete))));
				else
					$r = false;
		xml_parser_free($interprete);
		fclose($fichier);
		
		return $r;
	}
	
	function entrer($interprete, $nom, $attrs)
	{
		$courant = &$this->courant();
		$resultat = &$courant->entrerDans(&$this->pile[count($this->pile)-1], $nom, $attrs);
		$this->pile[] = &$resultat;
		if(is_a($resultat, Compo))
			$resultat->entrer();
	}
	
	function sortir($interprete, $nom)
	{
		$dernier = &$this->pile[count($this->pile)-1];
		if(is_a(&$dernier, Compo))
			$dernier->sortir();
		array_pop($this->pile);
		$courant = &$this->courant();
		$courant->sortirDe(&$dernier);
	}
	
	function contenu($interprete, $chaine)
	{
		if(strlen(trim($chaine)) == 0) return;
		$courant = &$this->courant();
		$courant->contenuPour(&$this->pile[count($this->pile)-1], $chaine);
	}
	
	/* Renvoie le dernier Compo (compo courant) de la pile. */
	function &courant()
	{
		for($i = count($this->pile); (--$i) >= 0;)
			if(is_a($this->pile[$i], Compo))
				return $this->pile[$i];
	}
}

class GobeurRacine extends Compo
{
	public $nomRacine;
	public $racineCompo;
	
	function GobeurRacine($aBouffer, &$destination)
	{
		$this->nomRacine = $aBouffer;
		$this->racineCompo = &$destination;
	}
	
	function &entrerDans($depuis, $nom, $attributs)
	{
		if($nom == $this->nomRacine)
			return $this->racineCompo;
	}
}

?>
