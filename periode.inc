<?php
/*
 * Copyright (c) 2005,2008,2010,2013 Guillaume Outters
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

/*- Affichage ----------------------------------------------------------------*/

function periode_aff($entre, $et, $mode = 0) { return Periode::aff(Date::mef($entre), Date::mef($et), $mode); }

function periode_affDate($date, $mode = 0, $tableau = false)
{
	return $GLOBALS['dateur']->aff($date, $mode, $tableau);
}

/* À FAIRE: cette classe devra finir par disparaître. Elle est juste là pour envelopper le code historique (histoire que le versionnage ne le voie pas disparaître). Les modifications de code devront être déportées à la classe Dateur, jusqu'à ce qu'il n'y ait plus de code ici. */
class Dateur0
{
	public function aff($date, $mode = 0, $tableau = false)
	{
	$total = null;
	$lettres = !($mode & Periode::$CHIFFRES);
	$sepDate = $lettres ? ' ' : '/';
	$mois = array('hors du temps', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
	if($date[0] >= 0 || $date[1] >= 0 || $date[2] >= 0)
	{
		$p = $mode == 2 ? 2 : 4; // Précision sur l'année. Bon, ça ne marche pas, il faudrait être plus tordu pour récupérer vraiment les deux derniers chiffres.
		if($date[0] >= 0) $total = sprintf("%0{$p}.{$p}d", $date[0]);
		if($lettres)
		{
			if($date[1] >= 0) $total = sprintf('%s%s', $mois[$date[1]], $total === null ? '' : $sepDate).$total;
			if($date[2] >= 0) $total = sprintf('%d%s%s', $date[2], $date[2] == 1 ? 'er' : '', $total === null ? '' : $sepDate).$total;
		}
		else
		{
			if($date[1] >= 0) $total = sprintf('%02.2d%s', $date[1], $total === null ? '' : $sepDate).$total;
			if($date[2] >= 0) $total = sprintf('%02.2d%s', $date[2], $total === null ? '' : $sepDate).$total;
		}
	}
	
	/* Trois conditions à l'écriture de l'heure en abrégé, pour qu'il n'y ait
	 * pas d'ambiguïté::
	 * - contiguïté des champs
	 * - plus d'un champ, sans quoi pas de séparateur, qui distingue l'heure du
	 *   jour
	 * - on commence au premier champ
	 */
	if(!$lettres)
	{
		for($i = 2; ++$i < 6 && $date[$i] >= 0;) {}
		for($j = $i; ++$j < 6;)
			if($date[$j] >= 0) // Encore des champs après, donc en $i on a un trou.
				$lettres = true; // Ça c'est le terme anglais qui se lit « trou ».
		if($i == 4) // Seul un champ renseigné, or on veut un séparateur.
			$lettres = true;
	}
	if(!$lettres) // Est-on passé à travers tous les tests sans perdre notre qualité de !$lettres?
	{
		$hms = '';
		while(--$i >= 3)
			$hms = ($i == 3 ? '' : ':').sprintf("%02.2d", $date[$i]).$hms;
	}
	else
	{
	$hms = '';
	$precedent = false; // Pour la simplification d'écriture, dans certains cas on permettra à deux nombres successifs de se partager l'unité du premier comme séparateur; on économise alors l'unité du second.
	for($i = 3; $i < 6; ++$i)
	{
		$lAutre = $precedent;
		$precedent = false;
		if($date[$i] >= 0 && $date[$i] !== null)
		{
				$hms .= $lAutre ? sprintf("%02.2d", $date[$i]) : ($hms ? ' ' : '').$date[$i];
			if(!$lAutre)
			{
					$hms .= Periode::$UNITES[$i];
				$precedent = true;
			}
		}
	}
	}
	
	$res = array('date' => $total);
	if($hms)
	{
		if($total)
			$res[] = ' ';
		$res[] = ($mode & Periode::$LE) ? 'à ' : '';
		$res['heure'] = $hms;
	}
	
	if(($mode & Periode::$LE) && $date[2] >= 0)
	{
		if ($date[1] < 0 && $date[0] < 0)
		{
			$date = localtime(mktime(0, 0, 0, date('n'), $date[2]));
			$jours = array('dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi');
			array_unshift($res, $jours[$date[6]].' ');
		}
		else
			array_unshift($res, 'le ');
	}
	return $tableau ? $res : implode('', $res);
	}
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

/**
 * @param $ratioRecouvrement Combien une "grosse" unité recouvre de sa période réelle (ex.: si ce paramètre vaut 1/3, un juillet - août 2012 ira grosso modo du 20 juillet au 10 août, le rendant compatible avec un août - septembre ou même un 15 - 31 août. Par contre juillet - août 2012 sera considéré comme chevauchant une période du 8 au 20 août).
 */
function periode_seChevauchent($periodes0, $periodes1, $ratioRecouvrement = 0.3)
{
	foreach($periodes0 as $p0)
		foreach($periodes1 as $p1)
			if(comparer_datations($p0[0], $p1[1], true, $ratioRecouvrement) <= 0 && comparer_datations($p1[0], $p0[1], true, $ratioRecouvrement) <= 0)
				return true;
	return false;
}

class Periode
{
	static public $ECRIT = 0;
	static public $NUMERIQUE = 1;
	static public $COURT = 2;
	static public $REDIGE = 3;
	
	static public $DE_A = 0x04; // "de x à y", plutôt que "x - y".
	static public $LE = 0x08; // "le x à y h", plutôt que "x y h".
	static public $CHIFFRES = 0x10; // "le 12/01", plutôt que "le 12 janvier".
	static public $JOUR_INSECABLE = 0x20; // "du 12/05/2003 au 23/05/2003", plutôt que "du 12 au 23/05/2003".
	
	static public $UNITES = array(null, null, null, 'h', 'mn', 's');
	
	static function aff($entre, $et, $mode = 0, $tableau = false)
	{
		switch($mode)
		{
			case Periode::$ECRIT: $mode = Periode::$DE_A; break;
			case Periode::$NUMERIQUE: $mode = Periode::$CHIFFRES; break;
			case Periode::$COURT: $mode = Periode::$DE_A | Periode::$CHIFFRES; break;
			case Periode::$REDIGE: $mode = Periode::$DE_A | Periode::$LE; break;
		}
		$b2 = $b1 = ''; // Blocs présents dans les dates; une fois déterminés, on tentera de les réduire pour en afficher le minimum.
		
		/* Détermination de ce qu'on a en commun. */
		
		for($i = 0, $premierDef = -1; $i < 6 && ($entre[$i] == $et[$i] || $et[$i] === null); ++$i) // $i, on aurait pu l'appeler $premierDif, car en fin de boucle ce sera le numéro du premier champ différant entre entre et et. Mais à écrire, c'est long.
			if(($et[$i] = $entre[$i]) !== null && $premierDef == -1) // Affectation au cas où il est indéfini.
				$premierDef = $i;
		if($premierDef < 0 && $i < 6)
			$premierDef = $i;
		for($dernierDef = 6; --$dernierDef > $premierDef && $entre[$dernierDef] === null && $et[$dernierDef] === null;) {}
		
		/* Exception pour les fêtards: la nuit est considérée comme une seule journée. */
		
		if($i <= 2 && $et[$i] == $entre[$i] + 1) // Si on s'est arrêté au jour ou sur un champ précédent (ex.: pour la nuit de la saint Sylvestre, c'est dès l'année que les deux divergent), et que sur ce champ on a une différence d'1.
		{
			if(Date::calculer($et) - Date::calculer($entre) < 3600 * 23) // À consommer dans les 24 h, moins une heure de marge pour l'éventualité du changement d'heure et parce qu'il faut rester raisonnable quand on fait nuit blanche. Et on se fiche d'une différence négative, parce que nos périodes ne sont pas censées aller à rebrousse-temps.
				$i = 3;
		}
		
		/* Choix d'un regroupement de champs. On tente de factoriser. Cependant on
		 * s'interdit (en français) de couper un bloc jma par de l'hms. De ce fait,
		 * si l'on peut décomposer les dates en trois blocs S (partie spécifique du
		 * jma), C (partie commune entre entre et et) et H (hma), on a droit aux
		 * combinaisons S1 - S2 C et S1 C H1 - S2 H2, mais pas S1 H1 - S2 H2 C. Et
		 * S1 H1 S2 C H2 paraît peu naturel. */
		
		$reducs = array('SC.SC' => 'S.SC', 'Cs.Cs' => 'C_s.s', 'SCs.SCs' => 'SCs.Ss', 'Cc.Cc' => 'Cc.', 'C.C' => 'C.');
		if($mode & Periode::$JOUR_INSECABLE)
			$reducs = array('Cs.Cs' => 'C_s.s', 'Cc.Cc' => 'Cc.', 'C.C' => 'C.');
		
		if($premierDef < 3)
		{
			if($i > $premierDef)
				$b2 = $b1 = 'C';
			if($i < 3)
				$b2 = $b1 = 'S'.$b1;
		}
		if($dernierDef >= 3)
			$b2 = $b1 = $b1.($i < 6 ? 's' : 'c'); // Du moment qu'un élément de l'heure diffère, c'est l'heure en bloc que l'on affichera.
		
		if(array_key_exists($b1 .= '.'.$b2, $reducs))
			$b1 = $reducs[$b1];
		
		$b = explode('.', strtr($b1, array('c' => 's', 'SCs' => 'T', 'SC' => 'J'))); // Simplification pour plus tard. T comme totale, J comme jour.
		
		/* Affichage. */
		
		$blocs = array();
		$types = array();
		$nDates = 0;
		foreach(array($entre, $et) as $n => $d)
			if(($b1 = $b[$n]))
			{
				if(strpos($b1, '_') === false)
					$b1 = '_'.$b1;
				for($z = 0; $z < strlen($b1); ++$z)
				{
					switch($b1{$z})
					{
						case '_':
							$blocs[] = ($mode & Periode::$LE) ? ($nDates ? '_ à ' : '_ de ') : ($nDates ? '_ - ' : '');
							$types[] = 0;
							continue 2;
						case 'T': $entre = 0; $et = 5; break;
						case 'J': $entre = 0; $et = 2; break;
						case 'C': $entre = 0; $et = $i < 3 ? $i - 1 : 2; break;
						case 'S': $entre = $i; $et = 2; break;
						case 's': $entre = 3; $et = 5; break;
					}
					$d2 = array(null, null, null, null, null, null);
					for(; $entre <= $et; ++$entre)
						$d2[$entre] = $d[$entre];
					$elements = Periode::affDate($d2, $mode, true);
					$blocs = array_merge($blocs, array_values($elements));
					$types = array_merge($types, array_keys($elements));
					$blocs[] = ' ';
					$types[] = 0;
				}
				++$nDates;
			}
		foreach($blocs as $n => $b)
			$blocs[$n] = strtr($b, $nDates == 1 ? array('_ à ' => '', '_ de ' => '', '_ - ' => '') : array('_' => ''));
		// Suppression des blocs vides.
		for($i = count($types); --$i >= 0;)
		{
			if(!strlen($blocs[$i]))
			{
				array_splice($blocs, $i, 1);
				array_splice($types, $i, 1);
			}
		}
		// Concaténation des blocs textuels.
		for($i = count($types); --$i >= 0;)
		{
			if(is_int($types[$i])) // Indique que le bloc est un texte de liaison entre deux éléments un peu plus significatifs.
			{
				for($j = $i; --$i >= 0 && is_int($types[$i]);) {}
				++$i;
				if($i < $j)
				{
					array_splice($blocs, $i, $j - $i + 1, array(Periode::tasser(implode('', array_slice($blocs, $i, $j - $i + 1)), $mode)));
					array_splice($types, $i, $j - $i);
				}
			}
		}
		// Trim.
		if(($n = count($blocs)))
		{
			if(trim($blocs[--$n]) == '')
			{
				unset($blocs[$n]);
				unset($types[$n]);
			}
			if(trim($blocs[0]) == '')
			{
				array_shift($blocs);
				array_shift($types);
			}
			else
				$blocs[0] = ltrim($blocs[0]);
		}
		return $tableau ? array($blocs, $types) : implode('', $blocs);
	}
	
	static function tasser($aff, $mode)
	{
		if($mode & Periode::$LE)
			$aff = strtr($aff, array('  ' => ' ', 'de le' => 'du', 'à à' => 'à', 'à le' => 'au', 'de à' => 'de'));
		else
			$aff = strtr($aff, array('- -' => '-', '  ' => ' '));
		return $aff;
	}
	
	static function affDate($d, $mode, $tableau = false)
	{
		return periode_affDate(Date::fem($d), $mode, $tableau);
	}
	
	static function testsAff()
	{
		$tests = array
		(
			'10/10 08 h 30', '16/10 16:20',
			'31/12/2007 22 h 30', '1/1/2008 4 h',
			'20/3/2008 19:30', '21 0 h',
			'11/3/2008', '8/4/2008',
			'13/04/2008 21:26', '13/04/2008 21:26',
			'13/04/2008 21:27', '21:27',
		);
		for($i = count($tests); ($i -= 2) >= 0;)
		{
			$a = Date::lire($tests[$i]);
			$b = Date::lire($tests[$i + 1]);
			echo Date::aff_tableau($a).Date::aff_tableau($b).' -> '.Periode::aff($a, $b, Periode::$REDIGE)."\n";
		}
	}
	
/* Calcule la durée d'une période. L'unité est le plus petit champ rempli à la
 * fois dans $debut et $fin. Fonctionne en décrémentant, donc ça peut être
 * long. $fin exclus. */
	static function duree($debut, $fin, $numChampUnite = false)
{
	/* À FAIRE: une version qui sache utiliser des raccourcis, ex.: 3600 s dans
	 * 1 h, 24 h dans un jour sauf au changement d'heure, etc. */
	if($numChampUnite === false)
	{
		for($numChampUnite = 6; --$numChampUnite >= 0 && ($debut[$numChampUnite] === null || $fin[$numChampUnite] === null);) {}
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
static function descendreA(&$date, $numChamp, $valeur, $numChampUnite)
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
	
	public static function unionSi($périodes, $sEffleurentSinonSeChevauchent = true)
	{
		if(count($périodes) <= 1)
			return $périodes;
		
		$r = array(array_shift($périodes));
		while(count($périodes))
		{
			$nouvellePourComparaison = $nouvelle = array_shift($périodes);
			if($sEffleurentSinonSeChevauchent)
			{
				$nouvellePourComparaison[0] = Date::dernierChampPlus($nouvellePourComparaison[0], -1);
				$nouvellePourComparaison[1] = Date::dernierChampPlus($nouvellePourComparaison[1], 1);
			}
			// Où place-t-on notre départ?
			for($i = 0; $i < count($r); ++$i)
				if(Date::comparer($nouvellePourComparaison[0], $r[$i][0]) < 0)
					break;
			// Voyons si on touche notre prédécesseur.
			if($i > 0 && Date::comparer($nouvellePourComparaison[0], $r[$i - 1][1]) <= 0) // Chevauchement! On fusionne.
			{
				--$i;
				if(Date::comparer($nouvelle[0], $r[$i][0]) < 0)
					$r[$i][0] = $nouvelle[0];
				if(Date::comparer($nouvelle[1], $r[$i][1]) > 0)
					$r[$i][1] = $nouvelle[1];
			}
			else
				array_splice($r, $i, 0, array($nouvelle));
			// Puis on essaie de manger le maximum de nos successeurs.
			for($j = $i; ++$j < count($r);)
				if(Date::comparer($nouvellePourComparaison[1], $r[$j][0]) >= 0) // On touche!
				{
					if(Date::comparer($nouvelle[1], $r[$j][1]) < 0)
					{
						$r[$i][1] = $r[$j][1];
						array_splice($r, $j, 1);
						break; // Et on n'ira pas plus loin.
					}
					array_splice($r, $j, 1); // Mangé tout cru: on l'englobe.
				}
		}
		
		return $r;
	}
}

class Dateur extends Dateur0
{
}

$GLOBALS['dateur'] = new Dateur;
