<?php
/*
 * Copyright (c) 2003-2004,2006-2008 Guillaume Outters
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

/* ATTENTION: pour pouvoir remonter les chemins, il est considéré que le
 * chemin décrit ne contient aucun lien. */

class Chemin
{
	/*- Références -----------------------------------------------------------*/
	
	public static $UrlPubliqueRacine = null;
	public static $UrlPubliqueDépart = null;
	
	/**
	 * Définit le mode "propre" sur tous les appels à venir à et().
	 * En mode pas propre, et() agrège le maximum de choses dans un clone de $this. En mode propre, $this sert de depuis au nouvel élément, qui ne possède alors plus que son supplément de chemin par rapport à $this.
	 */
	public static $EtPropre = false;
	
	/*- Contenu --------------------------------------------------------------*/
	
	/* Suite de sens (-1 ou 1) et d'éléments de chemins depuis le dossier de
	 * création. Ainsi, avec '../toto/machin' en partant d'un répertoire 'titi',
	 * on contiendra [-1, titi, 1, toto, 1, machin]. */
	public $chemin;
	/* Si le chemin désigne une fichier, son nom est ajouté ici */
	public $fichier;
	/**
	 * @var null|string|Chemin
	 *    null: chemin relatif sans rattachement
	 *    Chemin: chemin auquel celui-ci est relatif
	 *    string: protocole: soit '/', soit 'protocole://///' (tous les slashes
	 *      livrés avec le nom apparaissent).
	 */
	public $depuis;
	
	/*- Construction ---------------------------------------------------------*/
	
	/* Constructeur. Le paramètre 'depuis' doit être renseigné si le répertoire
	 * de départ est différent du répertoire courant (d'exécution du script). Il
	 * permet l'exploration correcte du système de fichier autour du chemin. */
	function Chemin($acces = null, $depuis = null)
	{
		$this->chemin = array();
		$this->fichier = null;
		$this->depuis = ($depuis === null ? $GLOBALS['cheminDepart'] : $depuis); // Tant qu'on ne nous dit pas le contraire
		
		if($acces)
			$this->decouper($acces);
	}
	
	/**
	 * Crée un chemin pour l'URL racine d'un tableau de type _SERVER (à partir de REQUEST_URI, les X-Forwarded-, etc.).
	 */
	public static function DepuisServeur($envServeur)
	{
		$proto = 'http';
		$hôte = isset($envServeur['HTTP_X_FORWARDED_HOST']) ? $envServeur['HTTP_X_FORWARDED_HOST'] : $envServeur['SERVER_NAME'];
		$complémentPort = isset($envServeur['HTTP_X_FORWARDED_PORT']) ? ':'.$envServeur['HTTP_X_FORWARDED_PORT'] : '';
		if(!$complémentPort)
		{
			$port = $envServeur['SERVER_PORT'];
			$portDéfaut = $proto == 'http' ? 80 : ($proto == 'https' ? 443 : null);
			if($port != $portDéfaut)
				$complémentPort = ':'.$port;
		}
		$racine = '/';
		$préfixePublic = '';
		if(isset($envServeur['HTTP_X_FORWARDED_URI']))
		{
			$uris = array
			(
				'u' => $envServeur['HTTP_X_FORWARDED_URI'], // pUblique.
				'r' => $envServeur['REQUEST_URI'], // pRivée.
			);
			foreach($uris as & $ptrUri)
				$ptrUri = preg_replace('#/(?:([^/]*\.php)(?:|/.*)|[^/]*)$#', '/\1', $ptrUri);
			$pos = 0;
			while(($pos = strpos($uris['u'], '/', $pos)) !== false)
			{
				if(substr($uris['u'], $pos) == $uris['r'])
				{
					$préfixePublic = substr($uris['u'], 0, $pos);
					break;
				}
				++$pos;
			}
		}
		$urlPubliqueRacine = $proto.'://'.$hôte.$complémentPort.$préfixePublic.'/';
		$urlPubliqueRacine = new Chemin($urlPubliqueRacine);
		
		$urlPubliqueDépart = $urlPubliqueRacine->et($uris['u'])->cheminDepuis($urlPubliqueRacine);
		
		return $urlPubliqueDépart;
	}
	
