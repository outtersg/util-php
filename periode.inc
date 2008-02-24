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

require_once('date.inc');

/* La période est un couple de dates en tableau. Cf. date.inc */

/* À FAIRE: possibilités de formatage plus élaborées (déjà, remplacer le - entre
 * deux dates par un « à »). */
/* À FAIRE: le truc super complexe que j'avais fait à CS pour Brit Air, qui
 * tenait compte des heures (si mes souvenirs sont bons, on avait une notion de
 * limite de compression qui se baladait: par exemple, 10/10/1979 à 12/10/1979
 * s'affichait « du 10 au 12/10/1979 », mais si une heure se rajoutait à ça on
 * rebasculait sur l'affichage de la date complète).
/*- Affichage ----------------------------------------------------------------*/

function periode_aff($entre, $et, $mode = 0)
{
	$entre2 = $entre;
	$aff = periode_affDate($et, $mode);
	for($i = 0; $i < 6; ++$i)
		if($entre2[$i] == $et[$i] || $entre2[$i] == -1)
			$entre2[$i] = -1;
		else
			break;
	if($i >= 6) return $aff; // Les deux dates sont strictement les mêmes.
	$aff = ($mode == Periode::$REDIGE ? 'de ' : '').periode_affDate($entre2, $mode).($mode == Periode::$REDIGE ? ' à ' : ' - ').$aff;
	if($mode == Periode::$REDIGE)
		$aff = strtr($aff, array('de le' => 'du', 'à le' => 'au'));
	return $aff;
}

function periode_affDate($date, $mode = 0)
{
	$total = null;
	$lettres = in_array($mode, array(Periode::$ECRIT, Periode::$REDIGE));
	$sepDate = $lettres ? ' ' : '/';
	$mois = array('hors du temps', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
	if($date[0] >= 0 || $date[1] >= 0 || $date[2] >= 0)
	{
		$p = $mode == 2 ? 2 : 4; // Précision sur l'année. Bon, ça ne marche pas, il faudrait être plus tordu pour récupérer vraiment les deux derniers chiffres.
		if($date[0] >= 0) $total = sprintf("%0{$p}.{$p}d", $date[0]);
		if($lettres)
		{
			if($date[1] >= 0) $total = sprintf('%s%s', $mois[$date[1]], $total === null ? '' : $sepDate).$total;
			if($date[2] >= 0) $total = sprintf('%d%s', $date[2], $total === null ? '' : $sepDate).$total;
		}
		else
		{
			if($date[1] >= 0) $total = sprintf('%02.2d%s', $date[1], $total === null ? '' : $sepDate).$total;
			if($date[2] >= 0) $total = sprintf('%02.2d%s', $date[2], $total === null ? '' : $sepDate).$total;
		}
	}
	/* À FAIRE: heures, minutes, secondes */
	
	if($mode == Periode::$REDIGE && $date[2] !== null)
		$total = 'le '.$total;
	return $total;
}

/*- Calcul -------------------------------------------------------------------*/

function periode_union($periodes)
{
	$res = array($periodes[0][0], $periodes[0][1]);
	
	foreach($periodes as $periode)
	{
		if(comparer_datations($periode[0], $res[0]) < 0)
			$res[0] = $periode[0];
		switch(comparer_datations($periode[1], $res[1]))
		{
			case 1: // Plus grand, comme il se doit.
			case -2: // null, donc période indéterminée, donc encore en cours.
				$res[1] = $periode[1];
		}
	}
	return $res;
}

class Periode
{
	static public $ECRIT = 0;
	static public $NUMERIQUE = 1;
	static public $COURT = 2;
	static public $REDIGE = 3;

/* Calcule la durée d'une période. L'unité est le plus petit champ rempli à la
 * fois dans $debut et $fin. Fonctionne en décrémentant, donc ça peut être
 * long. $fin exclus. */
function duree($debut, $fin, $numChampUnite = false)
{
	/* À FAIRE: une version qui sache utiliser des raccourcis, ex.: 3600 s dans
	 * 1 h, 24 h dans un jour sauf au changement d'heure, etc. */
	if($numChampUnite === false)
	{
		for($numChampUnite = 6; --$numChampUnite >= 0 && ($debut[$numChampUnite] === false || $fin[$numChampUnite] === false);) {}
		if($numChampUnite < 0)
			return false; // Rien à comparer!
	}
	$total = 0;
	for($numChamp = 0; $numChamp <= $numChampUnite; ++$numChamp)
		$total += Periode::descendreA($fin, $numChamp, $debut[$numChamp], $numChampUnite);
	return $total;
}

/* Renvoie le nombre d'unités dont aura été décrémentée $date pour que son
 * champs $numChamp ait baissé à $valeur. */
function descendreA(&$date, $numChamp, $valeur, $numChampUnite)
{
	$mini = array(0, 1, 1, 0, 0, 0); // Seuil de chacun des éléments.
	if($valeur == -1) $valeur = $mini[$numChamp] - 1;
	if($numChamp == $numChampUnite)
	{
		$total = $date[$numChamp] - $valeur;
		$date[$numChamp] = $valeur;
	}
	else
	{
		$total = 0;
		for($i = $date[$numChamp]; --$i >= $valeur;) // C'est mécanique, quand on est juste un au-dessus de la valeur voulue, et qu'on fait passer le champ suivant en dessous de son seuil, on décrémente notre champ. On utilise donc ce critère pour s'arrêter, et non tout bêtement « le champ a atteint la valeur désirée », car si celle-ci est en dessous du seuil, elle ne sera jamais atteinte (du fait des corriger()).
			$total += Periode::descendreA($date, $numChamp + 1, -1, $numChampUnite);
	}
	$date = Date::corriger($date);
	return $total;
}

}
