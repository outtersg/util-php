<?php
/*
 * Copyright (c) 2007 Guillaume Outters
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

require_once 'chemin.inc';
require_once 'fichierzip.php';

/* Machin qui se comporte comme FichierZip, mais en créant directement
 * plutôt que d'inclure à une archive. */

class FichierIci extends FichierZip
{
	public $modef = false; // Masque de création des fichiers.
	public $moded = false; // Idem pour les dossiers.
	protected $ou;
	/* Variables relatives au fichier en cours d'inclusion. */
	protected $fichier;
	
	function FichierIci($ou)
	{
		$this->moded = false;
		$this->modef = false;
		$this->chemin = is_a($ou, 'chemin') ? $ou : new Chemin($ou);
		$this->chemin->creerDossier();
	}
	
	function ajouterBloc($octets)
	{
		if(!$this->fichier) return false;
		fwrite($this->fichier, $octets);
		return true;
	}
	
	function clore() { return true; }
	
	function enTeteFichier($nomFichier, $infos = null)
	{
		$nomFichier = $this->chemin->et($nomFichier);
		$nomFichier->creerDossier($this->moded);
		if($nomFichier->fichier !== null)
			if(!($this->fichier = @fopen($nomFichier->cheminComplet(), 'w', $this->modef === false ? 0700 : $this->modef))) return false;
		return true;
 	}
	
	function clotureFichier()
	{
		if($this->fichier)
		{
			fclose($this->fichier);
			$this->fichier = false;
		}
		
		return true;
	}
}

?>
