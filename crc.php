<?php

/* Apparemment tout le monde a son implémentation incompatible. En voici donc
 * intégralement recopiée sur le code de Marcel van Kervinck
 * (<http://brick.bitpit.net/~marcelk/crc32>), code qui avait l'immense
 * avantage de pouvoir être rapidement compilé et de donner le même résultat que
 * celui de l'outil zip livré avec Darwin. Ensuite, il a l'air de prendre la
 * voie "little endian", mais tant que ça marche chez moi… Et, joie, on découvre
 * le même résultat qu'avec la fonction livrée. */

function crc32_continu($donnees, $valeurCourante = 0)
{
	/* Si on peut passer par du code C, on ne va pas se gêner; en particulier,
	 * le crc32 livré avec PHP se réinitialise à chaque appel, mais ça nous
	 * permet au moins de l'appeler pour notre première itération. */
	
	if($valeurCourante == 0) return crc32($donnees);
	
	/* Initialisation */
	
	if(!array_key_exists('crc32_statique', $GLOBALS))
	{
		$polynome = 0xedb88320;
		
		for($octet = 0; $octet < 0x100; ++$octet)
		{
			for($reste = $octet ^ 0xff, $bit = 8; --$bit >= 0;)
				$reste = (($reste >> 1) & 0x7fffffff) ^ (($reste & 0x1) ? $polynome : 0);
			$s[$octet] = $reste ^ 0xff000000;
		}
		$GLOBALS['crc32_statique'] = $s;
	}
	else
		$s = $GLOBALS['crc32_statique'];
	
	/* Calcul */
	
	for($n = strlen($donnees), $i = 0; $i < $n; ++$i)
		$valeurCourante = $s[(ord($donnees[$i]) ^ $valeurCourante) & 0xff] ^ (($valeurCourante >> 8) & 0x00ffffff);
	
	return $valeurCourante;
}

?>