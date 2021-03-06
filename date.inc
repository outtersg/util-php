<?php
/*
 * Copyright (c) 2003,2005,2007,2013 Guillaume Outters
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

function  aff_tableau($tableau) { return Date::aff_tableau($tableau); }
function  date_affichage_tableau($tableau) { return Date::date_affichage_tableau($tableau); }
function  date_affichage($date) { return Date::date_affichage($date); }
function  decouper_datation($date) { return Date::fem(Date::lire($date)); }
function  decouper_heure($date, $tenterAussiLaDate) { return Date::fem(Date::lireHeure($date, $tenterAussiLaDate)); }
function  decouper_date($date) { return Date::fem(Date::lireJour($date)); }
function  assembler_datation($tabDate, $tabHoraire) { return Date::fem(Date::assembler(Date::mef($tabDate), Date::mef($tabHoraire))); }
function  completer_datation($original, $avec) { return Date::fem(Date::completer(Date::mef($original), Date::mef($avec))); }
function  calculer_datation($chose) { return Date::calculer(Date::mef($chose)); }
function  obtenir_datation($secondes) { return Date::obtenir_datation($secondes); }
function  corriger_datation($chose) { return Date::fem(Date::corriger(Date::mef($chose))); }
function  comparer_datations($d0, $d1, $considererCommeDebutEtFin = null, $occupationIndefinis = 1) { return Date::comparer(Date::mef($d0), Date::mef($d1), $considererCommeDebutEtFin, $occupationIndefinis); }
function  tester_decoupage_datation() { return Date::tester_decoupage_datation(); }
function  afficher_test_decoupage_datation($date) { return Date::afficher_test_decoupage_datation($date); }

class Date
{

/*- Débogage -----------------------------------------------------------------*/

static function aff_tableau($tableau)
{
	$machin = '';
	for($i = 0; $i < count($tableau); ++$i)
		$machin .= (($i == 0 ? '[' : ', ').$tableau[$i]);
	$machin .= ']';
	return $machin;
}

static function date_affichage_tableau($tableau) { return Date::aff($tableau); }
static function aff($tableau)
{
	$masque = 0;
	
	/* Jour */
	
	$jour = '';
	for($i = 0; $i < 3; ++$i)
		if((($nombre = $tableau[$i]) !== null) || ($i == 1 && $tableau[0] !== null && $tableau[2] !== null))
		{
			if($nombre < 0) $nombre = 1;
			if($i == 0)
				$jour = sprintf('%04d', $nombre);
			else
				$jour .= sprintf('%s%02d', ($masque ? '-' : ''), $nombre);
			$masque = 2;
		}
	
	/* Heure */
	
	$heure = '';
	$unites = array('h', 'm', 's');
	$t2 = array();
	for($i = 3; $i < 6; ++$i)
		if($tableau[$i] !== null)
		{
			$heure .= "%02.2d".$unites[$i - 3];
			$t2[] = $tableau[$i];
		}
	if($heure)
	{
		foreach(array('%02.2dh%02.2dm%02.2ds' => '%02.2d:%02.2d:%02.2d', '%02.2dh%02.2dm' => '%02.2d:%02.2d') as $a => $n)
			if($heure == $a)
				$heure = $n;
		$heure = sprintf($heure, $t2[0], $t2[1], $t2[2]);
		$masque |= 1;
	}
	
	return $jour.($masque == 3 ? ' ' : '').$heure;
}

static function date_affichage($date)
{
	return strftime('%Y-%m-%d %H:%M:%S', $date);
}

/*- Découpage de dates et heures ---------------------------------------------*/

static $g_nomChamps = array('heures', 'minutes', 'secondes');
static $g_chiffres = '0123456789';

static function lire($date)
{
	return Date::lireHeure($date, true);
}

