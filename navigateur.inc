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

/* Merci aux notes de bas de page sur php.net! */
if (!function_exists('iconv') && function_exists('libiconv')) { function iconv($input_encoding, $output_encoding, $string) { return libiconv($input_encoding, $output_encoding, $string); } }

/* Simule un navigateur web: retient l'état cookies, referer, dernière URL
 * absolue pour calculer les relatives. */

class Navigateur
{
	function effacerCookies() { $this->cookies = array(); $this->cookiesTasses = null; }
	function Navigateur() { $this->derniere = null; $this->effacerCookies(); $this->données = array(); }
	
	function tasserCookies()
	{
		$noms = array_keys($this->cookies);
		$n = count($noms) - 1;
		if($n < 0) { $this->cookiesTasses = null; return; }
		$this->cookiesTasses = $noms[$n].$this->cookies[$noms[$n]];
		while(--$n >= 0)
			$this->cookiesTasses .= '; '.$noms[$n].$this->cookies[$noms[$n]];
	}
	
	/* Calcule l'URL absolue atteinte depuis le nœud courant en attaquant une
	 * URL absolue, relative ou racine. */
	function url($url)
	{
			if($url{0} == '/') // URL absolue sur le même site.
			{
				$pos = strpos($this->derniere, '/', strpos($this->derniere, '://') + 3);
				return ($pos === false ? $this->derniere : substr($this->derniere, 0, $pos)).$url;
			}
			else if(strpos($url, 'http://') !== 0) // URL relative
			{
				if(($pos = strrpos($this->derniere, '/')) != 0)
					return substr($this->derniere, 0, $pos + 1).$url;
			}
			return $url;
	}
	
	/* Récupère une URL.
	 * Paramètres:
	 *   url: URL à obtenir
	 *   champs: tableau associatif envoyé en POST
	 *   continuer: si true, le Navigateur est mis-à-jour avec les nouvelles
	 *     infos collectées.
	 */
	function obtenir($url, $champs = null, $continuer = false)
	{
		$this->suivre = $url;
		$this->continuer = $continuer;
		
		while($this->suivre !== null) // On suit les Location: à la main, car sinon nos cookies reçus dans une réponse contenant à la fois un Location et un Set-Cookie ne sont pas pris en compte au fur et à mesure. Couillon de PHP ou de curl. /* À FAIRE?: récupérer la connexion au lieu de faire des curl_init()/curl_close() à chaque fois. */
		{
			$url = $this->suivre;
			$this->suivre = null;
			
			$c = curl_init();
			if($this->derniere) curl_setopt($c, CURLOPT_REFERER, $this->derniere);
			//curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
			//curl_setopt($c, CURLOPT_MAXREDIRS, 0x8);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
			$url = $this->url($url);
			curl_setopt($c, CURLOPT_URL, $url);
			curl_setopt($c, CURLOPT_COOKIE, &$this->cookiesTasses); // Par référence, car entre deux redirections, ils doivent avoir été mis à jour; et on ne peut intervenir que sur cette variable.
			if($champs !== null)
			{
				/* PHP est bourrin, il poste en multipart si $champs est un tableau.
				 * Ça ne nous plaît pas du tout, enfin nous si, mais pas Monster
				 * pour l'authentification. */
				$champsTasses = '';
				foreach($champs as $cle => $valeur)
					$champsTasses .= '&'.$cle.'='.urlencode(iconv('UTF-8', 'ISO-8859-1//IGNORE', $valeur)); /* À FAIRE: ne pas coder en dur cette saleté d'ISO-8859-1 (qui n'accepte même pas mon pasτεχ); dépendre des directives du formulaire rempli. */
				curl_setopt($c, CURLOPT_POSTFIELDS, substr($champsTasses, 1));
			}
			$GLOBALS['navigateur_recuperateurEnTetes'] = &$this;
			curl_setopt($c, CURLOPT_HEADERFUNCTION, 'navigateur_recupEnTete');
			if($this->auth)
			{
				curl_setopt($c, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
				curl_setopt($c, CURLOPT_USERPWD, $this->auth); // id:mdp
			}
			if($this->continuer)
				$this->derniere = $url;
			$r = curl_exec($c);
			curl_close($c);
			$this->tasserCookies();
			
			/* Pour continuer (redirection) */
			
			$champs = null;
		}
		
		return $r;
	}
	
	function aller($url, $champs = null) { return $this->obtenir($url, $champs, true); }
	
	function recupererEnTete($enTete)
	{
		if($this->continuer && (strpos($enTete, 'Set-Cookie: ') === 0))
		{
			if(($pos = strpos($enTete, ';', 0xc)) !== false)
			{
				$enTete = substr($enTete, 0xc, $pos - 0xc);
				$pos = strpos($enTete, '=');
				$this->cookies[substr($enTete, 0, $pos)] = substr($enTete, $pos);
			}
		}
		else if(strpos($enTete, 'Location: ') === 0 || strpos($enTete, 'location: ') === 0) // Sale con d'IIS de photoservice.com!
			$this->suivre = strtr(substr($enTete, 0xa), array("\n" => '', "\r" => '')); // Un caractère retour à la fin.
	}
	
	function rattacherALaSession(&$session)
	{
		if(is_array($session))
		{
			$this->derniere = &$session['derniere'];
			$this->cookies = &$session['cookies'];
			$this->données = &$session['données'];
			$this->tasserCookies();
		}
		else
		{
			$session['derniere'] = &$this->derniere;
			$session['cookies'] = &$this->cookies;
			$session['données'] = &$this->données;
		}
	}
	
	function cloner()
	{
		$nouveau = clone $this;
		
		unset($nouveau->données); $nouveau->données = array(); // C'est une référence (sur un objet de session), donc en clonant c'est toujours une référence sur le même; seul l'unset permettra à chacun des deux objets de poursuivre son bonhomme de chemin.
		unset($nouveau->cookies); $nouveau->cookies = $this->cookies;
		unset($nouveau->derniere); $nouveau->derniere = $this->derniere;
		
		return $nouveau;
	}
	
	protected $derniere;
	protected $cookies;
	public $données; // Données supplémentaires attachées à la session, laissées à la discrétion de l'utilisateur (ex.: résultat de l'interprétation de la page chargée).
	protected $suivre; // État: a-t-on reçu un Location: dans la dernière réponse?
}

function navigateur_recupEnTete($curl, $enTete)
{
	$GLOBALS['navigateur_recuperateurEnTetes']->recupererEnTete($enTete);
	return strlen($enTete);
}

/* Plusieurs Navigateurs retenus en session */

class NavigateurHiérarchique
{
	protected $navigo; // Pile de Navigateurs
	protected $explo; // Pointeur sur le petit dernier de $navigo.
	protected $stockage; // Élément de $_SESSION dans lequel on nous a donné carte blanche.
	
