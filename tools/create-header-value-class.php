<?php
/**
 * Copyright © 2012 - 2020 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 */

/**
 *
 * @package Http
 */
namespace NoreSources\Http;

use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use NoreSources\Http\Header\AlternativeValueListInterface;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\Http\Header\HeaderValueInterface;
use NoreSources\Http\Header\TextHeaderValue;
use NoreSources\Http\Header\Traits\AlternativeValueListTrait;
use NoreSources\Http\Traits\QualityValueTrait;
use NoreSources\Type\TypeDescription;
require (__DIR__ . '/../vendor/autoload.php');

$argv = $_SERVER['argv'];
$headerName = null;
$createAlternativeValueListClass = false;
$fileBasePath = __DIR__ . '/../src/Header';
$overwrite = false;
$useQualityValue = false;

foreach ($argv as $value)
{
	if ($value == '-h' || $value == '--help')
	{
		echo ($_SERVER['argv'][0] . ' [options] <header field name>' .
			PHP_EOL);
		echo <<< EOF
 
	-a: Create AlternativeValueList class
	-f: Overwrite existing files
	-q: HeaderValue class implements QualityValueInterface


EOF;
		exit(0);
	}
	if ($value == '-a')
		$createAlternativeValueListClass = true;
	elseif ($value == '-f')
		$overwrite = true;
	elseif ($value == '-q')
		$useQualityValue = true;
	else
		$headerName = $value;
}

$classnames = HeaderValueFactory::getHeaderValueClassnames($headerName);

$fileHeader = \file_get_contents(
	__DIR__ . '/../resources/templates/file-header.txt');
$fileHeader = \str_replace('{year}', date('Y'), $fileHeader);
$projectPath = realPath(__DIR__ . '/..');

$interfaceNames = [
	[
		AlternativeValueListInterface::class
	],
	[
		HeaderValueInterface::class
	]
];

$traits = [
	[
		AlternativeValueListTrait::class
	],
	[]
];

$constants = [

	[
		'HEADERVALUE_CLASSNAME' => new Literal(
			TextHeaderValue::class . '::class'),
		'VALUE_DELIMITER' => ','
	],
	[]
];

if ($useQualityValue)
{
	$interfaceNames[1][] = QualityValueInterface::class;
	$traits[1][] = QualityValueTrait::class;
}

foreach ($classnames as $index => $classname)
{
	$classname = TypeDescription::getLocalName($classname, true);

	if ($index == 0)
		if (!$createAlternativeValueListClass)
			continue;

	$classFile = new PhpFile();
	$classFile->addComment($fileHeader);
	$classFile->addComment('@package Http');

	$ns = $classFile->addNamespace('NoreSources\Http\Header');

	$cls = $ns->addClass($classname);
	foreach ($interfaceNames[$index] as $i)
		$cls->addImplement($i);
	foreach ($traits[$index] as $trait)
	{
		$cls->addTrait($trait);
	}

	foreach ($constants[$index] as $name => $value)
	{
		$cls->addConstant($name, $value);
	}

	$dumper = new Dumper();

	$filePath = $projectPath . '/src/Header/' . $classname . '.php';
	if (!\file_exists($filePath) || $overwrite)
		file_put_contents($filePath, $classFile);
}