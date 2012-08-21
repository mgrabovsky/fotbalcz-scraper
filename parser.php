<?php

function make_fotbalcz_uri( $soutez ) {
	// &show=Los,Aktual,Vysledky
	$template = '%s/souteze.asp?soutez=%s';
	$subpath = '';

	if( is_array( $soutez ) ) {
		if( isset( $soutez['okres'] ) ) {
			$subpath = sprintf( $template, $soutez['kraj'] . '/' . $soutez['okres'],
				$soutez['soutez'] );
		} else {
			$subpath = sprintf( $template, $soutez['kraj'], $soutez['soutez'] );
		}
	} else {
		$subpath = $soutez;
	}

	$uri = 'http://nv.fotbal.cz/domaci-souteze/kao/' . $subpath;
	return $uri;
}

function get_competition_title( DOMXPath $xpath, DOMElement $context ) {
	$h4_texts = $xpath->query( '//h4/text()', $context );
	return $h4_texts->item( 0 )->nodeValue;
}

/*
 * Results table row format
 * ------------------------
 *
 * ID: <3-letter group identifier><2-digit round integer><2-digit match integer>
 * Host, Guest: <alphanumeric + dot>
 * Score: <integer>:<integer>[ (<integer>:<integer>)]
 * Spectators: <integer>
 * Note: <anything?>
 * Goals: GoalRecord[, GoalRecord]+[ - GoalRecord[, GoalRecord]+]
 * GoalRecord: Name[ <integer>][, vlastní]
 * Name: <alpha + space + dot>
 * Cards: [ŽK: CardRecords][; ][ČK: CardRecords]
 * CardRecords: Name[, Name]+[ - Name[, Name]]
 *
 * ID | Host | Guest | Score | Spectators | Note
 * [Branky: Goals][[; ]Cards]
 */
function get_results( DOMXPath $xpath, DOMNodeList $tables, $comp_id ) {
	$results = array();
	$callback = make_text_extracting_callback( 'td/b/text()[boolean(.)]' );
	$query = 'tr[td[1][b[boolean(text()) and ' .
	   'starts-with(text(), "' . $comp_id . '")]]]';

	foreach( $tables as $table ) {
		$title = $xpath->query( 'tr/td[@class="Titulka"]/p', $table )
			->item( 0 )->nodeValue;
		$matches = get_rows_from_table( $xpath, $query, $table, $callback );
		$results[] = compact( 'title', 'matches' );
	}

	return $results;
}

/*
 * Rankings table row format
 * -------------------------
 *
 * Rank: <integer>.
 * Team: <alphanumeric + dot>
 * Matches, Won, Draws, Losses, Points: <integer>
 * Score: <integer>: <integer>
 * _PK: ?
 * _PRAV: ( [-]<integer>)
 *
 * Rank | Team | Matches | Won | Draws | Losses | Score | Points | _PK | _PRAV
 */
function get_rankings_table( DOMXPath $xpath, DOMElement $table ) {
	$callback = make_text_extracting_callback( 'td/text()[boolean(.)]' );
	$query = 'tr[td[1][not(@colspan) and boolean(text()) ' .
			'and string-length(text()) <= 3]]';

	return get_rows_from_table( $xpath, $query, $table, $callback );
}

/*
 * Fixtures table row format
 * -------------------------
 *
 * ID: <3-letter group identifier><2-digit round integer><2-digit match integer>
 * Host, Guest: <alphanumeric + punctuation>
 * Date: <2-digit day>.<2-digit month>. <2-digit hour>:<2-digit minute>
 * DayOfWeek: <2-capital-letter day of week>
 * Place: <alphanumeric + punctuation>
 *
 * ID | Host | Guest | Day | DayOfWeek | Place
 */
function get_fixtures( DOMXPath $xpath, DOMNodeList $tables, $comp_id ) {
	$fixtures = array();
	$callback = make_text_extracting_callback( 'td/text()[boolean(.)]' );
	$query = 'tr[td[1][not(@colspan) and boolean(text()) and ' .
	   'starts-with(text(), "' . $comp_id . '")]]';

	foreach( $tables as $table ) {
		$title = $xpath->evaluate( 'tr/td[@class="Titulka"]/p//text()', $table )
			->item( 0 )->nodeValue;
		$matches = get_rows_from_table( $xpath, $query, $table, $callback );
		$fixtures[] = compact( 'title', 'matches' );
	}

	return $fixtures;
}

function get_last_round_matches( DOMXPath $xpath, DOMElement $table, $comp_id ) {
	$callback = make_text_extracting_callback( 'td/b/text()[boolean(.)]' );
	$query =
		'tr[td[1][not(@colspan) and b[starts-with(text(), "' . $comp_id . '")]]]';

	return get_rows_from_table( $xpath, $query, $table, $callback );
}

function get_next_round_matches( DOMXPath $xpath, DOMElement $table, $comp_id ) {
	$callback = make_text_extracting_callback( 'td/text()[boolean(.)]' );
	$query =
		'tr[td[1][not(@colspan) and starts-with(text(), "' . $comp_id . '")]]';

	return get_rows_from_table( $xpath, $query, $table, $callback );
}

function make_text_extracting_callback( $xpath_query ) {
	return function( DOMXPath $xpath, DOMElement $row ) use( $xpath_query ) {
			$info = array();

			$texts = $xpath->query( $xpath_query, $row );
			foreach( $texts as $text ) {
				$info[] = $text->nodeValue;
			}

			return $info;
		};
}

function get_rows_from_table( DOMXPath $xpath, $xpath_query, DOMElement $table,
	$callback )
{
	$matches = array();

	$rows = $xpath->query( $xpath_query, $table );
	foreach( $rows as $row ) {
		$matches[] = $callback( $xpath, $row );
	}

	return $matches;
}

