<?php

error_reporting(E_ALL);
libxml_use_internal_errors(true);
require_once 'parser.php';
require_once 'vendor/autoload.php';

$played = [];

function scrape_page($uri, array $scrapers) {
	if (empty($scrapers)) {
		throw new Exception('Will not scrape without callbacks');
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

function scrape_results() {
	list($results) = scrape_page(realpath('fotbal.cz/Vysledky.html'),
		['get_results']);

	return $results;
}

function scrape_fixtures() {
	list($fixtures) = scrape_page(realpath('fotbal.cz/Los.html'),
		['get_fixtures']);

	return $fixtures;
}

function filter_ours($us, $rounds) {
	$matches = array_reduce($rounds, function($memo, $round) {
		$our = array_filter($round['matches'], function($match) {
			return $match['home'] === 'Klobouky' || $match['away'] === 'Klobouky';
		});

		if (count($our) > 0)
			$memo = array_merge($memo, $our);

		return $memo;
	}, []);

	return $matches;
}

function results() {
	global $played;

	$rounds = scrape_results();
	$matches = filter_ours('Klobouky', $rounds);

	$played = array_map(function($m) { return $m['id']; }, $matches);

	$results = array_map(function($match) {
		$row = [
			'row_class'    => null,
			'opponent'     => null,
			'pretty_score' => null,
		];

		// Split score so it's easier to work with it
		$score = array_map('intval', explode(':', $match['score']));

		// Assign opponent and format score
		if ($match['home'] === 'Klobouky') {
			$where = 'home';
			$row['opponent'] = $match['away'];
			$row['pretty_score'] = "<b>{$score[0]}</b>:{$score[1]}";
		} else {
			$where = 'away';
			$row['opponent'] = $match['home'];
			$row['pretty_score'] = "{$score[0]}:<b>{$score[1]}</b>";
		}

		// Make sure that every dot is followed by a space
		$row['opponent'] = preg_replace('/\.(?! |$)/', '. ', $row['opponent']);

		$row['row_class'] = $where . ' ';

		// Assign outcome
		if ($score[0] === $score[1]) {
			$row['row_class'] .= 'draw';
		} elseif ($score[0] > $score[1]) {
			if ($where === 'home')
				$row['row_class'] .= 'win';
			else
				$row['row_class'] .= 'loss';
		} else {
			if ($where === 'home')
				$row['row_class'] .= 'loss';
			else
				$row['row_class'] .= 'win';
		}

		return $row;
	}, $matches);

	return $results;
}

function fixtures() {
	global $played;
	$days_of_week = ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'];

	$rounds = scrape_fixtures();
	$matches = filter_ours('Klobouky', $rounds);
	$matches = array_merge(array_filter($matches, function($m) use($played) {
		return $m['date'] !== false && !in_array($m['id'], $played);
	}));

	$fixtures = array_map(function($match) use($days_of_week) {
		$row = [
			'row_class'       => null,
			'opponent'        => null,
			'datetime'        => null,
			'pretty_datetime' => null,
		];

		// Assign opponent and format score
		if ($match['home'] === 'Klobouky') {
			$row['row_class'] = 'home';
			$row['opponent'] = $match['away'];
		} else {
			$row['row_class'] = 'away';
			$row['opponent'] = $match['home'];
		}

		$row['datetime'] = $match['date']->format('Y-m-d\TH:iO');
		$row['pretty_datetime'] =
			$days_of_week[intval($match['date']->format('w'))] . ', ' .
			$match['date']->format('j. n. Y G.i \h');

		return $row;
	}, $matches);

	return $fixtures;
}

$results = array_reverse(results());
$fixtures = fixtures();

// Output
$mustache = new Mustache_Engine([
	'loader' => new Mustache_Loader_FilesystemLoader(__DIR__ . '/templates'),
	'cache' => __DIR__ . '/cache',
]);

echo $mustache->render('matches', [
	'results' => $results,
	'fixtures' => $fixtures,
]);

$test_data = ['results' => [[
			'row_class' => 'away loss',
			'opponent' => 'Uherčice',
			'pretty_score' => '7:<i>1</i>',
		], [
			'row_class' => 'home loss',
			'opponent' => 'Starovičky',
			'pretty_score' => '<i>1</i>:2',
		], [
			'row_class' => 'home win',
			'opponent' => 'Nosislav',
			'pretty_score' => '<i>4</i>:3',
		], [
			'row_class' => 'away draw',
			'opponent' => 'D. Dunajovice',
			'pretty_score' => '0:<i>4</i>',
		], [
			'row_class' => 'home win',
			'opponent' => 'Popice',
			'pretty_score' => '<i>3</i>:1',
		],
	],
	'fixtures' => [[
			'row_class' => 'home',
			'opponent' => 'Vrbice',
			'datetime' => '2013-08-25T16:30+2000',
			'pretty_datetime' => 'Sobota, 25. 8. 2013 16.30 h'
		], [
			'row_class' => 'away',
			'opponent' => 'Kobylí',
			'datetime' => '2013-09-01T16:30+2000',
			'pretty_datetime' => 'Neděle, 1. 9. 2013 16.30 h'
		], [
			'row_class' => 'home',
			'opponent' => 'Pavlov',
			'datetime' => '2013-09-08T16:30+2000',
			'pretty_datetime' => 'Neděle, 8. 9. 2013 16.30 h'
		],
	]];

