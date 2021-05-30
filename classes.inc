<?php
/*
 * Copyright (c) 2021 Guillaume Outters
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

class Classes
{
	public static function ClassesFichier($fichierPhp, $siQueDesClasses = true)
	{
		$classes = array();
		$niveau = 0;
		$niveauClasse = -1;
		$espace = '';
		$état = 0;
		foreach(token_get_all(file_get_contents($fichierPhp)) as $bout)
		{
			// Mots-clés structurels.
			switch($mc = is_string($bout) ? $bout : $bout[0])
			{
				case '{': ++$niveau; continue 2;
				case '}':
					if(--$niveau == $niveauClasse)
						$niveauClasse = -1;
					continue 2;
			}
			if($niveauClasse >= 0 && $niveau > $niveauClasse)
				continue;
			if(in_array($état, array(T_CLASS, T_NAMESPACE)) && $mc == T_STRING)
			{
				// À FAIRE: namespace.
				$classes[] = $bout[1];
				$état = 'jusque';
				$niveauClasse = $niveau;
			}
			else if($état == 'jusque' && in_array($mc, array(';', '{')))
				$état = 0;
			else if(!$état)
				switch($mc)
				{
					case T_CLASS:
					case T_NAMESPACE:
						$état = $mc;
						break;
					case T_OPEN_TAG:
					case T_WHITESPACE:
						break;
					case T_REQUIRE_ONCE:
					case T_REQUIRE:
					case T_INCLUDE_ONCE:
					case T_INCLUDE:
						$état = 'jusque';
						break;
					default:
						if($siQueDesClasses)
						{
							//fprintf(STDERR, "%s:%d: '%s' hors classes\n", $fichierPhp, is_array($bout) ? $bout[2] : '??', is_array($bout) ? $bout[1] : $bout);
							return;
						}
					// À FAIRE: namespace, et namespace à accolades.
					// À FAIRE: interface.
				}
		}
		
		return $classes;
	}
	
	public static function ClassesFichierSous($fichierPhp, $classeMère)
	{
		$r = array();
		
		$classes = Classes::ClassesFichier($fichierPhp, true);
		if(!$classes)
			return $r;
		
		require_once $fichierPhp;
		foreach($classes as $classe)
			if(is_a($classe, $classeMère, true))
				$r[] = $classe;
		
		return $r;
	}
}

?>
