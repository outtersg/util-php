<?php
/*
 * Copyright (c) 2003,2005 Guillaume Outters
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
function  comparer_datations($d0, $d1) { return Date::comparer(Date::mef($d0), Date::mef($d1)); }
function  tester_decoupage_datation() { return Date::tester_decoupage_datation(); }
function  afficher_test_decoupage_datation($date) { return Date::afficher_test_decoupage_datation($date); }

class Date
{

/*- Débogage -----------------------------------------------------------------*/

function aff_tableau($tableau)
{
	$machin = '';
	for($i = 0; $i < count($tableau); ++$i)
		$machin .= (($i == 0 ? '[' : ', ').$tableau[$i]);
	$machin .= ']';
	return $machin;
}

function date_affichage_tableau($tableau)
{
	$masque = 0;
	
	/* Jour */
	
	$jour = '';
	for($i = 0; $i < 3; ++$i)
		if((($nombre = $tableau[$i]) >= 0) || ($i == 1 && $tableau[0] >= 0 && $tableau[2] >= 0))
		{
			if($nombre < 0) $nombre = 1;
			if($i == 0)
				$jour = sprintf('%04d', $nombre);
			else
				$jour .= sprintf('%s%02d', ($masque ? '-' : ''), $nombre);
			$masque = 2;
		}
	
	/* Heure */
	
	if(($tableau[3] >= 0) || ($tableau[4] >= 0) || ($tableau[5] >= 0))
	{
		if(($h = $tableau[3]) < 0) $h = 0;
		if(($m = $tableau[4]) < 0) $m = 0;
		$heure = sprintf('%02d:%02d', $h, $m);
		if(($i = $tableau[5]) >= 0)
			$heure .= sprintf(':%02d', $i);
		$masque |= 1;
	}
	
	return $jour.($masque == 3 ? ' ' : '').$heure;
}

function date_affichage($date)
{
	return strftime('%Y-%m-%d %H:%M:%S', $date);
}

/*- Découpage de dates et heures ---------------------------------------------*/

static $g_nomChamps = array('heures', 'minutes', 'secondes');
static $g_chiffres = '0123456789';

function lire($date)
{
	return Date::lireHeure($date, true);
}

function lireHeure($date, $tenterAussiLaDate)
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
		$valeurs[] = ($debut + 1 < $fin ? intval(substr($date, $debut + 1, $fin)) : false);
		
		/* Interprétation du séparateur */
		
		if($debut < 0)
			$suggestion2[] = 0;
		else if($c == ' ')
			$suggestion2[] = 0;
		else if($c == 'h')
		{
			$suggestion[] = 0;
			$suggestion2[] = 1;
		}
		else if($c == "'")
		{
			$suggestion[] = 1;
			$suggestion2[] = 2;
		}
		else if($c == '"')
			$suggestion[] = 2;
		else if($c != ':')
		{
			if($tenterAussiLaDate)
				return Date::assembler(Date::lireJour($date), array(false, false, false));
			//print('### délimiteur horaire non reconnu: \''.$c.'\' dans '.$date.'.<br/>');
			return null;
		}
		
		if(count($suggestion) < count($valeurs))
			$suggestion[] = -1;
		if(count($suggestion2) < count($valeurs) + 1)
			$suggestion2[] = -1;
		
		$fin = $debut;
		
		if($c == ' ') break;
	}
	
	/* Rangement de l'horaire dans les bonnes cases */
	
	$rangement = array(-1, -1, -1); // Indice dans $valeurs du machin qui doit aller dans la case correspondante; plus tard on y mettra la valeur elle-même, mais à chaque jour suffit sa peine.
	
	for($debut = count($valeurs); --$debut >= 0;) // Première passe: les champs dont on est sûr de la destination (le $suggestion correspondant a une valeur positive).
		if($valeurs[$debut] !== false && $suggestion[$debut] >= 0)
			if($rangement[$suggestion[$debut]] < 0)
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
		if($rangement[$j] < 0) // Pas trouvé à la première passe.
			for($debut = count($valeurs); --$debut >= 0;)
				if($valeurs[$debut] !== false && $suggestion2[$debut] == $j)
					if($rangement[$j] < 0)
						$rangement[$j] = $debut;
					else
					{
						//print('### le champ \''.Date::$g_nomChamps[$j].'\' apparaît plusieurs fois dans '.$date.'.<br/>');
						return null;
					}
	
	for($j = 0, $debut = count($valeurs); $j < count($rangement); ++$j) // Dernière passe: remplissage avec ce qui reste, dans l'ordre commun: heure-minute-seconde.
		if($rangement[$j] < 0) // Un truc restant à remplir.
		{
			while(--$debut >= 0 && ($valeurs[$debut] === false || $suggestion[$debut] >= 0 || $suggestion2[$debut] >= 0)) {} // Tant qu'on a des valeurs, invalides ou déjà exploitées, on remonte.
			if($debut < 0) break;
			$rangement[$j] = $valeurs[$debut]; // On stocke directement la valeur.
		}
		else
			$rangement[$j] = $valeurs[$rangement[$j]]; // On en profite pour faire le remplissage définitif, avec les valeurs.
	
	if($j < 2) // Un seul nombre ne nous suffit pas à l'accepter en tant qu'heure seule.
	{
		for($j = count($valeurs); --$j >= 0;)
			if($valeurs[$j] !== false)
				break;
		if($suggestion[$j] < 0) // Bon, si on a trouvé un marquage fort, on continue en considérant qu'il s'agit d'une heure.
		{
			if($tenterAussiLaDate)
				return Date::assembler(Date::lireJour($date), array(false, false, false));
			//print('### heure incomplète dans '.$date.' (il faut au moins les heures et minutes).<br/>');
			return null;
		}
	}
	
	/* Interprétation de la date */
	
	return ($tenterAussiLaDate ? Date::assembler($fin > 0 ? Date::lireJour(substr($date, 0, $fin)) : array(false, false, false), $rangement) : $rangement);
}

