<?php

error_reporting(E_ALL);
libxml_use_internal_errors(true);
require_once 'parser.php';
require_once 'vendor/autoload.php';

function rankings() {
	$fotbal   = new Fotbalcz\Fotbalcz('624A2B', [
		'document_fetcher' => 'Fotbalcz\\FileFetcher'
	]);
	$rankings = $fotbal->get_rankings();
	$fotbal   = null;

	$i = 0;
	$our_index = 0;
	$rankings = array_map(function($team) use(&$i, &$our_index) {
		$row = [
			'row_attrs'      => null,
			'team'           => $team['name'],
			'matches_played' => $team['matches'],
			'points'         => $team['points'],
		];

		$row['team'] = preg_replace('/\.(?! |$)/', '. ', $row['team']);

		if ($row['team'] === 'Klobouky') {
			$row['row_attrs'] = ' class="highlight"';
			$our_index = $i;
		}
		++$i;

		return $row;
	}, $rankings);

	return [$rankings, $our_index];
}

list($rankings, $our_index) = rankings();
$length = count($rankings);

if ($our_index <= 3) {
	$rankings   = array_slice($rankings, 0, max(3, $our_index + 2));
	$rankings[] = ['separator' => true];
} else {
	$tail = array_slice($rankings, $our_index - 1, 3);
	$tail[0]['row_attrs'] = ' class="pos-' . $our_index . '"';

	$rankings = array_merge(
		array_slice($rankings, 0, 2),
		[['separator' => true]],
		$tail);

	if ($our_index <= $length - 3) {
		$rankings[] = ['separator' => true];
	}
}

// Output
$mustache = new Mustache_Engine([
	'loader' => new Mustache_Loader_FilesystemLoader(__DIR__ . '/templates'),
	'cache' => __DIR__ . '/cache',
]);

echo $mustache->render('rankings-sidebar', [
	'rankings' => $rankings,
]);

