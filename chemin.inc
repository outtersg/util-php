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
	
	/*- Contenu --------------------------------------------------------------*/
	
	/* Suite de sens (-1 ou 1) et d'éléments de chemins depuis le dossier de
	 * création. Ainsi, avec '../toto/machin' en partant d'un répertoire 'titi',
	 * on contiendra [-1, titi, 1, toto, 1, machin]. */
	public $chemin;
	/* Si le chemin désigne une fichier, son nom est ajouté ici */
	public $fichier;
	/* Chemin auquel celui-ci est relatif. */
	public $depuis;
	/* Protocole par lequel s'obtient le chemin. Un protocole non nul désigne un
	 * chemin absolu ou un protocole (il vaut alors '/' dans le premier cas,
	 * 'protocole://///' dans le second (tous les slashes livrés avec le nom
	 * y apparaissent)). */
	public $protocole = null;
	
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
	
	function et($chemin)
	{
		$r = clone $this;
		if($chemin instanceof Chemin)
		{
			if($chemin->protocole === '/' && $r->protocole !== null && $r->protocole !== '/') // Un protocole façon URL passe devant un chemin absolu.
				$r->chemin = array_slice($r->chemin, 0, 2);
			else
			if($chemin->protocole !== null) // Si l'ajout est une adresse absolue, il se fiche de ce qu'on peut lui placer devant (par exemple $this).
				return clone $chemin;
			for($n = count($chemin->chemin), $i = 0; $i < $n; $i += 2)
				$r->ajouter($chemin->chemin[$i], $chemin->chemin[$i+1]);
			$r->fichier = $chemin->fichier;
		}
		else
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
						$this->chemin = array(); // Chemin absolu, on réinitialise.
						$this->protocole = substr($suiteDuCheminADecouper, 0, $fin + 1);
					}
					else
						$this->ajouter(1, $contenu);
				}
			}
			else if($debut == 0) // Le premier '/' est spécial, il indique un chemin absolu
			{
				if(!$this->surServeur() || !count($this->chemin) || $this->chemin[0] == -1) // Les protos dont le premier élément est déjà un élément de chemin.
				{
				$this->protocole = '/';
				$this->chemin = array();
				}
				else // Les protos dont le premier élément est un serveur (donc le chemin ne commence qu'au second).
					array_splice($this->chemin, 2);
			}
		$this->fichier = substr($suiteDuCheminADecouper, $debut);
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
				if($this->protocole === null)
					if($this->depuis) // Mais il reste peut-être encore notre $this->depuis.
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
		if($this->protocole !== null)  // Un protocole au début.
			$r = $this->protocole;
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
		if($moi->protocole)
		{
			if($autre->protocole && $autre->protocole != $moi->protocole)
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
		return ($this->protocole && $this->protocole != '/') || !$this->depuis ? $this : $this->depuis->absolu()->et($this);
	}
	
	function surServeur()
	{
		return $this->protocole && !in_array($this->protocole, $GLOBALS['CheminProtosSansServeur']);
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

$GLOBALS['CheminProtosSansServeur'] = array('/', 'file:');

$GLOBALS['cheminDepart'] = null; // Juste histoire qu'il soit défini pour servir de départ à $cheminDepart.
$GLOBALS['cheminDepart'] = $cheminDepart = new Chemin($_SERVER['SCRIPT_FILENAME']);

if(isset($_SERVER['REQUEST_URI']))
{
	Chemin::$UrlPubliqueDépart = Chemin::DepuisServeur($_SERVER);
	Chemin::$UrlPubliqueRacine = Chemin::$UrlPubliqueDépart->depuis;
}

?>