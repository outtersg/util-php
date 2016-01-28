<?php
/*
 * Copyright (c) 2015-2016 Guillaume Outters
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

namespace eu_outters_guillaume\Util\Graphe;

class Feuillu
{
	public function __construct()
	{
		if(!isset(self::$Rien))
			self::$Rien = new \stdClass; // L'important est que ce soit un objet unique pour lequel on soit sûr du ===.
	}
	
	public function diff($a, $b)
	{
		return $this->diffAjoutSuppression($a, $b, self::$Rien, self::$Rien);
	}
	
	public function diffAjoutSuppression($a, $b, $ajout, $suppression)
	{
		$ta = is_array($a);
		$tb = is_array($b);
		
		$simple = !$ta && !$tb;
		
		$t0 = array
		(
			$ta ? $a : (isset($a) ? array($a) : array()),
			$tb ? $b : (isset($b) ? array($b) : array()),
		);
		
		// D'un côté les objets, d'un autre le reste.
		
		$t1 = array
		(
			array
			(
				'o' => array(),
				's' => array(),
			),
			array
			(
				'o' => array(),
				's' => array(),
			),
		);
		foreach($t0 as $numTableau => $tableau)
			foreach($tableau as $clé => $valeur)
				if(is_object($valeur))
					$t1[$numTableau]['o'][spl_object_hash($valeur)] = $valeur;
				else
					$t1[$numTableau]['s'][$clé] = $valeur;
		
		if($ajout !== self::$Rien)
			if(is_object($ajout))
				$t1[1]['o'][spl_object_hash($ajout)] = $ajout;
			else if(array_search($ajout, $t1[1]['s']) === false)
				$t1[1]['s'][] = $ajout;
		if($suppression !== self::$Rien)
			if(is_object($suppression))
				unset($t1[1]['o'][spl_object_hash($suppression)]);
			else if(($clé = array_search($suppression, $t1[1]['s'])) !== false)
				unset($t1[1]['s'][$clé]);
		
		if(count($t1[1]['o']) && count($t1[1]['s']))
			throw new \Exception('Incohérence: un tableau doit contenir soit des objets, soit des scalaires, pas les deux');
		
		$valeurFinale = $t1[1][count($t1[1]['o']) ? 'o' : 's'];
		if($simple)
			$valeurFinale = array_shift($valeurFinale); // Donc null si le tableau est vide, ce qui nous arrange.
		
		return array
		(
			'r' => $valeurFinale,
			'-' => array
			(
				'o' => array_diff_key($t1[0]['o'], $t1[1]['o']),
				's' => array_diff_assoc($t1[0]['s'], $t1[1]['s']),
			),
			'+' => array
			(
				'o' => array_diff_key($t1[1]['o'], $t1[0]['o']),
				's' => array_diff_assoc($t1[1]['s'], $t1[0]['s']),
			),
		);
	}
	
	public function diffAjout($àQuoi, $quoi)
	{
		return $this->diffAjoutSuppression($àQuoi, $àQuoi, $quoi, self::$Rien);
	}
	
	public function diffSuppression($deQuoi, $quoi)
	{
		return $this->diffAjoutSuppression($deQuoi, $deQuoi, self::$Rien, $quoi);
	}
	
	/*- Débogage -------------------------------------------------------------*/
	
	public function aff($o, $filtre = null)
	{
		$r = array();
		$déjà = array();
		$ro = $this->_aff($o, /*&*/ $r, /*&*/ $déjà, $filtre ? $filtre : (isset($this->filtre) ? $this->filtre : null));
		return is_object($o) ? $r : array($ro, $r);
	}
	
	protected function _aff($o, & $liste, & $déjà, $filtre = null)
	{
		if(is_object($o))
		{
			$ro = new \ReflectionObject($o);
			$t = $ro->getProperties();
		}
		else
			$t = $o;
		// Évitons la récursivité avant d'appeler nos fils.
		if(is_object($o))
		{
			if(($faireFigurer = !$filtre || call_user_func($filtre, $o)))
				$liste[get_class($o)][spl_object_hash($o)] = null;
			$déjà[spl_object_hash($o)] = true;
		}
		
		$aff = array();
		foreach($t as $clé => $val)
		{
			if(is_object($o))
			{
				$clé = $val->getName();
				$val->setAccessible(true);
				$val = $val->getValue($o);
			}
			if(is_object($val))
			{
				if(!isset($déjà[spl_object_hash($val)]))
					$this->_aff($val, /*&*/ $liste, /*&*/ $déjà, $filtre);
				$val = spl_object_hash($val).(method_exists($val, '__toString') ? ' ['.$val.']' : '');
			}
			else if(is_array($val))
				$val = $this->_aff($val, /*&*/ $liste, /*&*/ $déjà, $filtre);
			$aff[$clé] = $val;
		}
		
		if(is_object($o) && $faireFigurer)
			$liste[get_class($o)][spl_object_hash($o)] = $aff;
		
		return $aff;
	}
	
	public static $Rien;
}

?>
