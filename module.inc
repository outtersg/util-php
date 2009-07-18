<?php
/*
 * Copyright (c) 2003-2005 Guillaume Outters
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

/* Renvoie une liste de modules. Un module est un script PHP additionnel,
 * qui peut être inclus par l'appelant.
 * $cheminDossier: dossier devant contenir les modules.
 * $type: type de modules recherchés:
 *   0: tout fichier du répertoire, possédant le suffixe, est considéré comme un
 *      module.
 *   1: les modules sont les sous-répertoires contenant un fichier suffixé de
 *      même nom (par exemple: carotte est un modules dans /tmp/ s'il existe un
 *      /tmp/carotte/carotte.inc, pour le suffixe .inc).
 */
function module_liste($cheminDossier, $type, $suffixe = '.inc')
{
	if($cheminDossier instanceof Chemin) $cheminDossier = $cheminDossier->cheminComplet();
	
	$dossier = opendir($cheminDossier);
	$liste = array();
	if($type)
	{
		while(($nomRessource = readdir($dossier)))
			if(is_dir($cheminDossier.'/'.$nomRessource))
				if(is_file($cheminDossier.'/'.$nomRessource.'/'.$nomRessource.$suffixe))
					$liste[] = $nomRessource;
	}
	else
	{
		$tailleSuffixe = strlen($suffixe);
		while(($nomFichier = readdir($dossier)))
			if(($pos = strpos($nomFichier, $suffixe)) !== false && $pos == strlen($nomFichier) - $tailleSuffixe)
				$liste[] = substr($nomFichier, 0, $pos);
	}
	closedir($dossier);
	
	return $liste;
}
?>
