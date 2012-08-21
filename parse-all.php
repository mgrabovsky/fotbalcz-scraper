<?php

// http://nv.fotbal.cz/adresare/adresar-klubu/viewadr.asp?detail=604024

error_reporting( E_ALL );
require 'parser.php';

#$input_uri = realpath( 'fixtures.html' );
$input_uri = make_fotbalcz_uri( array(
	'kraj'   => 'jihomoravsky',
	'okres'  => 'breclav',
	'soutez' => '624E2A',
) );

/**
 * First obtain information from index page (&show=Aktual)
 */
libxml_use_internal_errors( true );
$doc = new DOMDocument;
if( !$doc->loadHTMLFile( $input_uri . '&show=Aktual' ) ) {
	error_log( 'Could not load index page' );
	exit( 1 );
}

error_log( 'Index page successfully loaded' );

$xpath = new DOMXPath( $doc );
$container = $xpath->query(
	'//div[@id="maincontainer"]/table[@height="300"]//td[2]' )->item( 0 );
$tables470 = $xpath->query( './/table[@width="470"]', $container );

$comp_title = get_competition_title( $xpath, $container );
$comp_id = strstr( $comp_title, ' ', true );

//* On index page
/*
$last_round_matches = get_last_round_matches( $xpath, $tables470->item( 0 ),
	$comp_id );
$next_round_matches = get_next_round_matches( $xpath, $tables470->item( 2 ),
	$comp_id );
 */
$rankings_table = get_rankings_table( $xpath, $tables470->item( 1 ) );
//*/

/**
 * Next, obtain fixtures (&show=Los)
 */
$doc = null;
$xpath = null;
$doc = new DOMDocument;
if( !$doc->loadHTMLFile( $input_uri . '&show=Los' ) ) {
	error_log( 'Could not load fixtures page' );
	exit( 1 );
}
error_log( 'Fixtures page successfully loaded' );

$xpath = new DOMXPath( $doc );
$container = $xpath->query(
	'//div[@id="maincontainer"]/table[@height="300"]//td[2]' )->item( 0 );
$tables470 = $xpath->query( './/table[@width="470"]', $container );

//* On 'Fixtures' page
$fixtures = get_fixtures( $xpath, $tables470, $comp_id );
$fixtures = array_slice( $fixtures, 0, 14 );
//*/

/**
 * At last, obtain results (&show=Vysledky)
 */
$doc = null;
$xpath = null;
$doc = new DOMDocument;
if( !$doc->loadHTMLFile( $input_uri . '&show=Vysledky' ) ) {
	error_log( 'Could not load results page' );
	exit( 1 );
}
error_log( 'Results page successfully loaded' );

$xpath = new DOMXPath( $doc );
$container = $xpath->query(
	'//div[@id="maincontainer"]/table[@height="300"]//td[2]' )->item( 0 );
$tables470 = $xpath->query( './/table[@width="470"]', $container );

//* On 'All Results' page
$results = get_results( $xpath, $tables470, $comp_id );
$results = array_slice( $results, 0, 14 );
//*/

echo "<h2>$comp_title</h2>\n",
	'<i>Zdroj: <a href="' . $input_uri . '">FAČR</a></i>';

if( !empty( $rankings_table ) ) {
	echo "\n\n", '<table class="rankings">', "\n",
		'<thead><tr><th>Tým</th><th>Z</th><th>+</th><th>0</th><th>-</th><th>Skóre</th>',
		'<th>B</th><th>(P)</th></tr></thead>', "\n",
		"<tbody>\n";
	foreach( $rankings_table as $team ) {
		$add = ($team[1] === 'Klobouky') ? ' class="highlight"' : '';
		echo '<tr', $add, '><td class="team">', $team[1], '</td>';

		$other = array_slice( $team, 2 );
		unset( $other[6] );
		$other = array_map( function( $item ) { return "<td>$item</td>"; }, $other );

		echo implode( '', $other ), "</tr>\n";
	}
	echo "</tbody>\n</table>";
}

if( !empty( $results ) ) {
	foreach( $results as $round ) {
		echo "\n\n<h3>" . $round['title'], "</h3>\n",
			'<table class="fixtures">' . "\n",
			"<thead><tr><th>Domácí</th><th>Hosté</th><th>Skóre</th></tr></thead>\n" .
			"<tbody>\n";
		foreach( $round['matches'] as $match ) {
			list( $id, $host, $guest, $score ) = $match;

			$add = '';
			if( $host === 'Klobouky' || $guest === 'Klobouky' )
				$add = ' class="highlight"';

			echo '<tr' . $add . '><td class="host">' . $host . '</td><td class="guest">' .
			   $guest . '</td><td>'	. $score . "</td></tr>\n";
		}
		echo "</tbody>\n</table>";
	}
}

if( !empty( $fixtures ) ) {
	foreach( $fixtures as $round ) {
		echo "\n\n<h3>" . $round['title'], "</h3>\n",
			'<table class="fixtures">' . "\n",
			"<thead><tr><th>Domácí</th><th>Hosté</th><th>Termín</th></tr></thead>\n" .
			"<tbody>\n";
		foreach( $round['matches'] as $match ) {
			list( $id, $host, $guest ) = $match;
			$date = mb_strtolower( $match[4], 'UTF-8' ) . ' ' . $match[3];

			$add = '';
			if( $host === 'Klobouky' || $guest === 'Klobouky' )
				$add = ' class="highlight"';

			echo '<tr' . $add . '><td class="host">' . $host . '</td><td class="guest">' .
			   $guest . '</td><td>'	. $date . "</td></tr>\n";
		}
		echo "</tbody>\n</table>";
	}
}

/*
echo $comp_title, "\n\n";

if( !empty( $fixtures ) ) {
	printf( "%22s	%-12s %-13s\n", 'Domácí', 'Hosté', 'Termín' );
	foreach( $fixtures as $match ) {
		echo $match['title'], "\n";
		foreach( $match['matches'] as $match ) {
			printf( "(%s) %12s v. %-12s %s, %s\n", $match[0], $match[1], $match[2],
				strtolower( $match[4] ), $match[3] );
		}
		echo "\n";
	}
}

if( !empty( $results ) ) {
	printf( "%22s	%-12s %-13s\n", 'Domácí', 'Hosté', 'Termín' );
	foreach( $results as $match ) {
		echo $match['title'], "\n";
		foreach( $match['matches'] as $match ) {
			printf( "(%s) %12s v. %-12s %s, %s\n", $match[0], $match[1], $match[2],
				strtolower( $match[4] ), $match[3] );
		}
		echo "\n";
	}
}

if( !empty( $last_round_matches ) ) {
	foreach( $last_round_matches as $match ) {
		printf( "(%s) %12s v. %-12s %s\n", $match[0], $match[1], $match[2],
			$match[3] );
	}
	echo "\n";
}

if( !empty( $next_round_matches ) ) {
	foreach( $next_round_matches as $match ) {
		printf( "(%s) %12s v. %-12s %s, %s\n", $match[0], $match[1], $match[2],
			strtolower( $match[4] ), $match[3] );
	}
	echo "\n";
}

if( !empty( $rankings_table ) ) {
	printf( "%16s Z  +  0  -	Skore  B   (P)\n", 'Tym' );
	echo str_repeat( '-', 45 ), "\n";
	foreach( $rankings_table as $team ) {
		printf( "%3s %12s %-2s %-2s %-2s %-2s %5s %-2s %s\n", $team[0], $team[1],
			$team[2], $team[3], $team[4], $team[5], $team[6], $team[7], $team[9] );
	}
}
*/

