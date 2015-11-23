<?php

//require_once 'PHPUnit/Framework.php';

require_once dirname(__FILE__).'/../htopus.php';

class HTOpusTest extends PHPUnit_Framework_TestCase
{
	public function testA()
	{
		$e = <<<TERMINE
			racine:
				groupe avec=attr:
					élément1
					sous-groupe:
						sous-sous-groupe-vide:
					élément2
				groupevide:
				feuille avec=attr
TERMINE;
		$s = array
		(
			'racine' => array
			(
				'fils' => array
				(
					'groupe' => array
					(
						'attrs' => array('avec' => 'attr'),
						'fils' => array
						(
							'élément1' => array(),
							'sous-groupe' => array
							(
								'fils' => array
								(
									'sous-sous-groupe-vide' => array('fils' => array()),
								),
							),
							'élément2' => array(),
						),
					),
					'groupevide' => array
					(
						'fils' => array(),
					),
					'feuille' => array
					(
						'attrs' => array('avec' => 'attr'),
					),
				),
			),
		);
		
		$h = new HTOpus;
		$d = new Dodo;
		$h->abonner($d);
		$r = $h->lire($e)->document['fils'];
		
		$this->assertEquals($s, $r);
	}
}

?>
