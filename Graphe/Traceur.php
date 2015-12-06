<?php
/*
 * Copyright (c) 2015 Guillaume Outters
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

class Traceur
{
	public $trace = false;
	
	public function trace($quoi, $niveau = null)
	{
		if($this->trace)
		{
			$niveauLocal = isset($niveau) ? $niveau : $this->_niveau;
			fprintf(STDERR, $niveauLocal.strtr($quoi, array("\n" => "\n".$niveauLocal))."\n");
			if(!isset($niveau))
			{
				$trace = new Trace($this, $this->_niveau);
				$this->_niveau .= '  ';
				return $trace;
			}
		}
		
		return $this;
	}
	
	public function revenir($niveau)
	{
		$this->_niveau = $niveau;
	}
	
	public function clore($message = null)
	{
		// Implémentation vide, pour quand on se retourne soi-même comme trace.
	}
	
	protected $_niveau = '';
}

class Trace
{
	public $niveau;
	public $traceur;
	
	public function __construct(Traceur $traceur, $niveau)
	{
		$this->traceur = $traceur;
		$this->niveau = $niveau;
	}
	
	public function clore($message = null)
	{
		if(isset($message))
			$this->traceur->trace($message, $this->niveau.'  ');
		$this->traceur->revenir($this->niveau);
	}
}

?>
