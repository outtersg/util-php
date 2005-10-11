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

/* ATTENTION: pour pouvoir remonter les chemins, il est considéré que le
 * chemin décrit ne contient aucun lien. */

class Chemin
{
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
	
	function et($chemin)
	{
		$r = clone $this;
		if(is_a($chemin, Chemin))
		{
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
				$this->protocole = '/';
				$this->chemin = array();
			}
		$this->fichier = substr($suiteDuCheminADecouper, $debut);
	}
	
	function ajouter($sens, $nom)
	{
		$n = count($this->chemin);
		if
		(
			/* .. */
			($sens == -1 && $n > 0 && $this->chemin[$n - 2] > 0)
			/* ../toto (si on était déjà dans toto) */
			|| ($n > 0 && $this->chemin[$n - 2] == -$sens && $this->chemin[$n - 1] === $nom)
		)
		{
			array_pop($this->chemin);
			array_pop($this->chemin);
			return;
		}
		
		/* Si .., on recherche le nom du courant */
		
		if($nom == null && $sens == -1)
		{
			$chemin = clone $this->depuis;
			$chem = $this->cheminDossier();
			$chemin->decouper($this->cheminDossier()); /* À FAIRE: utiliser un découper qui ne se refarcisse pas le découpage. */
			$nom = $chemin->chemin[$n - 1];
		}
		
		/* Cas simple */
		
		$this->chemin[] = $sens;
		$this->chemin[] = $nom;
	}
	
	/*- Consultation ---------------------------------------------------------*/
	
	function cheminDossierJusquA($n)
	{
		if($this->protocole !== null)  // Un protocole au début.
			$r = $this->protocole;
		else
			$r = '';
		
		for($i = 0; $i < $n; $i += 2)
			$r .= ($this->chemin[$i] == 1 ? $this->chemin[$i+1] : '..').'/';
		return $r;
	}
	
	function cheminDossier()
	{
		return $this->cheminDossierJusquA(count($this->chemin));
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
	
	function cheminComplet()
	{
		return $this->cheminDossier().$this->fichier;
	}
	
	/*- Manipulations --------------------------------------------------------*/
	
	function creerDossier($mode)
	{
		if(!$mode) $mode = 0700;
		for($taille = count($this->chemin), $i = $taille; $i > 0; $i -= 2)
			if(file_exists($this->cheminDossierJusquA($i)) === true)
			{
				while(($i += 2) <= $taille)
					mkdir(substr($this->cheminDossierJusquA($i), 0, -1), $mode); // Le serveur de Free refuse de créer des répertoires si le nom contient un '/' à la fin.
				return;
			}
	}
}

$cheminDepart = new Chemin($_SERVER['PHP_SELF']);

/*$chem = new Chemin('/Users/gui/tmp/');
$chem = $chem->et('../chose/');
print('/Users/gui/chose/'."\n");
print($chem->cheminComplet()."\n");
print_r($chem->chemin);print("\n");
$chem = new Chemin('http://www.apple.fr/images/truc.png');
$chem = $chem->et('../chose/');
print('http://www.apple.fr/chose/'."\n");
print($chem->cheminComplet()."\n");
print_r($chem->chemin);print("\n");
$chem = new Chemin('../chose/truc/');
$chem = $chem->et('../chose/');
print('../chose/chose/'."\n");
print($chem->cheminComplet()."\n");
print_r($chem->chemin);print("\n");
$chem = new Chemin('../chose/');
$chem = $chem->et('../../rien/');
print('../../rien/'."\n");
print($chem->cheminComplet()."\n");
print_r($chem->chemin);print("\n");
$chem = new Chemin('chose/index.html');
$chem = $chem->et('../rien/');
print('rien/'."\n");
print($chem->cheminComplet()."\n");
print_r($chem->chemin);print("\n");*/
/*print('====Resultat1====<br/>'."\n"); // cheminDepart vaut php/chamrousse
$chem = new Chemin();
$chem->decouper('toto/'); // php/chamrousse/toto
$chem2 = $chem;
print($chem->cheminDossier().'<br/>'); flush();
$chem2->decouper('../../titi/');
$chem2 = $chem2->dossierInverse();
print($chem2->cheminDossier().'<br/>'); flush();

print('====Resultat====<br/>'."\n");
$chem = new Chemin('../chamrousse/index.xhtml');
print($chem->cheminDossier().'<br/>'); flush();
$chem = $chem->dossierInverse();
print($chem->cheminDossier().'<br/>'); flush();*/

?>