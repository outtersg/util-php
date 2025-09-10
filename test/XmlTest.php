<?php

require_once dirname(__FILE__).'/../xml/composimple.php';
require_once dirname(__FILE__).'/../xml/chargeur.php';

class XmlTest extends PHPUnit_Framework_TestCase
{
	function testOnEcraseALaRelecture()
	{
		file_put_contents('/tmp/test.util', <<<TERMINE
<?xml version="1.0"?>
<racine>
	<chose>coucou</chose>
</racine>
TERMINE
);
		$c = new Chargeur();
		$o = new CompoBidon();
		$c->charger('/tmp/test.util', 'racine', $o, true);
		$c->charger('/tmp/test.util', 'racine', $o, true);
		assert($o->chose == 'coucou');
	}
}

class CompoBidon extends CompoSimple
{
	public function __construct()
	{
		parent::__construct(array('chose' => &$this->chose));
	}
}

?>
