<?php
/*
 * Copyright (c) 2009 Guillaume Outters
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

class SSem
{
	function SSem()
	{
		register_shutdown_function(array(&$this, 'sortir'));
	}
	
	/* S'attache à ou crée un sémaphore.
	 * Paramètres:
	 *   $chemin
	 *     Chemin d'un fichier existant, par exemple __FILE__ de l'appelant,
	 *     servant de référence.
	 *   $cle
	 *     Si nécessaire, clé pour distinguer diverses invocations depuis le
	 *     même fichier.
	 *   $provision
	 *     Nombre d'accès simultanés à la ressource.
	 *   $bloquant
	 *     Si non, la fonction renvoie 0 en cas d'indisponibilité immédiate.
	 *   $combien
	 *     Nombre de tickets voulus; 1 pour en réserver un, -1 pour en libérer.
	 *   $jeSuisUneRestau
	 *     Si true, l'appel est déjà une restauration; SSem n'installera pas de
	 *     quoi gérer un retour en arrière en cas d'erreur inopinée (puisque
	 *     justement nous sommes ce retour en arrière).
	 * Retour:
	 *   -1 en cas d'erreur, 0 si l'appelant ne veut pas bloquer mais n'obtient
	 *   pas le sémaphore, 1 si le sémaphore est acquis.
	 */
	function attendre($chemin, $cle = '', $provision = 1, $bloquant = true, $combien = 1, $jeSuisUneRestau = false)
	{
		/* Gros inconvénient du sémaphore à la PHP: pas moyen de faire un
		 * sem_trywait. À nous de l'émuler donc, avec une horreur de variable
		 * en mémoire partagée, qui nous indique le nombre de choses sur
		 * l'affaire, et donc si on bloque ou non. Évidemment l'accès à cette
		 * mémoire est lui-même protégé par un sémaphore (mais simple, celui-là,
		 * car il ne bloque que le temps d'aller consulter la mémoire partagée).
		 */
		/* 'restau' est là en cas de plantage entre le moment où on inscrit la
		 * nouvelle valeur et celui où on s'assure de retenir de combien on
		 * voudra revenir: tant qu'on a encore le sémaphore, on se sait seuls à
		 * manipuler, donc la restau marchera. Par contre une fois qu'on laisse
		 * d'autres threads jouer avec, la restauration devra passer par le
		 * recalcul (courante - ce qu'on avait ajouté). Sauf que cette chose, on
		 * ne sait pas l'écrire atomiquement. D'où la régence du restau. */
		if(!function_exists('sem_get') || !function_exists('shm_attach')) return 1; // Eh, que pouvons-nous faire de mieux?
		/* On a un sémaphore PHP rapide qui contrôle l'accès à la mémoire
		 * partagée dans laquelle une variable nous permet de gérer un bon
		 * vieux sémaphore comme on les aime. */
		/* À FAIRE?: utiliser en seconde étape un second sémaphore, plutôt que
		 * de poireauter par palliers d'1 s en attendant le relâchement de la
		 * variable partagée. */
		$id = ftok($chemin, $cle);
		if(($this->sems[$chemin][$cle]['sem'] = sem_get($id)) === false) return $this->finir($chemin, $cle);
		if(!($mem = $this->sems[$chemin][$cle]['mem'] = shm_attach($id, 128))) return $this->finir($chemin, $cle);
		while(true)
		{
			if(!$this->assurerSemInterne($chemin, $cle)) return $this->finir($chemin, $cle);
			if(($val = shm_get_var($mem, 0)) === false)
			{
				$this->sems[$chemin][$cle]['restau'] = $provision;
				shm_put_var($mem, 0, $provision - $combien);
				$jeSuisUneRestau || $this->sems[$chemin][$cle]['var'] = $combien;
				unset($this->sems[$chemin][$cle]['restau']);
				$this->finir($chemin, $cle, true);
				return 1;
			}
			else
			{
				if($val >= $combien)
				{
					$this->sems[$chemin][$cle]['restau'] = $jeSuisUneRestau ? $val - $combien : $val;
					if(shm_put_var($mem, 0, $val - $combien) === false) return $this->finir($chemin, $cle);
					if($jeSuisUneRestau)
						unset($this->sems[$chemin][$cle]['var']);
					else
					$this->sems[$chemin][$cle]['var'] = (isset($this->sems[$chemin][$cle]['var']) ? $this->sems[$chemin][$cle]['var'] : 0) + $combien;
					unset($this->sems[$chemin][$cle]['restau']);
					
					$this->finir($chemin, $cle, true);
					return 1;
				}
				if(!$bloquant)
				{
					$this->finir($chemin, $cle);
					return 0;
				}
			}
			$this->finir($chemin, $cle);
			sleep(1);
		}
	}
	
	function liberer($chemin, $cle = '', $combien = 1)
	{
		return $this->attendre($chemin, $cle, 1, true, - $combien, true);
	}
	
	function assurerSemInterne($chemin, $cle)
	{
		if(isset($this->sems[$chemin][$cle]['semacq'])) return true;
		
		if(sem_acquire($this->sems[$chemin][$cle]['sem']))
			return $this->sems[$chemin][$cle]['semacq'] = true;
		return false;
	}
	
	/* Ménage interne. */
	function finir($chemin, $cle, $maisGarder = false)
	{
		$infos = & $this->sems[$chemin][$cle];
		if(!$maisGarder && (isset($infos['var']) || isset($infos['restau'])))
		{
			$this->assurerSemInterne($chemin, $cle);
			shm_put_var($infos['mem'], 0, isset($infos['restau']) ? $infos['restau'] : shm_get_var($infos['mem'], 0) + $infos['var']);
			unset($infos['var']);
			unset($infos['restau']);
		}
		if(isset($infos['semacq']))
		{
			sem_release($infos['sem']);
			unset($infos['semacq']);
		}
		return -1;
	}
	
	/* Ménage interne. */
	function sortir()
	{
		foreach($this->sems as $chemin => $sems)
			foreach(array_keys($sems) as $cle)
				$this->finir($chemin, $cle);
	}
	
	function tenter($chemin, $cle = '', $provision = 1)
	{
		return SSem::attendre($chemin, $cle, $provision, false);
	}
}

?>
