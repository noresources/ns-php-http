<?php
/**
 * Copyright © 2012 - 2021 by Renaud Guillard (dev@nore.fr)
 * Distributed under the terms of the MIT License, see LICENSE
 *
 * @package HTTP
 */
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Response\TextResponse;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use NoreSources\Http\Header\HeaderField;
use NoreSources\Http\Header\HeaderValueFactory;
use NoreSources\Http\Header\HeaderValueInterface;
use NoreSources\Http\Tools\BasicTcpLogger;
use NoreSources\Http\Tools\RequestDumper;
use NoreSources\Type\TypeDescription;

ini_set('html_errors', 'off');

include_once (__DIR__ . '/../../../vendor/autoload.php');

$dumper = new RequestDumper();
$requestLogger = new BasicTcpLogger('localhost', 5555);
$requestLogger->prefix = '  > ';
$dumper->setLogger($requestLogger);

$responseLogger = clone $requestLogger;
$responseLogger->prefix = '  < ';

$request = ServerRequestFactory::fromGlobals();
$dumper->dump($request);

$response = null;
$authorization = HeaderValueFactory::fromMessage($request,
	HeaderField::AUTHORIZATION);

$requestLogger->debug(
	'Auth = ' . TypeDescription::getName($authorization));

if ($authorization instanceof HeaderValueInterface)
	$response = new TextResponse('Hello ' . $authorization);
else
	$response = new TextResponse('None shall pass' . PHP_EOL, 401,
		[
			HEADER::WWW_AUTHENTICATE => 'Basic realm=test'
		]);

$s = new Laminas\Diactoros\Response\Serializer();
$responseLogger->debug($s->toString($response));

$emiter = new SapiEmitter();
$emiter->emit($response);


