<?php

function &get_page_object(DOMDocument $doc) {
	$xpath = new DOMXPath($doc);
	$container = $xpath->query('id("maincontainer")/table[@height="300"]//td[@width="500"]')->item(0);
	$tables470 = $xpath->query('.//table[@width="470"]', $container);

	$page = new StdClass;
	$page->xpath = &$xpath;
	$page->container = &$container;
	$page->tables470 = &$tables470;

	return $page;
}

function get_results($page) {
	$results = [];
	$callback = extract_text('td');
	$rows_query = 'tr[not(@bgcolor) and td[1][not(@bgcolor) and b[boolean(text())]]]';

	$columns = ['id', 'home', 'away', 'score', 'spectators', 'notes'];

	foreach ($page->tables470 as $table) {
		$title = $page->xpath->evaluate('string(tr/td[@class="Titulka"]/p)', $table);

		$matches = get_rows_from_table($page->xpath, $rows_query, $table, $callback);
		$matches = array_map(function($old_match) use($columns) {
			$match = array_combine($columns, $old_match);
			$match = array_map(function($val) {
				$trimmed = trim($val, " \t\n\r\0\x0b\xa0\xc2");
				if ($trimmed === '')
					return null;
				else
					return $trimmed;
			}, $match);
			return $match;
		}, $matches);

		$results[] = compact('title', 'matches');
	}

	return $results;
}

function get_rankings($page) {
	$callback = extract_text('td/text()[boolean(.)]');
	$query = 'tr[td[1][not(@colspan) and boolean(text()) and string-length(text()) <= 3]]';

	$columns = ['rank', 'name', 'matches', 'wins', 'draws', 'losses', 'score',
		'points', 'pk', 'p'];

	$rows = get_rows_from_table($page->xpath, $query, $page->tables470->item(1),
		$callback);
	$rows = array_map(function($row) use($columns) {
		$new_row = array_combine($columns, $row);

		# Clean up the score field
		$new_row['score'] = implode(':',
			array_map('trim',
				explode(':', $new_row['score'])
			)
		);

		return $new_row;
	}, $rows);

	return $rows;
}

function get_fixtures($page) {
	$fixtures = [];
	$callback = extract_text('td/text()[boolean(.)]');
	$query = 'tr[td[1][not(@colspan) and boolean(text())]]';

	$columns = ['id', 'home', 'away', 'date'];
	$time_zone = new DateTimeZone('Europe/Prague');

	foreach ($page->tables470 as $table) {
		$title = $page->xpath->evaluate('string(tr/td[@class="Titulka"]/p)', $table);

		$year = explode('.', $title)[3];

		$matches = get_rows_from_table($page->xpath, $query, $table, $callback);
		$matches = array_map(function($old_match) use($columns, $year,
			$time_zone)
		{
			$old_match = array_slice($old_match, 0, 4);
			$match = array_combine($columns, $old_match);

			$match['date'] = DateTime::createFromFormat('d.m. H:i Y',
				$match['date'] . ' ' . $year, $time_zone);

			return $match;
		}, $matches);

		$fixtures[] = compact('title', 'matches');
	}

	return $fixtures;
}

function extract_text($query) {
	return function(DOMXPath $xpath, DOMElement $row) use($query) {
			$info = [];

			$texts = $xpath->query($query, $row);
			foreach ($texts as $text) {
				$info[] = $text->nodeValue;
			}

			return $info;
		};
}

function get_rows_from_table(DOMXPath $xpath, $rows_query, DOMElement $table, $callback) {
	$matches = [];

	$rows = $xpath->query($rows_query, $table);
	foreach ($rows as $row) {
		$matches[] = $callback($xpath, $row);
	}

	return $matches;
}

