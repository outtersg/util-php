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
	 * Retour:
	 *   -1 en cas d'erreur, 0 si l'appelant ne veut pas bloquer mais n'obtient
	 *   pas le sémaphore, 1 si le sémaphore est acquis.
	 */
	function attendre($chemin, $cle = '', $provision = 1, $bloquant = true, $combien = 1)
	{
		/* Gros inconvénient du sémaphore à la PHP: pas moyen de faire un
		 * sem_trywait. À nous de l'émuler donc, avec une horreur de variable
		 * en mémoire partagée, qui nous indique le nombre de choses sur
		 * l'affaire, et donc si on bloque ou non. Évidemment l'accès à cette
		 * mémoire est lui-même protégé par un sémaphore (mais simple, celui-là,
		 * car il ne bloque que le temps d'aller consulter la mémoire partagée).
		 */
		if(!function_exists('sem_get') || !function_exists('shm_attach')) return 1; // Eh, que pouvons-nous faire de mieux?
		/* On a un sémaphore PHP rapide qui contrôle l'accès à la mémoire
		 * partagée dans laquelle une variable nous permet de gérer un bon
		 * vieux sémaphore comme on les aime. */
		/* À FAIRE?: utiliser en seconde étape un second sémaphore, plutôt que
		 * de poireauter par palliers d'1 s en attendant le relâchement de la
		 * variable partagée. */
		/* BOGUE: possibilités de dérapage aux endroits où l'on enregistre
		 * l'incrément apporté de façon non atomique (on fait le shm_put_var,
		 * puis on enregistre dans notre tableau la valeur: en cas de timeout
		 * pile entre les deux, on n'aura pas encore l'info nous permettant
		 * de libérer juste ce qu'il faut). */
		$id = ftok($chemin, $cle);
		while(true)
		{
			if(!($sem = $this->sems[$chemin][$cle]['sem'] = sem_get($id))) return $this->finir($chemin, $cle);
			if(!($mem = $this->sems[$chemin][$cle]['mem'] = shm_attach($id, 128))) return $this->finir($chemin, $cle);
			if(($val = shm_get_var($mem, 0)) === false)
			{
				shm_put_var($mem, 0, $provision - $combien);
				$this->sems[$chemin][$cle]['var'] = $combien;
				$this->finir($chemin, $cle, true);
				return 1;
			}
			else
			{
				if($val >= $combien)
				{
					if(shm_put_var($mem, 0, $val - $combien) === false) return $this->finir($chemin, $cle);
					$this->sems[$chemin][$cle]['var'] = (isset($this->sems[$chemin][$cle]['var']) ? $this->sems[$chemin][$cle]['var'] : 0) + $combien;
					
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
		return $this->attendre($chemin, $cle, 1, true, - $combien);
	}
	
	/* Ménage interne. */
	function finir($chemin, $cle, $maisGarder = false)
	{
		$infos = & $this->sems[$chemin][$cle];
		if(!$maisGarder && isset($infos['var']))
		{
			shm_put_var($infos['mem'], 0, shm_get_var($infos['mem'], 0) - $infos['var']);
			unset($infos['var']);
		}
		if(isset($infos['mem']))
		{
			shm_detach($infos['mem']);
			unset($infos['mem']);
		}
		if(isset($infos['sem']))
		{
			sem_release($infos['sem']);
			unset($infos['sem']);
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
