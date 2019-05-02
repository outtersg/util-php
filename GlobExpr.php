<?php
/*
 * Copyright (c) 2013,2018-2019 Guillaume Outters
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

class GlobExpr
{
	public static function globEnExpr($globs)
	{
		if(is_array($globs))
			$globs = implode(chr(3), $globs);
		$globs = strtr($globs, array
		(
			'.' => '\.',
			'*' => '[^/]*',
			'(?' => '(?',
			'?' => '[^/]',
		));
		$globs = '(?:'.strtr($globs, chr(3), '|').')';
		return $globs;
	}
	
	public static function exprEnGlobs($expr)
	{
		// Réduction des . et *
		$étoile = chr(3);
		$point = chr(4);
		$interr = chr(5);
		$expr = strtr($expr, array
		(
			'\\\\' => '\\\\',
			'\\.' => '.',
			'\\*' => '?',
			'\\?' => '?',
			'.' => $point,
			'[^/]' => $point,
			'*' => $étoile,
			'?' => $interr,
		));
		$expr = preg_replace("#\($interr(:|P<[^>]*>)#", '(', $expr);
		$expr = preg_replace("#\)$interr#", '|)', $expr);
		$expr = preg_replace
		(
			array
			(
				"#\([^()/]*\)$étoile#",
				"#\[[^]]*\]$étoile#",
				"#$point$étoile#",
			),
			'*',
			$expr
		);
		// On convertit maintenant les expressions parenthésées.
		$globs = array($expr => true); // On indice par expression, ce qui permettra d'éliminer les doublons (ex.: a*|b* donnent en glob tous deux *: inutile de générer [ *, * ], même si c'est chouette (en ASCII art)).
		$duBoulot = true;
		while($duBoulot)
		{
			$duBoulot = false;
			$globs0 = $globs;
			$globs = array();
			foreach($globs0 as $glob => $trou)
				// Les parenthèses simples sont supprimées.
				if(($glob2 = preg_replace("#\(([^()|]*)\)([^$étoile$interr]|\$)#", '\1\2', $glob)) != $glob)
				{
					$globs[$glob2] = true;
					$duBoulot = true;
				}
				// Les x | y deviennent deux possibilités [ x, y ].
				else if(preg_match("#\([^()|]*(?:\|[^()|]*)+\)#", $glob, $r, PREG_OFFSET_CAPTURE))
				{
					$avant = substr($glob, 0, $r[0][1]);
					$après = substr($glob, $r[0][1] + strlen($r[0][0]));
					foreach(explode('|', substr($r[0][0], 1, -1)) as $alternative)
						$globs[$avant.$alternative.$après] = true;
					$duBoulot = true;
				}
				// (<sous-dossier>/)* n'est pas transformable en glob (nombre indéfini de niveaux).
				else if(($glob2 = preg_replace('#/([^()]*\))#', '[31m/[0m\1', $glob)) != $glob)
					throw new Exception('Impossible de convertir en glob un nombre indéfini de niveaux hiérarchiques "(sous-dossier/)*": '.$glob2);
				else
					$globs[$glob] = true;
		}
		// S'il reste des bouts qu'on n'a pas traités, ça n'est pas bon.
		foreach($globs as $glob)
			if(($glob2 = strtr($glob, array($étoile => '[31m*[0m', $point => '[31m.[0m', $interr => '[31m?[0m'))) != $glob)
				throw new Exception('Impossible de convertir en glob: '.$glob2);
		return $globs;
	}
}

?>