static function lireHeure($date, $tenterAussiLaDate)
{
	$retour = array();
	$fin = strlen($date);
	
	/* Prélèvement de l'horaire */
	
	$valeurs = array();
	$suggestion = array(-1); // 0: heure; 1: minute; 2: seconde; calculé à partir du délimiteur suivant le nombre.
	$suggestion2 = array(); // Pareil, mais calculé par le délimiteur précédent le nombre.
	while($fin > 0)
	{
		/* Recherche du nombre */
		
		$debut = $fin;
		while(--$debut >= 0 && false !== strpos(Date::$g_chiffres, $c = $date{$debut})) {}
		$valeurs[] = ($debut + 1 < $fin ? intval(substr($date, $debut + 1, $fin)) : null);
		while(--$debut >= 0 && false === strpos(Date::$g_chiffres, $c2 = $date{$debut}))
			$c = $c2.$c;
		++$debut;
		
		$c = strtr($c, array(' ' => '', ' ' => '')); // Réduction des espaces et espaces insécables.
		if(strlen($c) < 1)
			$c = ' ';
		
		/* Interprétation du séparateur */
		
		if($debut < 0)
			$suggestion2[] = 0;
		else switch($c)
		{
			case ' ':
			case 'T':
				$suggestion2[] = 0;
				break;
			case 'h':
			$suggestion[] = 0;
			$suggestion2[] = 1;
				break;
			case "'":
			case 'm':
			case 'mn':
			$suggestion[] = 1;
			$suggestion2[] = 2;
				break;
			case '"':
			case 's':
			$suggestion[] = 2;
				break;
			case ':':
				break;
			default:
			if($tenterAussiLaDate)
				return Date::assembler(Date::lireJour($date), array(null, null, null));
			//print('### délimiteur horaire non reconnu: \''.$c.'\' dans '.$date.'.<br/>');
			return null;
		}
		
		if(count($suggestion) < count($valeurs))
			$suggestion[] = -1;
		if(count($suggestion2) < count($valeurs) + 1)
			$suggestion2[] = -1;
		
		$fin = $debut;
		
		if($c == ' ' || $c == 'T') break;
	}
	
	/* Rangement de l'horaire dans les bonnes cases */
	
	$rangement = array(null, null, null); // Indice dans $valeurs du machin qui doit aller dans la case correspondante; plus tard on y mettra la valeur elle-même, mais à chaque jour suffit sa peine.
	
	for($debut = count($valeurs); --$debut >= 0;) // Première passe: les champs dont on est sûr de la destination (le $suggestion correspondant a une valeur positive).
		if($valeurs[$debut] !== null && $suggestion[$debut] >= 0)
			if($rangement[$suggestion[$debut]] === null)
			{
				$rangement[$suggestion[$debut]] = $debut;
				$suggestion2[$debut] = -1;
			}
			else
			{
				//print('### le champ \''.Date::$g_nomChamps[$suggestion[$debut]].'\' apparaît plusieurs fois dans '.$date.'.<br/>');
				return null;
			}
	
	for($j = count($rangement); --$j >= 0;) // Deuxième passe: les suggestions un peu moins fiables sont utilisées pour remplir les trous
		if($rangement[$j] === null) // Pas trouvé à la première passe.
			for($debut = count($valeurs); --$debut >= 0;)
				if(isset($valeurs[$debut]) && $suggestion2[$debut] == $j)
					if($rangement[$j] === null)
						$rangement[$j] = $debut;
					else
					{
						//print('### le champ \''.Date::$g_nomChamps[$j].'\' apparaît plusieurs fois dans '.$date.'.<br/>');
						return null;
					}
	
	for($nTrous = 0, $j = 0, $debut = count($valeurs); $j < 3; ++$j) // Dernière passe: remplissage avec ce qui reste, dans l'ordre commun: heure-minute-seconde.
		if($rangement[$j] === null) // Un truc restant à remplir.
		{
			while(--$debut >= 0 && ($valeurs[$debut] === null || $suggestion[$debut] >= 0 || $suggestion2[$debut] >= 0)) {} // Tant qu'on a des valeurs, invalides ou déjà exploitées, on remonte.
			if($debut < 0) { ++$nTrous; continue; }
			$rangement[$j] = $valeurs[$debut]; // On stocke directement la valeur.
		}
		else
			$rangement[$j] = $valeurs[$rangement[$j]]; // On en profite pour faire le remplissage définitif, avec les valeurs.
	
	if($nTrous >= 2) // Un seul nombre ne nous suffit pas à l'accepter en tant qu'heure seule.
	{
		for($j = count($valeurs); --$j >= 0;)
			if($valeurs[$j] !== null)
				break;
		if($suggestion[$j] < 0) // Bon, si on a trouvé un marquage fort, on continue en considérant qu'il s'agit d'une heure.
		{
			if($tenterAussiLaDate)
				return Date::assembler(Date::lireJour($date), array(null, null, null));
			//print('### heure incomplète dans '.$date.' (il faut au moins les heures et minutes).<br/>');
			return null;
		}
	}
	
	/* Interprétation de la date */
	
	return ($tenterAussiLaDate ? Date::assembler($fin > 0 ? Date::lireJour(substr($date, 0, $fin)) : array(null, null, null), $rangement) : $rangement);
}

