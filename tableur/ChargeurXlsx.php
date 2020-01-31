<?php
/*
 * Copyright (c) 2018,2020 Guillaume Outters
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


class ChargeurXlsx extends Chargeur
{
	public function lire($chemin)
	{
		$this->d = new Décompresseur($chemin);
		
		$compoStyles = new CompoStyles();
		$compoChaînes = new CompoChaînes();
		
		$this->charger('-', 'styleSheet', $compoStyles, false, $f = $this->d->styles());
		fclose($f);
		
		$this->charger('-', 'sst', $compoChaînes, false, $f = $this->d->chaînes());
		fclose($f);
		
		$this->formats = $compoStyles->formats;
		$this->chaînes = $compoChaînes->chaînes;
	}
	
	public function feuille($numFeuille)
	{
		if(!($f = $this->d->feuille($numFeuille))) return $f;
		$compoFeuille = new CompoFeuille();
		$this->charger('-', 'worksheet', $compoFeuille, false, $f);
		fclose($f);
		
		return $compoFeuille;
	}
}

?>
