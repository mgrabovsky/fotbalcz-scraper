<?php

error_reporting(E_ALL);
libxml_use_internal_errors(true);
require_once 'parser.php';
require_once 'vendor/autoload.php';

function scrape_page($uri, array $scrapers) {
	if (empty($scrapers)) {
		throw new Exception('Cannot scrape without callbacks');
	}

	$doc = new DOMDocument;
	if (!$doc->loadHTMLFile($uri)) {
		throw new Exception("Could not load page: $uri");
	}
	error_log("Loaded page: $uri");

	$page = get_page_object($doc);
	$results = [];
	foreach ($scrapers as $i => $func) {
		$results[$i] = call_user_func($func, $page);
	}

	$page = null;
	$doc = null;

	return $results;
}

function scrape_rankings() {
	list($rankings) = scrape_page(realpath('fotbal.cz/Aktual.html'),
		['get_rankings']);

	return $rankings;
}

function rankings() {
	$rankings = scrape_rankings();

	$rankings = array_map(function($team) {
		$row = [
			'row_attrs'      => null,
			'team'           => $team['name'],
			'matches_played' => $team['matches'],
			'wins'           => $team['wins'],
			'draws'          => $team['draws'],
			'losses'         => $team['losses'],
			'score'          => $team['score'],
			'points'         => $team['points'],
		];

		$row['team'] = preg_replace('/\.(?! |$)/', '. ', $row['team']);

		if ($row['team'] === 'Klobouky')
			$row['row_attrs'] = ' class="highlight"';

		return $row;
	}, $rankings);

	return $rankings;
}

$rankings = rankings();

// Output
$mustache = new Mustache_Engine([
	'loader' => new Mustache_Loader_FilesystemLoader(__DIR__ . '/templates'),
	'cache' => __DIR__ . '/cache',
]);

echo $mustache->render('rankings', [
	'rankings' => $rankings,
]);

