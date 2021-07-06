<?php
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\Response\TextResponse;
use NoreSources\Container\Container;
use NoreSources\Http\Server\MultipartFormDataRequestBodyParserMiddleware;
use NoreSources\Http\Server\StructuredTextRequestBodyParserMiddleware;
use NoreSources\Http\Test\ClosureRequestHandler;
use NoreSources\Type\TypeDescription;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

ini_set('html_errors', 'off');

function prepend($prefix, $text)
{
	return \preg_replace('/^(.*)/m', $prefix . "\\1", $text);
}

function print_request_body(ServerRequestInterface $request)
{
	$body = $request->getBody();
	$body->rewind();
	echo (prepend(">\t\t", $body->getContents()) . PHP_EOL);
}

function print_request_parsed(ServerRequestInterface $request)
{
	var_export($request->getParsedBody());
}

function print_request_files(ServerRequestInterface $request)
{
	foreach ($request->getUploadedFiles() as $file)
	{
		if ($file instanceof UploadedFileInterface)
		{
			echo ($file->getClientFilename() . ' ' . $file->getSize() .
				PHP_EOL);
		}
	}
}

function print_request(ServerRequestInterface $request)
{
	$s = new Laminas\Diactoros\Request\Serializer();
	echo (prepend(">\t\t", $s->toString($request)) . PHP_EOL);
}

include_once (__DIR__ . '/../../../vendor/autoload.php');

$request = ServerRequestFactory::fromGlobals();

print_request($request);

$base = basename($request->getUri()->getPath());
$middlewares = [
	'multipart' => new MultipartFormDataRequestBodyParserMiddleware(),
	'structured' => new StructuredTextRequestBodyParserMiddleware()
];

$mw = Container::keyValue($middlewares, $base,
	Container::firstValue($middlewares));

$resultRequest = null;
$response = $mw->process($request,
	new ClosureRequestHandler(
		function (ServerRequestInterface $request) use (&$resultRequest) {
			$resultRequest = $request;
			return new TextResponse('OK');
		}));

echo ('-- After ' . TypeDescription::getLocalName($mw) .
	' --------------------------------------------' . PHP_EOL);
print_request_parsed($resultRequest);
print_request_files($resultRequest);
foreach ($resultRequest->getUploadedFiles() as $key => $value)
{
	$filepath = '/tmp/' . $value->getClientFilename();
	echo ($key . ' -> ' . $filepath . PHP_EOL);
	if ($value instanceof UploadedFileInterface)
		$value->moveTo($filepath);
	else
		echo ($value->getClientFilename() . ' error ' .
			$value->getError() . PHP_EOL);
}