	/* Rattache le NavigateurHiérarchique à une session (à créer ou existante).
	 * Paramètres:
	 *   $où: tableau indiquant le chemin à suivre dans $_SESSION pour arriver à
	 *     l'espace réservé au stockage du NavigateurHiérarchique. */
	function préparerSession($où)
	{
		$this->navigo = array(); // PHP outrepassant tout ce qu'un esprit malade pourrait concevoir en matière de langage merdique à souhait, on ne peut pas sérialiser notre Navigateur dans la session car celle-ci est déroulée par un truc (de_session) qui y cherche ses réglages, alors que ce fichier (et donc la classe Navigateur) n'a pas encore été chargée, ce qui vautre PHP. On ne sérialise donc que le contenu du Navigateur, qu'on se fait chier ensuite à remettre en place à la main ici. Mais qu'est-ce qu'ils sont cons, alors!
		$this->stockage = &$_SESSION;
		foreach($où as $élément)
		{
			if(!array_key_exists($élément, $this->stockage))
				$this->stockage[$élément] = array();
			$this->stockage = &$this->stockage[$élément];
		}
		$i = count($this->stockage);
		if($i <= 0) $i = 1;
		while(--$i >= 0)
			$this->ajouterOuRecupérerDernierNavigo();
	}
	
	function ajouterOuRecupérerDernierNavigo()
	{
		$i = count($this->navigo);
		$this->navigo[$i] = $i == 0 ? new Navigateur() : $this->navigo[$i - 1]->cloner();
		$this->explo = &$this->navigo[$i];
		$this->explo->données['pos'] = 0;
		$this->explo->rattacherALaSession($this->stockage[$i]);
	}
	
	function supprimerDernierNavigo()
	{
		$i = count($this->navigo) - 1;
		if($i > 0)
			$this->explo = &$this->navigo[$i - 1];
		else
			$this->explo = null;
		array_splice($this->navigo, $i);
		array_splice($this->stockage, $i);
	}
}

?>