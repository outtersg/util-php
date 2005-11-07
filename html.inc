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

/*- Structure ----------------------------------------------------------------*/

function html_enTete($xhtml = true)
{
	if($xhtml)
	{
		echo('<?xml version="1.0" encoding="UTF-8"?>'."\n");
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr">
<head>
<?php
	}
	else
	{
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<?php
	}
}

function html_meta($meta, $xhtml = true)
{
	echo '<'.$meta.($xhtml ? '/>' : '>'); // En HTML 4, cette balise n'a pas le droit d'être fermée.
}

function html_corps($complement = null)
{
	echo '</head>'."\n".($complement ? '<body '.$complement.'>' : '<body>')."\n";
}

function html_fin()
{
?>
</body>
</html>
<?php
}

/*- Chaînes ------------------------------------------------------------------*/

/* Renvoie la version utilisable en JavaScript d'une chaîne.
 * Le code JavaScript doit commencer par des balises de commentaire XML. */
function html_chaineJS($chaine)
{
	/* Une sorte de consensus sur le non-respect de la norme XML semble s'être
	 * établi: bien que la norme dise que les caractères & et < ne peuvent
	 * apparaître que dans des commentaires, sections CDATA et directives <?,
	 * aussi bien Safari que Firefox n'en ont rien à foutre et acceptent
	 * gaillardement ces caractères dans des bouts de JS. Le validator W3C, lui,
	 * n'a pas trop l'air au courant, donc il se contente d'émettre un warning.
	 * La solution CDATA affole complètement les deux navigateurs, reste donc la
	 * bonne vieille méthode de commencer toutes ses sections JS par des
	 * commentaires. */
	return strtr($chaine, array(/*'&' => '&amp;', '<' => '&lt;', '>' => '&gt;',*/ '\'' => '\\\'', '\\' => '\\\\'));
}

/*- Stockage -----------------------------------------------------------------*/

function html_session()
{
	/* Stockage de session par cookies? Et comment vous faites pour vous loguer
	 * sous deux comptes différents dans le même navigateur? */
	
	try
	{
		@ini_set("session.use_cookies", false);
		@ini_set("session.use_trans_sid", true);
	} catch(Exception $e) {}
	session_start();
}

?>