static function lireJour($date)
{
	if(strlen($date) == 0) return array(null, null, null);
	
	/* Stockage des valeurs */
	
	$fin = strlen($date);
	$valeurs = array();
	$sep = null; // Séparateur; on s'attend à avoir toujours le même.
	$longueurs = array(); // Longueur de chaque champ.
	while($fin > 0)
	{
		$debut = $fin;
		while(--$debut >= 0 && false !== strpos(Date::$g_chiffres, $c = $date{$debut})) {}
		if($debut == $fin - 1)
		{
			//print('### double séparateur dans la date '.$date.'.<br/>');
			return null;
		}
		$valeurs[] = intval(substr($date, $debut + 1, $fin));
		$longueurs[] = $fin - $debut - 1;
		if($debut < 0) break;
		
		if($sep == null)
		{
			if($c != '/' && $c != '-')
			{
				//print('### \''.$c.'\' inutilisable comme séparateur de date ('.$date.').<br/>');
				return null;
			}
			$sep = $c;
		}
		else if($sep != $c)
		{
			//print('### séparateurs inconsistents dans la date '.$date.'.<br/>');
			return null;
		}
		
		$fin = $debut;
	}
	
	/* Finalisation */
	
	$fin = count($valeurs);
	if($fin > 3)
	{
		//print('### la date '.$date.' contient trop de champ. Je m\'attendais à un simple an-mois-jour, sans précision du jour de la semaine ni de l\'époque Ming en cours.<br/>');
		return null;
	}
	
	if($sep == '-' || $sep == null) // Mode ISO, à remettre en bon ordre (parce qu'on a interprété en inverse).
	{
		if($fin >= 2)
		{
			$debut = $valeurs[0];
			$valeurs[0] = $valeurs[$fin - 1];
			$valeurs[$fin - 1] = $debut;
		}
	}
	
	/* Recherche de l'année (quatre chiffres); On décale en conséquence. */
	
	if($fin > 0 && $longueurs[0] == 4) // La date contient une année; elle reste en première position.
		while($fin < 3) { $valeurs[] = null; ++$fin; }
	else // Pas trouvé d'année, on complète par des null (ce qui nous donne: 1 élément: jour; 2: jour et mois, 3: ben tout).
	{
		for($debut = 3; --$fin >= 0;)
			$valeurs[--$debut] = $valeurs[$fin];
		while(--$debut >= 0)
			$valeurs[$debut] = null;
	}
	
	return $valeurs;
}

