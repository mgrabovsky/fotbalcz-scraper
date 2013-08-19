<?php

class competition {
	public $id, $uri, $title;

	public function __construct( $id ) {
		$this->id = $id;
		$this->uri = "http://nv.fotbal.cz/domaci-souteze/kao/souteze.asp?soutez=$id";
	}
}

function get_competition_title( $page ) {
	return $page->xpath->query( '/.//h4/text()', $page->container )->item( 0 )
		->nodeValue;
}

function &get_page_object( DOMDocument $doc ) {
	$xpath = new DOMXPath( $doc );
	$container = $xpath->query(
		'//div[@id="maincontainer"]/table[@height="300"]//td[2]' )->item( 0 );
	$tables470 = $xpath->query( '/.//table[@width="470"]', $container );

	$page = new StdClass;
	$page->xpath = &$xpath;
	$page->container = &$container;
	$page->tables470 = &$tables470;

	return $page;
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
function get_results( $page ) {
	$results = [];
	$callback = extract_text( 'td/b/text()[boolean(.)]' );
	$query = 'tr[(@bgcolor="#ffffff" or @bgcolor="#f8f8f8") and ' .
		'td[1][b[boolean(text())]]]';

	$columns = [ 'id', 'home', 'away', 'score' ];

	foreach( $page->tables470 as $table ) {
		$title = $page->xpath->query( 'tr/td[@class="Titulka"]/p', $table )
			->item( 0 )->nodeValue;

		$matches = get_rows_from_table( $page->xpath, $query, $table, $callback );
		$matches = array_map( function( $old_match ) use( $columns ) {
			$old_match = array_slice( $old_match, 0, 4 );
			$match = array_combine( $columns, $old_match );
			return $match;
		}, $matches );

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
function get_rankings( $page ) {
	$callback = extract_text( 'td/text()[boolean(.)]' );
	$query = 'tr[td[1][not(@colspan) and boolean(text()) ' .
			'and string-length(text()) <= 3]]';

	return get_rows_from_table( $page->xpath, $query, $page->tables470->item( 1 ),
		$callback );
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
function get_fixtures( $page ) {
	$fixtures = [];
	$callback = extract_text( 'td/text()[boolean(.)]' );
	$query = 'tr[td[1][not(@colspan) and boolean(text())]]';

	$columns = [ 'id', 'home', 'away', 'date' ];
	$time_zone = new DateTimeZone( 'Europe/Prague' );

	foreach( $page->tables470 as $table ) {
		$title = $page->xpath->query( 'tr/td[@class="Titulka"]/p//text()', $table )
			->item( 0 )->nodeValue;

		var_dump( $title );
		$year = explode( '.', $title )[3];

		$matches = get_rows_from_table( $page->xpath, $query, $table, $callback );
		$matches = array_map( function( $old_match ) use( $columns, $year,
			$time_zone )
		{
			$old_match = array_slice( $old_match, 0, 4 );
			$match = array_combine( $columns, $old_match );

			$match['date'] = DateTime::createFromFormat( 'd.m. H:i Y',
				$match['date'] . ' ' . $year, $time_zone );

			return $match;
		}, $matches );

		$fixtures[] = compact( 'title', 'matches' );
	}

	return $fixtures;
}

function get_last_round_matches( $page, $comp_id ) {
	$callback = extract_text( 'td/b/text()[boolean(.)]' );
	$query =
		'tr[td[1][not(@colspan) and b[starts-with(text(), "' . $comp_id . '")]]]';

	return get_rows_from_table( $page->xpath, $query, $page->tables470->item( 0 ),
		$callback );
}

function get_next_round_matches( $page, $comp_id ) {
	$callback = extract_text( 'td/text()[boolean(.)]' );
	$query =
		'tr[td[1][not(@colspan) and starts-with(text(), "' . $comp_id . '")]]';

	return get_rows_from_table( $page->xpath, $query, $page->tables470->item( 2 ),
		$callback );
}

function extract_text( $xpath_query ) {
	return function( DOMXPath $xpath, DOMElement $row ) use( $xpath_query ) {
			$info = [];

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
	$matches = [];

	$rows = $xpath->query( $xpath_query, $table );
	foreach( $rows as $row ) {
		$matches[] = $callback( $xpath, $row );
	}

	return $matches;
}

