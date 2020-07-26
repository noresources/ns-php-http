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
use Nette\PhpGenerator\PhpFile;
use NoreSources\Http\Tools\FileBuilderHelper;
require (__DIR__ . '/../vendor/autoload.php');

$fileHeader = \file_get_contents(__DIR__ . '/../resources/templates/class-file-header.txt');
$fileHeader = \str_replace('{year}', date('Y'), $fileHeader);

$className = 'AuthenticationScheme';
$directory = 'Authentication';
$dataFilename = 'authschemes.csv';

$dataStream = \fopen(__DIR__ . '/../resources/data/authschemes.csv', 'r');

$classFile = new PhpFile();

$classFile->addComment($fileHeader);

$ns = $classFile->addNamespace('NoreSources\Http\\' . $directory);

$projectPath = realPath(__DIR__ . '/..');
$classFile->addComment(
	'This file is generated by ' . \substr(__FILE__, \strlen($projectPath) + 1) . PHP_EOL);

$classFile->addComment('@package Http');

$cls = $ns->addClass($className);

$cls->addComment(<<< EOF
HTTM Authenication schemes
EOF
);

$index = 0;
while ($entry = \fgetcsv($dataStream))
{
	if ($index++ == 0)
		continue; // Column names

	$name = $entry[0];
	$references = $entry[1];
	$notes = $entry[2];

	$referenceLinks = FileBuilderHelper::makePhpDocReferenceLinks($references);

	$constantName = \strtoupper(\preg_replace(',[^a-zA-Z0-9],', '_', $name));
	$constantComment = $name . ' HTTP Authentication scheme.' . PHP_EOL . $notes . PHP_EOL .
		$referenceLinks;

	$constant = $cls->addConstant($constantName, $name);
	$constant->addComment($constantComment);
}

\fclose($dataStream);

$dumper = new Dumper();

file_put_contents($projectPath . '/src/' . $directory . '/' . $className . '.php', $classFile);
