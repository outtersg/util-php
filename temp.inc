<?php
/*
 * Copyright (c) 2003-2004,2007,2009 Guillaume Outters
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

/* Renvoie un numéro de fichier pas encore utilisé dans le répertoire demandé. */
function temp_nouveau($cheminDossier, $suffixe)
{
	$numero = 0;
	if(!is_string($cheminDossier)) $cheminDossier = $cheminDossier->cheminDossier();
	while(file_exists($cheminDossier.'/'.($numero = dechex(rand(0, 0x8000000))).$suffixe)) {} /* BOGUE: il faudrait que l'on ait un appel atomique "créer un fichier et renvoyer une erreur s'il existe déjà". */
	fclose(fopen($cheminDossier.'/'.$numero.$suffixe, "w"));
	return $numero;
}

$GLOBALS['temp_renames'] = null;
function temp_finir_renames()
{
	$r = true;
	foreach($GLOBALS['temp_renames'] as $num => $d)
	{
		@unlink($d[1]);
		if(!rename($d[0], $d[1])) $r = false;
		unset($GLOBALS['temp_renames'][$num]);
	}
	return $r;
}

function posix_rename($source, $dest)
{
	if(!@rename($source, $dest))
	{
		if(!isset($GLOBALS['temp_renames']))
		{
			$GLOBALS['temp_renames'] = array();
			register_shutdown_function('temp_finir_renames'); // Ça nous assure un semblant d'atomicité. Enfin je crois.
		}
		$GLOBALS['temp_renames'][] = array($source, $dest);
		return temp_finir_renames();
	}
	return true;
}

class Remplacant
{
	/* Renvoie un objet Temp, dont les membres sont les fichiers préouverts
	 * pour: l: le chemin demandé, et e: le fichier appelé à le remplacer.
	 * Renvoie false si le verrou n'a pas pu être obtenu (un autre programme
	 * est passé par là, et n'a pas relâché). */
	static function &Verrouille($chemin, $mode = 0770, $dureeTentativesEnDixiemesDeSecondes = 10)
	{
		$r = new Remplacant();
		$r->mode = $mode;
		if(class_exists('Chemin'))
		{
			$r->chemin = new Chemin(getcwd().'/'); // Il nous faut une référence stable pour si on change de répertoire courant.
			$r->chemin = $r->chemin->et($chemin)->absolu()->cheminComplet();
		}
		else
			$r->chemin = $chemin;
		register_shutdown_function(array(&$r, 'annuler'));
		$umask = umask(0777 - $mode);
		while(!($r->e = @fopen($r->chemin.'.verrou', 'x+')) && --$dureeTentativesEnDixiemesDeSecondes > 0)
			usleep(100000);
		umask($umask);
		if(!$r->e) return $r->e;
		$r->l = @fopen($r->chemin, 'r');
		return $r;
	}
	
	/* Termine la transaction demandée dans Verrouille(), en effectuant le
	 * remplacement. Si demandé, sauvegarde l'ancien fichier sous un nom avec
	 * suffixe numérique.
	 * Paramètres:
	 *   $sauvegarde
	 *     Créer une sauvegrde de l'ancien fichier, à côté de celui qu'on va
	 *     remplacer. Mentionner un md5 ici indique de ne sauvegarder que si le
	 *     fichier n'a pas ce md5 (ex.: si l'on fait plusieurs modifications
	 *     successives, on retiendra entre chacune le md5 du fichier juste
	 *     écrit; de cette façon, à moins qu'un processus tierce ne le modifie,
	 *     on ne sauvegardera pas nos étapes).
	 *   $garderLaMain
	 *     En fait, conserver le verrou et se préparer à réécrire le fichier
	 *     dans pas longtemps (il faudra donc appeler un vrai remplacer un de
	 *     ces jours).
	 * Retour:
	 *     si $sauvegarde était une chaîne (md5 de l'ancien fichier), md5 du
	 *     nouveau fichier écrit; sinon true ou false.
	 */
	function remplacer($sauvegarde = false, $garderLaMain = false)
	{
		$retour = is_bool($sauvegarde);
		if(!is_bool($sauvegarde)) // Un md5, on regarde ce qu'on a de beau dans le fichier pour décider de le sauvegarder ou non.
			$sauvegarde = isset($this->dejaSauvegarde) ? false : (md5(stream_get_contents($this->l, -1, 0)) != $sauvegarde);
		if($sauvegarde && !isset($this->dejaSauvegarde))
		{
			$umask = umask(0777 - $this->mode);
			for($n = 0; ++$n < 0x1000 && !($sauvegarde = @fopen($this->chemin.'.'.$n, 'x+'));) {}
			umask($umask);
			if($sauvegarde) // Sinon tant pis, plus de place.
			{
				fclose($sauvegarde);
				copy($this->chemin, $this->chemin.'.'.$n);
			}
			$this->dejaSauvegarde = true;
		}
		if(!$retour)
			$retour = md5(stream_get_contents($this->e, -1, 0)); // On calcule maintenant la valeur du nouveau md5, parce qu'on a un descripteur de fichier ouvert dessus et c'est pratique. Certes, on aura fait ce calcul pour rien si finalement on renvoie false, mais ce n'est pas le cas nominal.
		if($garderLaMain)
		{
			if(!fflush($this->e)) return false;
			/* On remplace, mais sans lâcher notre verrou: on passe donc par à côté. */
			if(!($temp = tempnam(dirname($this->chemin), 'temp.'))) return false;
			if(!copy($this->chemin.'.verrou', $temp)) { @unlink($temp); return false; }
			if(!posix_rename($temp, $this->chemin)) return false;
			/* On se prépare à générer à nouveau le fichier. */
			if(!ftruncate($this->e, 0)) return false;
			if(!rewind($this->e)) return false;
			fclose($this->l);
			if(!($this->l = fopen($this->chemin, 'r'))) return false;
			return $retour;
		}
		@fclose($this->l);
		fclose($this->e);
		if(posix_rename($this->chemin.'.verrou', $this->chemin))
		{
			unset($this->dejaSauvegarde); // La prochaine itération aura à sauvegarder le fichier que l'on clôt maintenant, car on le considère suffisamment définitif pour le mériter.
			return $retour;
		}
		@unlink($this->chemin.'.verrou');
		return false;
	}
	
	function annuler()
	{
		@fclose($this->l);
		@fclose($this->e);
		@unlink($this->chemin.'.verrou');
	}
}

?>