static function assembler($tabDate, $tabHoraire)
{
	if($tabDate == null || $tabHoraire == null) return null;
	for($i = 0; $i < count($tabHoraire); ++$i)
		$tabDate[] = $tabHoraire[$i];
	
	return $tabDate;
}

/*- Calculs sur tableaux de dates --------------------------------------------*/

static function completer($original, $avec)
{
	if($original == null) return $avec;
	if($avec == null) return $original;
	for($i = 6; --$i >= 0;)
		if($avec[$i] !== null)
			$original[$i] = $avec[$i];
	return $original;
}

/* Enlève de $date les éléments déjà présents dans $reference. */
static function decompleter($date, $reference)
{
	if(!$date || !$reference)
		return $date;
	for($i = -1; ++$i < 6;)
		if($reference[$i] === $date[$i])
			$date[$i] = null;
		else
			break;
	return $date;
}

static function calculer($chose)
{
	/* Un mktime avec des null, ça ne va pas être terrible. */
	if($chose == null || in_array(null, $chose))
		$chose = Date::completer(array(1970, 1, 1, 0, 0, 0), $chose);
	return mktime($chose[3], $chose[4], $chose[5], $chose[1], $chose[2], $chose[0] >= 0 ? $chose[0] : 0);
}

static function obtenir_datation($secondes) { return Date::obtenir($secondes); }
static function obtenir($secondes)
{
	$t = getdate($secondes);
	return array($t['year'], $t['mon'], $t['mday'], $t['hours'], $t['minutes'], $t['seconds']);
}

/* Transforme les 32 janvier en 1er février, etc. */
static function corriger($chose)
{
	/* calculer_datation remplaçant nos indéfinis, il faudra les remettre en
	 * place à posteriori. */
	$indefinis = array();
	foreach($chose as $i => $n)
		if($n === null)
			$indefinis[] = $i;
	$r = obtenir_datation(Date::calculer($chose));
	foreach($indefinis as $i)
		$r[$i] = null;
	return $r;
}

/**
 * Renvoie un nombre inférieur, égal ou supérieur à 0 selon que le premier
 * paramètre est gnagnagna, gnagnagna ou gnagnagna blabla. Ne fonctionne pas
 * forcément si vous laissez trop de trous dans vos dates.
 * 
 * @param Datation $d0 Première date.
 * @param Datation $d1 Seconde date.
 * @param mixed $considererCommeDebutEtFin Si null, les indéfinis ne sont pas comparés, et un indéfini est considéré égal à tout. Si booléen, les indéfinis sont comparés, soit en étant pris à leur début (si false), soit en étant pris à leur début pour $d0 et fin pour $d1 (si true). Ainsi, à true, $d0 au 26 janvier est équivalent au 26 janvier 00:00:00, tandis que $d1 au 26 janvier sera équivalent au 26 janvier 23:59:59.
 * @param float $occupationIndefinis Portion de l'unité qu'occupe un indéfini. À 1, on prend l'unité incluse complète (le 26 janvier est un 26 janvier à partir de 00:00:00), tandis qu'à 2/3, on le prend à partir de 8 h du mat'.
 * @return integer <, = ou > à 0, selon que $d0 est <, = ou > que $d1.
 */
