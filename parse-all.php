<?php

// http://nv.fotbal.cz/adresare/adresar-klubu/viewadr.asp?detail=604024

error_reporting( E_ALL );
libxml_use_internal_errors( true );
require 'parser.php';

$competition = new competition( '624A2B' );

$rankings = scrape_latest_results( $competition );
#$fixtures = scrape_fixtures( $competition );
#$results = scrape_results( $competition );

#exit();

echo "<h2>$competition->title</h2>";

if( !empty( $rankings ) ) format_rankings( $rankings );

	echo "\n\n<i>Zdroj: <a href=\"$competition->uri\">FAČR</a></i>";
#if( !empty( $fixtures ) ) format_fixtures( $fixtures );
#if( !empty( $results ) ) format_results( $results );

# Ultimate
exit();

function format_rankings( $rankings ) {
	$columns = [ 'Tým', 'Z', '+', '0', '-', 'Skóre', 'Body' ];

	echo "\n\n", '<table class="rankings">', "\n",
		'<thead><tr><th>', implode( '</th><th>', $columns ), '</th></tr></thead>',
		"\n<tbody>\n";
	foreach( $rankings as $team ) {
		$add = ($team[1] === 'Klobouky') ? ' class="highlight"' : '';
		echo '<tr', $add, '><td class="team">', $team[1], '</td>';

		$other = array_slice( $team, 2 );
		// Remove last two columns
		array_pop( $other );
		array_pop( $other );
		// Remove spaces in score column
		$other[4] = str_replace( ' ', '', $other[4] );

		$other = array_map( function( $item ) { return "<td>$item</td>"; }, $other );

		echo implode( '', $other ), "</tr>\n";
	}
	echo "</tbody>\n</table>";
}

function format_results( $results ) {
    echo "\n\n", '<table class="fixtures">', "\n",
        "<thead><tr><th>Domácí</th><th>Hosté</th><th>Výsledek</th></tr></thead>\n",
        "<tbody>\n";
	foreach( $results as $round ) {
		foreach( $round['matches'] as $match ) {
			$id = $match['id'];
			$home = $match['home'];
			$away = $match['away'];
			$score = $match['score'];
            if( $home === 'Klobouky' || $away === 'Klobouky' ) {
                echo "<tr>\n\t", '<td class="home">' . $home . "</td>\n\t",
                    '<td class="away">' . $away . "</td>\n\t", '<td>' . $score .
                    "</td>\n</tr>\n";
				#$played[] = $id;
            }
		}
	}
    echo "</tbody>\n</table>";
}

function format_fixtures( $fixtures ) {
	$days_of_week = [ 'ne', 'po', 'út', 'st', 'čt', 'pá', 'so' ];

    echo "\n\n", '<table class="fixtures">', "\n",
        "<thead><tr><th>Domácí</th><th>Hosté</th><th>Termín</th></tr></thead>\n",
        "<tbody>\n";
	foreach( $fixtures as $round ) {
		foreach( $round['matches'] as $match ) {
			$id = $match['id'];
			$home = $match['home'];
			$away = $match['away'];
            if( $home === 'Klobouky' || $away === 'Klobouky' ) {
				/*
				if( in_array( $id, $played ) )
					continue;
				 */
				$date = $days_of_week[intval( $match['date']->format( 'w' ) )] .
					', ' . $match['date']->format( 'j. n. G:i' );

                echo "<tr>\n\t", '<td class="home">' . $home . "</td>\n\t",
                    '<td class="away">' . $away . "</td>\n\t", '<td>' . $date .
                    "</td>\n</tr>\n";
            }
		}
	}
    echo "</tbody>\n</table>";
}

/**
 * Scrape information from 'latest results' page (`show=Aktual`)
 */
function scrape_latest_results( competition &$comp ) {
	list( $rankings, $comp_title ) = scrape_page( $comp->uri . '&show=Aktual',
		[ 'get_rankings', 'get_competition_title' ] );
	$comp->title = $comp_title;

	#$last_round_matches = get_last_round_matches( $page, $comp_id );
	#$next_round_matches = get_next_round_matches( $page, $comp_id );

	return $rankings;
}

/**
 * Obtain fixtures (`show=Los`)
 */
function scrape_fixtures( competition $comp ) {
	list( $fixtures ) = scrape_page( $comp->uri . '&show=Los',
		[ 'get_fixtures' ] );

	return $fixtures;
}

/**
 * Obtain results of finished matches (`show=Vysledky`)
 */
function scrape_results( competition $comp ) {
	list( $results ) = scrape_page( $comp->uri . '&show=Vysledky',
		[ 'get_results' ] );

	return $results;
}

function scrape_page( $uri, array $scrapers ) {
	if( empty( $scrapers ) ) {
		throw new Exception( 'Cannot scrape without scrapers' );
	}

	$doc = new DOMDocument;
	if( !$doc->loadHTMLFile( $uri ) ) {
		throw new Exception( "Could not load page: $uri" );
	}
	error_log( "Page successfully loaded: $uri" );

	$page = get_page_object( $doc );
	$results = [];
	foreach( $scrapers as $i => $func ) {
		$results[$i] = call_user_func( $func, $page );
	}

	$page = null;
	$doc = null;

	return $results;
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

if( !empty( $rankings ) ) {
	printf( "%16s Z  +  0  -	Skore  B   (P)\n", 'Tym' );
	echo str_repeat( '-', 45 ), "\n";
	foreach( $rankings as $team ) {
		printf( "%3s %12s %-2s %-2s %-2s %-2s %5s %-2s %s\n", $team[0], $team[1],
			$team[2], $team[3], $team[4], $team[5], $team[6], $team[7], $team[9] );
	}
}
*/

