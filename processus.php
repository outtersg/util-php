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


class Processus
{
	public function __construct($argv)
	{
		$prems = true;
		foreach($argv as & $arg)
			if($prems)
			{
				$arg = escapeshellcmd($arg);
				$prems = false;
			}
			else
				$arg = escapeshellarg($arg);
		
		$desc = array
		(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('pipe', 'w'),
		);
		$this->_tubes = array();
		$this->_fils = proc_open(implode(' ', $argv), $desc, $this->_tubes);
	}
	
	public function attendre()
	{
		fclose($this->_tubes[0]);
		
		$entrees = array();
		$sorties = array(1 => $this->_tubes[1], 2 => $this->_tubes[2]);
		$erreurs = array();
		
		while(count($sorties))
		{
			$sortiesModif = $sorties; // Copie de tableau.
			if($nFlux = stream_select($sortiesModif, $entrees, $erreurs, 1))
				foreach($sortiesModif as $flux)
				{
					foreach($sorties as $fd => $fluxSurveillé)
						if($fluxSurveillé == $flux)
							break;
					$bloc = fread($flux, 0x4000);
					$this->_sortie($fd, $bloc);
					if(strlen($bloc) == 0) // Fin de fichier, on arrête de le surveiller.
						unset($sorties[$fd]);
				}
		}
		
		fclose($this->_tubes[1]);
		fclose($this->_tubes[2]);
		$retour = proc_close($this->_fils);
		
		if($retour != 0)
			fprintf(STDERR, '# Le processus fils est sorti en erreur '.$retour."\n");
	}
	
	protected function _sortie($fd, $bloc)
	{
		fwrite($fd == 1 ? STDOUT : STDERR, $bloc);
	}
}

class ProcessusCauseur extends Processus
{
	public function __construct($argv)
	{
		$this->_contenuSortie = '';
		parent::__construct($argv);
	}
	
	protected function _sortie($fd, $bloc)
	{
		$this->_contenuSortie .= $bloc;
	}
	
	public function contenuSortie()
	{
		return $this->_contenuSortie;
	}
}

class ProcessusLignes extends Processus
{
	public function __construct($argv, $sorteurLignes = null)
	{
		$this->_sorteur = $sorteurLignes;
		parent::__construct($argv);
	}
	
	public function attendre()
	{
		foreach(array(1, 2) as $fd)
			$this->_contenuSorties[$fd] = null;
		$r = parent::attendre();
		foreach(array(1, 2) as $fd)
			if(isset($this->_contenuSorties[$fd]))
				$this->_sortie($fd, "\n");
		return $r;
	}
	
	protected function _sortie($fd, $bloc)
	{
		if(isset($this->_contenuSorties[$fd]))
			$bloc = $this->_contenuSorties[$fd].$bloc;
		$début = 0;
		while(($fin = strpos($bloc, "\n", $début)) !== false)
		{
			$this->_sortirLigne(substr($bloc, $début, $fin - $début), $fd);
			$début = $fin + 1;
		}
		$this->_contenuSorties[$fd] = $début < strlen($bloc) ? substr($bloc, $début) : null;
	}
	
	protected function _sortirLigne($ligne, $fd)
	{
		if(isset($this->_sorteur))
			call_user_func($this->_sorteur, $ligne, $fd);
	}
}

?>