	/**
	 * Tire un nouveau chemin de $this ($this devient son depuis).
	 */
	public function dou($chemin)
	{
		return $this->et($chemin, true);
	}
	
	function et($chemin, $propre = null)
	{
		if(!isset($propre))
			$propre = Chemin::$EtPropre;
		if($propre)
			$r = new Chemin($chemin instanceof Chemin ? null : $chemin, $this);
		else
		$r = clone $this;
		if($chemin instanceof Chemin)
		{
			if($chemin->depuis === '/' && $r->depuis !== null && $r->depuis !== '/') // Un protocole façon URL passe devant un chemin absolu.
				$r->_enraciner();
			else if(is_string($chemin->depuis)) // Si l'ajout est une adresse absolue, il se fiche de ce qu'on peut lui placer devant (par exemple $this).
				return clone $chemin;
			for($n = count($chemin->chemin), $i = 0; $i < $n; $i += 2)
				$r->ajouter($chemin->chemin[$i], $chemin->chemin[$i+1]);
			$r->fichier = $chemin->fichier;
		}
		else
			if(!$propre)
			$r->decouper($chemin);
		
		return $r;
	}
	
	/*- Interne --------------------------------------------------------------*/
	
	function decouper($suiteDuCheminADecouper)
	{
		$fin = -1;
		while(($fin = strpos($suiteDuCheminADecouper, '/', $debut = $fin + 1)) !== false)
			if($debut < $fin)
			{
				$contenu = substr($suiteDuCheminADecouper, $debut, $fin - $debut);
				if($contenu == '..')
					$this->ajouter(-1, null);
				else if($contenu != '.')
				{
					if($debut == 0 && ($f = strpos($contenu, ':')) !== false) // Protocole, avant le premier '/'
					{
						$fin = $f;
						while(($suite = substr($suiteDuCheminADecouper, ++$f, 1)) == '/') // On passe tous les '/': tant qu'on a pas le serveur, ils peuvent faire partie du protocole et il faut donc les conserver, ce que ne fait pas notre traitement de chemin habituel.
							++$fin;
						$this->_enraciner(substr($suiteDuCheminADecouper, 0, $fin + 1));
					}
					else
						$this->ajouter(1, $contenu);
				}
			}
			else if($debut == 0) // Le premier '/' est spécial, il indique un chemin absolu
				$this->_enraciner('/');
		$this->fichier = substr($suiteDuCheminADecouper, $debut);
	}
	
	/**
	 * Réinitialise le chemin à sa racine.
	 *
	 * @param string $par Début de chemin qui nous fait enraciner (protocole ou /; ou null pour enraciner sur le protocole actuel).
	 */
	protected function _enraciner($par = null)
	{
		// On ne peut plus être depuis notre _depuis que si celui-ci est déjà à sa racine.
		
		if(($par === '/' || !$par) && $this->surServeur())
		{
			// Cas pourri: si nous ne sommes pas le point de départ, nous devons aller vérifier que ce dernier est déjà à sa racine; sinon il nous faudra devenir nous-mêmes racine.
			$depuis = $this->ancêtreRacine(true);
			if(!is_integer($depuis) && $depuis !== $this) // Si c'est un entier, pas de bol: on n'a pas assez d'éléments pour trouver la racine. Tant pis.
				$this->depuis = $depuis;
		}
		else
		{
			$this->chemin = array();
			if($par)
				$this->depuis = $par;
		}
	}
	
