<?php

error_reporting(E_ALL);
libxml_use_internal_errors(true);
require_once 'parser.php';
require_once 'vendor/autoload.php';

$fixtures = [];
$results  = [];
$played   = [];

function filter_ours($us, $rounds) {
	$matches = array_reduce($rounds, function($memo, $round) use($us) {
		$our = array_reduce($round['matches'], function($memo, $match) use($us, $round) {
			if ($match['home'] === $us || $match['away'] === $us) {
				$roundnum = intval(explode('.', $round['title'])[0]);
				$memo[] = $match + [ 'round' => $roundnum ];
			}

			return $memo;
		}, []);

		if (count($our) > 0)
			$memo = array_merge($memo, $our);

		return $memo;
	}, []);

	return $matches;
}

function results(Fotbalcz\Fotbalcz &$fotbal) {
	global $played, $fixtures;
	$days_of_week = ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'];

	$rounds  = $fotbal->get_results();
	$matches = filter_ours('Klobouky', $rounds);

	$played = array_map(function($m) { return $m['id']; }, $matches);

	$results = array_map(function($match) use($fixtures, $days_of_week) {
		$row = [
			'id'           => $match['id'],
			'round'        => $match['round'],
			'datetime'     => null,
			'date'         => null,
			'time'         => null,
			'opponent'     => null,
			'where'        => null,
			'pretty_score' => null,
		];

		$fixture = array_filter($fixtures, function($f) use($match) {
			return $f['id'] === $match['id'];
		});
		$fixture = array_shift( $fixture );

		$row['round'] = $match['round'];
		$row['datetime'] = $fixture['date_obj']->format('Y-m-d\TH:iO');
		$row['date'] =
			$days_of_week[intval($fixture['date_obj']->format('w'))] . ', ' .
			$fixture['date_obj']->format('j. n. Y');
		$row['time'] = $fixture['date_obj']->format('G.i \h');;

		// Split score so it's easier to work with it
		$score = array_map('intval', explode(':', $match['score']));

		// Assign opponent and format score
		if ($match['home'] === 'Klobouky') {
			$where = 'home';
			$row['where'] = 'doma';
			$row['opponent'] = $match['away'];
			$row['pretty_score'] = "<b>{$score[0]}</b>:{$score[1]}";
		} else {
			$where = 'away';
			$row['where'] = 'venku';
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

function fixtures(Fotbalcz\Fotbalcz &$fotbal) {
	$days_of_week = ['Ne', 'Po', 'Út', 'St', 'Čt', 'Pá', 'So'];

	$rounds = $fotbal->get_fixtures();
	$matches = filter_ours('Klobouky', $rounds);

	$fixtures = array_map(function($match) use($days_of_week) {
		$row = [
			'id'              => $match['id'],
			'row_class'       => null,
			'round'           => $match['round'],
			'datetime'        => null,
			'date'            => null,
			'time'            => null,
			'opponent'        => null,
			'where'           => null,
		];

		if (!isset($match['date']) || !$match['date'])
			return null;

		$row['round'] = $match['round'];
		$row['date_obj'] = &$match['date'];
		$row['datetime'] = $match['date']->format('Y-m-d\TH:iO');
		$row['date'] =
			$days_of_week[intval($match['date']->format('w'))] . ', ' .
			$match['date']->format('j. n. Y');
		$row['time'] = $match['date']->format('G.i \h');

		// Assign opponent and format score
		if ($match['home'] === 'Klobouky') {
			$row['row_class'] = 'home';
			$row['where'] = 'doma';
			$row['opponent'] = $match['away'];
		} else {
			$row['row_class'] = 'away';
			$row['where'] = 'venku';
			$row['opponent'] = $match['home'];
		}

		return $row;
	}, $matches);

	return $fixtures;
}

$fotbal   = new Fotbalcz\Fotbalcz('624A2B');
$fixtures = array_filter(fixtures($fotbal));
$results  = array_reverse(results($fotbal));
$fobal    = null;

$fixtures = array_merge(array_filter($fixtures, function($m) use($played) {
	return $m['date'] !== false && !in_array($m['id'], $played);
}));

// Output
$mustache = new Mustache_Engine([
	'loader' => new Mustache_Loader_FilesystemLoader(__DIR__ . '/templates'),
	'cache' => __DIR__ . '/cache',
]);

echo $mustache->render('matches', [
	'results' => $results,
	'fixtures' => $fixtures,
]);