function lireJour($date)
{
	if(strlen($date) == 0) return array(false, false, false);
	
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
		while($fin < 3) { $valeurs[] = false; ++$fin; }
	else // Pas trouvé d'année, on complète par des false (ce qui nous donne: 1 élément: jour; 2: jour et mois, 3: ben tout).
	{
		for($debut = 3; --$fin >= 0;)
			$valeurs[--$debut] = $valeurs[$fin];
		while(--$debut >= 0)
			$valeurs[$debut] = false;
	}
	
	return $valeurs;
}

function assembler($tabDate, $tabHoraire)
{
	if($tabDate == null || $tabHoraire == null) return null;
	for($i = 0; $i < count($tabHoraire); ++$i)
		$tabDate[] = $tabHoraire[$i];
	
	return $tabDate;
}

/*- Calculs sur tableaux de dates --------------------------------------------*/

function completer($original, $avec)
{
	if($original == null) return $avec;
	if($avec == null) return $original;
	for($i = 6; --$i >= 0;)
		if($avec[$i] !== false)
			$original[$i] = $avec[$i];
	return $original;
}

function calculer($chose)
{
	/* Un mktime avec des false, ça ne va pas être terrible. */
	if($chose == null || in_array(false, $chose))
		$chose = Date::completer(array(1970, 1, 1, 0, 0, 0), $chose);
	return mktime($chose[3], $chose[4], $chose[5], $chose[1], $chose[2], $chose[0] >= 0 ? $chose[0] : 0);
}

function obtenir_datation($secondes)
{
	$t = getdate($secondes);
	return array($t['year'], $t['mon'], $t['mday'], $t['hours'], $t['minutes'], $t['seconds']);
}

/* Transforme les 32 janvier en 1er février, etc. */
function corriger($chose)
{
	/* calculer_datation remplaçant nos indéfinis, il faudra les remettre en
	 * place à posteriori. */
	$indefinis = array();
	foreach($chose as $i => $n)
		if($n === false)
			$indefinis[] = $i;
	$r = obtenir_datation(Date::calculer($chose));
	foreach($indefinis as $i)
		$r[$i] = false;
	return $r;
}

/* Renvoie un nombre inférieur, égal ou supérieur à 0 selon que le premier
 * paramètre est gnagnagna, gnagnagna ou gnagnagna blabla. Ne fonctionne pas
 * forcément si vous laissez trop de trous dans vos dates. */
function comparer($d0, $d1)
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
		if(($d0[$i] === false && $d1[$i] !== false) || ($d1[$i] === false && $d0[$i] !== false))
			return 0; // Vous êtes incomparables, toutes les deux.
		if($d0[$i] < $d1[$i]) return -1;
		if($d0[$i] > $d1[$i]) return 1;
	}
	return 0;
}

/*- Tests --------------------------------------------------------------------*/

function tester_decoupage_datation()
{
	$tests = array
	(
		'2003-08-30',
		'31/8 12:07',
		'8h00',
		'23',
		'2003-08-24 03:16:48',
		'23/08/2003 23:13:22',
	);
	for($y = count($tests); --$y >= 0;)
		afficher_test_decoupage_datation($tests[$y]);
}

function afficher_test_decoupage_datation($date)
{
	$tableau = decouper_datation($date);
	if($tableau != null)
	{
		print('  '.aff_tableau($tableau).': '.$date.'<br/>');
	}
	else
		print('(échec pour '.$date.')<br/>');
}

/*- Compatibilité ------------------------------------------------------------*/

function mef($date)
{
	for($i = 6; --$i >= 0;)
		if($date[$i] < 0)
			$date[$i] = false;
	return $date;
}

function fem($date)
{
	for($i = 6; --$i >= 0;)
		if($date[$i] === false)
			$date[$i] = -1;
	return $date;
}

/*- Fin ----------------------------------------------------------------------*/

}

?>
