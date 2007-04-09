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

?>