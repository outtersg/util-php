<?php
/*
 * Copyright (c) 2003,2006 Guillaume Outters
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

/* Décompose un tableau de paramètres pour inclusion dans une page HTML. Le mode
 * peut valoir:
 * - 0: renvoi de paramètres pour une GET HTTP.
 * - 1: renvoi de commandes javascript pour ajouter des éléments à g_params
 *      (form global émettant en POST HTTP).
 * - 2: renvoi d'affectations javascript (les variables JS ont le même nom que
 *      celles PHP).
 * - 3: renvoi d'inputs HTML, à insérer directement dans la page.
 */
function params_decomposer($nom, $tableau, $mode = 0)
{
	if($mode == 2)
	{
		$premier = true;
		$retour = $nom === null ? '{ ' : ($nom.': { ');
		foreach($tableau as $cle => $valeur)
		{
			if($premier == true)
				$premier = false;
			else
				$retour .= ', ';
			if(is_array($valeur))
				$retour .= params_decomposer($cle, $valeur, $mode);
			else
				$retour .= $cle.": '".strtr($valeur, array("\r\n" => '\n', "\n" => '\n'))."'"; // À FAIRE: beaucoup plus de remplacements. */
		}
		$retour .= ' }';
	}
	else
	{
		$retour = '';
		foreach($tableau as $cle => $valeur)
		{
			$nomCompose = $nom === null ? $cle : $nom.'['.$cle.']';
			if(is_array($valeur))
				$retour .= params_decomposer($nomCompose, $valeur, $mode);
			else
			{
				switch($mode)
				{
					case 0:
					$retour .= '&'.$nomCompose.'='.$valeur;
					break;
					case 1:
					$retour .= "\n".'temp = document.createElement(\'input\');';
					$retour .= "\n".'temp.setAttribute(\'name\', \''.$nomCompose.'\');';
					$retour .= "\n".'temp.setAttribute(\'value\', \''.$valeur.'\');';
					$retour .= "\n".'g_params.appendElement(temp);';
					break;
					case 3:
						$retour .= "\n".'<input type="hidden" name="'.$nomCompose.'" value="'.$valeur.'"/>';
						break;
				}
			}
		}
	}
	return $retour;
}

function params_combiner($tableau1, $tableau2)
{
	foreach($tableau2 as $cle => $valeur)
	{
		if(is_array($valeur) && isset($tableau1[$cle]))
			$tableau1[$cle] = params_combiner($tableau1[$cle], $valeur);
		else
			$tableau1[$cle] = $valeur;
	}
}

function params_purgerReçusRécursivement(&$params)
{
	foreach(array_keys($params) as $cle)
	{
		if(is_array($params[$cle]))
			params_purgerReçusRécursivement(&$params[$cle]);
		else
			$params[$cle] = stripslashes($params[$cle]);
	}
}

/* Vire d'un tableau reçu en POST ou GET tous les antislashes que cette
 * putasserie de PHP, avec sa directive magic_quotes_gpc, ajoute pour les
 * script kiddies. Enfin, ça n'est pas la première fois (ni sans doute la
 * dernière) que j'imagine que ce langage a été conçu dans un fût de vodka. */
function params_purgerReçus(&$params)
{
	if(get_magic_quotes_gpc()) // Mais il y a vraiment des abrutis sur cette terre, pour introduire une fonctionnalité comme celle-là dans le seul but de faciliter la vie d'autres abrutis et faire ch… en même temps tous les développeurs dignes de ce nom! http://www.webmasterstop.com/63.html
		params_purgerReçusRécursivement(&$params);
}

?>