	/**
	 * Trouve le premier ancêtre qui soit racine ou le crée si besoin.
	 * Ancêtre racine = a 0 ou 1 élément de chemin (0 pour les protos sans serveur, 1 pour ceux avec serveur).
	 */
	public function ancêtreRacine($meTronquerSiBesoin = false)
	{
		if(is_object($this->depuis))
			$r = $this->depuis->ancêtreRacine();
		else if(!$this->depuis) // Aïe, on est un chemin purement absolu; on ne pourra jamais renvoyer d'ancêtre racine…
			$r = '/';
		else // Nous sommes le point de départ!
			$r = $this->surServeur() ? 2 : 0; // * 2 car nous avons à chaque fois un sens et l'élément de chemin.
		// Interprétation du résultat intermédiaire.
		if(is_integer($r)) // Il nous faut un certain nombre d'éléments de chemin.
		{
			if(($r -= count($this->chemin)) == 0) // Pile ce qu'il manquait! Nous constituons une racine.
				$r = $this;
			else if($r < 0) // Bon, trop d'éléments. Qu'est-on autorisé à faire?
			{
				$rbis = $meTronquerSiBesoin ? $this : clone $this;
				array_splice($rbis->chemin, $r);
				$r = $rbis;
			}
			// Else il en manque encore; on transmet $r tel quel, à notre appelant de compléter.
		}
		return $r;
	}
	
	function ajouter($sens, $nom)
	{
		$n = count($this->chemin);
		if
		(
			/* .. */
			($n > ($remonteeMax = 2 * $this->surServeur()) && $sens == -1 && $this->chemin[$n - 2] > 0)
			/* ../toto (si on était déjà dans toto) */
			|| ($n > $remonteeMax && $this->chemin[$n - 2] == -$sens && $this->chemin[$n - 1] === $nom)
		)
		{
			array_pop($this->chemin);
			array_pop($this->chemin);
			return;
		}
		
		/* Si .., on recherche le nom du courant */
		
		if($sens == -1) // On va vérifier que la remontée est possible.
			if(($nom = $this->positiver($sens)) === false)
				return; // Interdit de remonter, on n'ajoute donc pas ce .. à notre liste.
		
		/* Cas simple */
		
		$this->chemin[] = $sens;
		$this->chemin[] = $nom;
	}
	
	/* Remontant le chemin, tente d'annuler $sens.
	 * Retour:
	 * - false: interdiction de remonter tant que ça (au-dessus de la racine du
	 *   système de fichiers).
	 * - null: on peut remonter, mais on ne connaîtra jamais le nom du dossier
	 *   duquel on sort.
	 * - autre chose: nom du dossier dont on sort en remontant.
	 */
	function positiver($sens)
	{
		$n = count($this->chemin);
		$remonteeMax = 2 * $this->surServeur();
		while($sens < 0)
		{
			if(($n -= 2) < $remonteeMax) // On a épuisé le chemin.
			{
				if(is_object($this->depuis)) // Mais il reste peut-être encore notre $this->depuis.
						return $this->depuis->positiver($sens);
					else
						return null;
				return false;
			}
			$sens += $this->chemin[$n];
		}
		return $this->chemin[$n + 1];
	}
	
	/*- Consultation ---------------------------------------------------------*/
	
	function cheminDossierJusquA($n, $url = false)
	{
		if(is_string($this->depuis))  // Un protocole au début.
			$r = $this->depuis;
		else
			$r = '';
		
		for($i = 0; $i < $n; $i += 2)
			$r .= ($this->chemin[$i] == 1 ? ($url ? rawurlencode($this->chemin[$i+1]) : $this->chemin[$i+1]) : '..').'/';
		return $r ? $r : './';
	}
	
	function cheminDossier($url = false)
	{
		return $this->cheminDossierJusquA(count($this->chemin), $url);
	}
	
	function dossierInverse()
	{
		if(array_key_exists(0, $this->chemin) && $this->chemin[0] == 0) return FALSE;
		$r = new Chemin();
		for($i = count($this->chemin); ($i -= 2) >= 0;)
		{
			$r->chemin[] = -$this->chemin[$i];
			$r->chemin[] = $this->chemin[$i+1];
		}
		$r->depuis = $this;
		return $r;
	}
	