static function comparer($d0, $d1, $considererCommeDebutEtFin = null, $occupationIndefinis = 1)
{
	if($d0 === null)
		if($d1 === null)
			return 0;
		else
			return -2;
	if($d1 === null)
		return 2;
	for($i = 0; $i < 6; ++$i)
	{
		if($d0[$i] === null && $d1[$i] === null)
		{
			if(isset($considererCommeDebutEtFin) && $considererCommeDebutEtFin)
				return 0.5 - $occupationIndefinis; // Le début est inférieur à la fin tant que, sur l'unité à départager, il ne sont pas « repoussés » plus loin que la moitié.
			return 0;
		}
		if($d0[$i] === null || $d1[$i] === null)
		{
			if(!isset($considererCommeDebutEtFin))
			return 0; // Vous êtes incomparables, toutes les deux.
			// Puisqu'on a des instructions pour le complément, on les applique, et on continuera dans la boucle comme si de rien n'était.
			$tRefs = array(0 => & $d0, 1 => & $d1);
			foreach($tRefs as $num => & $d)
				if($d[$i] === null)
				{
					$dzero = Date::calculer(Date::completer(array(1970, 1, 1, 0, 0, 0), $d));
					++$d[$i - 1];
					$dplusun = Date::calculer(Date::completer(array(1970, 1, 1, 0, 0, 0), $d)) - 1; // Et on retire une petite seconde pour être au bout du bout, mais toujours dans le cadre.
					$plutotApres = ($num && $considererCommeDebutEtFin) ? $occupationIndefinis : 1 - $occupationIndefinis;
					$d = Date::obtenir((1 - $plutotApres) * $dzero + $plutotApres * $dplusun);
				}
		}
		if($d0[$i] < $d1[$i]) return -1;
		if($d0[$i] > $d1[$i]) return 1;
	}
	return 0;
}
	
	static function calculerAvecIndefinis($d, $plutotApres = false, $occupationIndefinis = 1)
	{
		// Cas particulier: une date « Maintenant ».
		if($d === array(null, null, null, null, null, null))
			return time();
		// Cas particulier: une date ultra précise.
		for($i = -1; ++$i < 6 && $d[$i] !== null;) {}
		if($i == 6)
			return self::calculer($d);
		/* COPIE: comparer() possède un bout ressemblant vachement à ça. */
		$dzero = Date::calculer(Date::completer(array(1970, 1, 1, 0, 0, 0), $d));
		++$d[$i - 1];
		$dplusun = Date::calculer(Date::completer(array(1970, 1, 1, 0, 0, 0), $d)) - 1; // Et on retire une petite seconde pour être au bout du bout, mais toujours dans le cadre.
		$plutotApres = $plutotApres ? $occupationIndefinis : 1 - $occupationIndefinis;
		$d = (1 - $plutotApres) * $dzero + $plutotApres * $dplusun;
		
		return $d;
	}

static function moins($date, $moins)
{
	foreach($date as $i => $e)
		if($e !== null && array_key_exists($i, $moins) && $moins[$i] !== null)
		{
			$date[$i] -= $moins[$i];
			$date = Date::corriger($date);
		}
	return $date;
}
	
	public static function dernierChampPlus($date, $ajout)
	{
		for($i = 6; --$i >= 0;)
			if(isset($date[$i]))
			{
				$date[$i] += $ajout;
				$date = Date::corriger($date);
				break;
			}
		return $date;
	}

/*- Tests --------------------------------------------------------------------*/

static function tester_decoupage_datation()
{
	$tests = array
	(
		'2003-08-30',
		'31/8 12:07',
		'8h00',
		'23',
		'2003-08-24 03:16:48',
		'23/08/2003 23:13:22',
		'3 mn 15',
		'8 m 15s',
		'15',
		'15h',
	);
	for($y = count($tests); --$y >= 0;)
		afficher_test_decoupage_datation($tests[$y]);
}

static function afficher_test_decoupage_datation($date)
{
	$tableau = decouper_datation($date);
	if($tableau != null)
	{
		print('  '.aff_tableau($tableau).': '.$date.'<br/>'."\n");
	}
	else
		print('(échec pour '.$date.')<br/>'."\n");
}

/*- Compatibilité ------------------------------------------------------------*/

static function mef($date)
{
	for($i = 6; --$i >= 0;)
		if($date[$i] < 0)
			$date[$i] = null;
	return $date;
}

static function fem($date)
{
	for($i = 6; --$i >= 0;)
		if(!isset($date[$i]))
			$date[$i] = -1;
	return $date;
}

/*- Fin ----------------------------------------------------------------------*/

}

?>
