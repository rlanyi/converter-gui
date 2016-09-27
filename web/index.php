<?php

session_cache_limiter(false);
session_name('_ytadmin_session');
ini_set('session.cookie_lifetime', 60 * 60 * 24 * 365);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 365);
session_start();

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Tracy\Debugger;

require '../vendor/autoload.php';
spl_autoload_register(function ($classname) {
	if (file_exists("../lib/" . $classname . ".php")) {
		require ("../lib/" . $classname . ".php");
	}
});

require('../config.php');

$app = new \Slim\App(['settings' => $config]);
$container = $app->getContainer();

if ((isset($config['debug'])) && ($config['debug'] === true)) {
	unset($app->getContainer()['errorHandler']);
	Debugger::enable();
}

// Logging
$container['logger'] = function($c) {
	$logger = new \Monolog\Logger('YouTubeTV');
	$file_handler = new \Monolog\Handler\StreamHandler("../logs/app.log");
	$logger->pushHandler($file_handler);
	return $logger;
};

// Templates
$container['view'] = function ($container) {
	$view = new \Slim\Views\Twig(realpath('../templates'), [
		'cache' => realpath('../cache'),
		'auto_reload' => true
		]);
    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
	$view->addExtension(new \Slim\Views\TwigExtension(
		$container['router'],
		$basePath
		));

	return $view;
};

// Database
$container['db'] = function ($c) {
	$db = $c['settings']['db'];
	$pdo = new PDO("mysql:host=" . $db['host'] . ";dbname=" . $db['dbname'],
		$db['user'], $db['pass'], [PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"]);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
//	$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
	return $pdo;
};

// Errors
//// 404
$container['notFoundHandler'] = function ($c) {
	return new NotFoundWithTwig($c->get('view'), 'error404.html', function ($request, $response) use ($c) {
		return $c['response']->withStatus(404);
	});
};

// Routes
$app->get('/', function (Request $request, Response $response) {
	return $this->view->render($response, 'home.html');
})->setName('home');

$app->get('/videos/list', '\InfoController:listVideos')->setName('videos-list');
$app->get('/videos/convert', '\InfoController:showConvertStatus')->setName('videos-convert');

$app->get('/youtube/meta/update/{channel}[/{submit:submit}]', '\YoutubeController:updateMeta')->setName('youtube-meta-update');
$app->get('/youtube/authorize', '\YoutubeController:authorize')->setName('youtube-authorize');

$app->get('/hello/{name}', function (Request $request, Response $response, $args) {
	$name = $request->getAttribute('name');

	$db = $this['db'];

	return $this->view->render($response, 'profile.html', [
		'name' => $args['name']
		]);
})->setName('profile');

$app->run();
