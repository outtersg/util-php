<?php
/*
 * Copyright (c) 2003-2004,2007 Guillaume Outters
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

class Remplacant
{
	/* Renvoie un objet Temp, dont les membres sont les fichiers préouverts
	 * pour: l: le chemin demandé, et e: le fichier appelé à le remplacer.
	 * Renvoie false si le verrou n'a pas pu être obtenu (un autre programme
	 * est passé par là, et n'a pas relâché). */
	function &Verrouille($chemin)
	{
		$r = new Remplacant();
		if(class_exists('Chemin'))
		{
			$r->chemin = new Chemin(getcwd().'/'); // Il nous faut une référence stable pour si on change de répertoire courant.
			$r->chemin = $r->chemin->et($chemin)->absolu()->cheminComplet();
		}
		else
			$r->chemin = $chemin;
		register_shutdown_function(array(&$r, 'annuler'));
		for($n = 10; !($r->e = @fopen($r->chemin.'.verrou', 'x+')) && --$n > 0;)
			usleep(1000);
		if(!$r->e) return false;
		$r->l = fopen($r->chemin, 'r');
		return $r;
	}
	
	/* Termine la transaction demandée dans Verrouille(), en effectuant le
	 * remplacement. Si demandé, sauvegarde l'ancien fichier sous un nom avec
	 * suffixe numérique. */
	function remplacer($sauvegarde = false)
	{
		if($sauvegarde)
		{
			for($n = 0; ++$n < 0x1000 && !($sauvegarde = @fopen($this->chemin.'.'.$n, 'x+'));) {}
			if($sauvegarde) // Sinon tant pis, plus de place.
			{
				fclose($sauvegarde);
				copy($this->chemin, $this->chemin.'.'.$n);
			}
		}
		fclose($this->l);
		fclose($this->e);
		if(@rename($this->chemin.'.verrou', $this->chemin))
			return true;
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