	/* Trouve le chemin relatif entre $autre et $this. */
	function cheminDepuis($autre)
	{
		/* Tout le monde part du même point (si possible). */
		
		$autre = $autre->absolu();
		$moi = $this->absolu();
		if(is_string($moi->depuis))
		{
			if($autre->depuis && $autre->depuis != $moi->depuis)
				return $moi; // On reste en absolu.
		}
		
		/* On parcourt ce qu'il y a de commun. */
		
		for($i = 0, $n = count($autre->chemin); $i < $n && $autre->chemin[$i] == $moi->chemin[$i] && ($autre->chemin[$i] < 0 || $autre->chemin[$i + 1] == $moi->chemin[$i + 1]); $i += 2) {}
		
		/* On rabiboche le reste. */
		
		$r1 = new Chemin();
		$r1->chemin = array_slice($autre->chemin, $i);
		$r2 = new Chemin();
		$r2->chemin = array_slice($moi->chemin, $i);
		
		$r = $r1->dossierInverse()->et($r2);
		$r->fichier = $moi->fichier;
		$r->depuis = $autre;
		return $r;
	}
	
	function __toString()
	{
		return $this->cheminComplet();
	}
	
	function cheminComplet()
	{
		$d = $this->cheminDossier();
		return $this->fichier && $d == './' ? $this->fichier : $d.$this->fichier;
	}
	
	function url()
	{
		$d = $this->cheminDossier(true);
		$urlFichier = $this->fichier ? rawurlencode($this->fichier) : $this->fichier;
		return $this->fichier && $d == './' ? $urlFichier : $d.$urlFichier;
	}
	
	/* Renvoie (si possible) le chemin absolu de celui-ci, en allant chercher
	 * dans $this->depuis. */
	function absolu()
	{
		return is_object($this->depuis) ? $this->depuis->absolu()->et($this) : $this;
	}
	
	function surServeur()
	{
		if(is_object($this->depuis))
			return $this->depuis->surServeur();
		return is_string($this->depuis) && !in_array($this->depuis, $GLOBALS['CheminProtosSansServeur']);
	}
	
	/*- Manipulations --------------------------------------------------------*/
	
	function creerDossier($mode = false)
	{
		if(!$mode) $mode = 0700;
		for($taille = count($this->chemin), $i = $taille; $i > 0; $i -= 2)
			if(file_exists($this->cheminDossierJusquA($i)) === true)
			{
				$umask = umask(0);
				while(($i += 2) <= $taille)
					mkdir(substr($this->cheminDossierJusquA($i), 0, -1), $mode); // Le serveur de Free refuse de créer des répertoires si le nom contient un '/' à la fin.
				umask($umask);
				return;
			}
	}
}

function detruireChemin($chemin)
{
	/* On assainit. */
	
	$chemin instanceof Chemin && $chemin = $chemin->cheminComplet();
	while(substr($chemin, -1) == '/') $chemin = substr($chemin, 0, -1);
	
	/* On analyse. */
	
	if(is_link($chemin) || !is_dir($chemin))
		return unlink($chemin);
	else
	{
		$d = opendir($chemin);
		while(($c = readdir($d)))
		{
			if($c == '.' || $c == '..') continue;
			detruireChemin($chemin.'/'.$c);
		}
		closedir($d);
		rmdir($chemin);
	}
}

if(isset($GLOBALS['CHEMIN_ET_PROPRE']))
	Chemin::$EtPropre = $GLOBALS['CHEMIN_ET_PROPRE'];

$GLOBALS['CheminProtosSansServeur'] = array('/', 'file:');

$GLOBALS['cheminDepart'] = null; // Juste histoire qu'il soit défini pour servir de départ à $cheminDepart.
$GLOBALS['cheminDepart'] = $cheminDepart = new Chemin($_SERVER['SCRIPT_FILENAME']);

if(isset($_SERVER['REQUEST_URI']))
{
	Chemin::$UrlPubliqueDépart = Chemin::DepuisServeur($_SERVER);
	Chemin::$UrlPubliqueRacine = Chemin::$UrlPubliqueDépart->depuis;
}

?>