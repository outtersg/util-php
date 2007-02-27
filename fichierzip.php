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

require_once('crc.php');

/* Écriture de fichier Zip. La référence se trouve à
 * <http://www.pkware.com/products/enterprise/white_papers/appnote.html>.
 * Merci beaucoup:
 * - à PKWare pour avoir eu la brillante idée de faire du little-endian
 * - à l'équipe PHP pour avoir bien oublié de faire un CRC32 progressif */
/* À FAIRE:
 * - récupérer la date de modif des fichiers et en faire une date DOS pour les
 *   dates de modif.
 * - utiliser un algo de compression pour certains fichiers compressibles.
 */

class FichierZip
{
	public $infos = array();
	public $methode = 1; // 0: stored; 1: deflated à la main; voir l'appnote.iz d'Info-ZIP pour savoir ce qui est bien ou non. Si, avec un zip généré en mode 0, unzip sous Mac OS X passe sans problème, on constate que zip -T est beaucoup moins prêt à laisser passer.
	/* ATTENTION: PHP propose une méthode gzdeflate, qui donnerait le bon
	 * résultat mais demande un retravail puisqu'elle marque chacun des blocs
	 * qui lui est passé comme final. J'ai expérimenté en commençant par un
	 * gzdeflate(…, 0) (qui doit donner la même chose que mon deflate à la
	 * main), si je lui passe des blocs de 4K il prend le bloc et lui met le
	 * marqueur, mais si je passe des blocs de 64K, il passe le bloc tel quel en
	 * lui collant après coup un bloc vide avec le marqueur. */
	/* Variables relatives au fichier en cours d'inclusion. */
	protected $nomFichierDansArchive;
	protected $controle;
	protected $taille;
	protected $tailleCompr;
	
	function ajouterContenu($nomFichierDansArchive, $contenu)
	{
		print($this->enTeteFichier($nomFichierDansArchive));
		$this->ajouterBloc($contenu);
		print($this->clotureFichier());
	}
	
	function ajouterFichier($nomFichierDansArchive, $cheminFichier)
	{
		if(!($f = @fopen($cheminFichier, 'r'))) return;
		
		/* En-tête */
		
		print($this->enTeteFichier($nomFichierDansArchive));
		
		/* Contenu */
		
		while(!feof($f))
			$this->ajouterBloc(fread($f, 0x8000)); // On ne va pas y aller à la petite cuillère.
		
		/* Résumé */
		
		print($this->clotureFichier());
		
		fclose($f);
	}
	
	function ajouterBloc($octets)
	{
		$tailleBloc = strlen($octets);
		$this->taille += $tailleBloc;
		$this->controle = crc32_continu($octets, $this->controle);
		if($this->methode)
		{
			$this->tailleCompr += $tailleBloc + 5;
			print(pack('cvv', 0, $tailleBloc, -1 - $tailleBloc));
		}
		print($octets);
	}
	
	function clore()
	{
		/* Ponte du récapitulatif de fin d'archive */
		
		$positionCourante = 0;
		$tailleTdM = 0;
		foreach($this->infos as $infos)
		{
			$infos[3] = $positionCourante;
			print($this->enTeteFichier($infos[4], $infos));
			$positionCourante += 0x1e + strlen($infos[4]) + $infos[1] + 0x10; // Place prise par le fichier dans l'archive
			$tailleTdM += 0x2e + strlen($infos[4]); // Taille prise par cette entrée dans la table des matières
		}
		
		/* Clotûre globale */
		
		$nombre = count($this->infos);
		print(pack('NvvvvVVv',
			0x504b0506, // end of central dir signature
			0, // number of this disk
			0, //number of the disk with the start of the central directory
			$nombre, // total number of entries in the central directory on this disk
			$nombre, // total number of entries in the central
			$tailleTdM, // size of the central directory
			$positionCourante, // offset of start of central directory with respect to the starting disk number
			0)); // .ZIP file comment length
	}
	
	/* Génère un en-tête pour un fichier. Si $infos est renseigné (il doit
	 * contenir le CRC-32, la taille prise dans l'archive, la taille du fichier
	 * décompressé, et la position en octets du fichier dans le Zip), un en-tête
	 * récapitulatif est généré (pour être utilisé dans la table des matières du
	 * Zip). Sinon un simple en-tête est créé, qui devra être suivi des données. */
	function enTeteFichier($nomFichier, $infos = null)
	{
		$r =
			($infos !== null
			? pack('Nv', 0x504b0102, 0x0000) // central file header signature, version made by
			: pack('N', 0x504b0304)). // local file header signature
			pack('vvvvv',
				0x0014, // version needed to extract
				0x0008, // general purpose bit flag
				$this->methode ? 0x0008 : 0x0000, // compression method
				0x0000, // last mod file time
				0x0000). // last mod file date
			($infos !== null
			? pack('VVV', $infos[0], $infos[1], $infos[2]) // crc-32, compressed size, uncompressed size
			: pack('VVV', 0, 0, 0)).
			pack('vv', strlen($nomFichier), 0). // file name length, extra field length
		($infos !== null
		? pack('vvvVV', 0, 0, 0, 0, $infos[3]) // file comment length, disk number start, internal file attributes, external file attributes, relative offset of local header 
		: '').
			$nomFichier; // file name, extra field (vide), file comment (vide)
		$this->controle = 0;
		$this->taille = 0;
		$this->tailleCompr = 0;
		$this->nomFichierDansArchive = $nomFichier;
		return $r;
 	}
	
	/* Génère le bloc de fin de fichier, et mémorise le nécessaire pour la
	 * clôture d'archive. */
	function clotureFichier()
	{
		$r = '';
		
		if($this->methode)
		{
			$r .= pack('cvv', 1, 0, -1); // Un bloc vide pour terminer
			$this->tailleCompr += 5;
		}
		
		/* Résumé */
		
		if(!$this->methode) $this->tailleCompr = $this->taille;
		$r .= pack('NVVV', 0x504b0708, $this->controle, $this->tailleCompr, $this->taille);
		$this->infos[] = array($this->controle, $this->tailleCompr, $this->taille, 0, $this->nomFichierDansArchive); // On aura besoin de ressortir les infos sur le fichier à la fin.
		
		return $r;
	}
}

?>