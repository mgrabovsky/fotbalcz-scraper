<?php

error_reporting(E_ALL);
libxml_use_internal_errors(true);
require_once 'parser.php';
require_once 'vendor/autoload.php';

function rankings() {
	$fotbal   = new Fotbalcz\Fotbalcz('624A2B');
	$rankings = $fotbal->get_rankings();
	$fotbal   = null;

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

