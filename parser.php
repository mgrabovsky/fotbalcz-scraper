<?php

namespace Fotbalcz;

class Fotbalcz {
	private $group_id;
	private $fetcher;

	public function __construct($group_id, $options = []) {
		$this->group_id = $group_id;

		$doc_fetcher = 'Fotbalcz\UrlFetcher';
		if (isset($options['document_fetcher']) &&
			class_exists($options['document_fetcher']))
		{
			$doc_fetcher = $options['document_fetcher'];
		}

		$this->fetcher = new $doc_fetcher($this->group_id);
	}

	public function get_rankings() {
		$doc  = $this->fetcher->fetch_rankings();
		$page = $this->get_page_object($doc);
		return $this->get_rankings_inner($page);
	}

	public function get_results() {
		$doc  = $this->fetcher->fetch_results();
		$page = $this->get_page_object($doc);
		return $this->get_results_inner($page);
	}

	public function get_fixtures() {
		$doc  = $this->fetcher->fetch_fixtures();
		$page = $this->get_page_object($doc);
		return $this->get_fixtures_inner($page);
	}

	private function get_rankings_inner($page) {
		$callback = $this->extract_text('td/text()[boolean(.)]');
		$query = 'tr[td[1][not(@colspan) and boolean(text()) and string-length(text()) <= 3]]';

		$columns = ['rank', 'name', 'matches', 'wins', 'draws', 'losses', 'score',
			'points', 'pk', 'p'];

		$rows = $this->get_rows_from_table($page->xpath, $query, $page->tables470->item(1),
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
	
	private function get_results_inner($page) {
		$results = [];
		$callback = $this->extract_text('td');
		$rows_query = 'tr[not(@bgcolor) and td[1][not(@bgcolor) and b[boolean(text())]]]';

		$columns = ['id', 'home', 'away', 'score', 'spectators', 'notes'];

		foreach ($page->tables470 as $table) {
			$title = $page->xpath->evaluate('string(tr/td[@class="Titulka"]/p)', $table);

			$matches = $this->get_rows_from_table($page->xpath, $rows_query, $table, $callback);
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

	private function get_fixtures_inner($page) {
		$fixtures = [];
		$callback = $this->extract_text('td/text()[boolean(.)]');
		$query = 'tr[td[1][not(@colspan) and boolean(text())]]';

		$columns = ['id', 'home', 'away', 'date'];
		$time_zone = new \DateTimeZone('Europe/Prague');

		foreach ($page->tables470 as $table) {
			$title = $page->xpath->evaluate('string(tr/td[@class="Titulka"]/p)', $table);

			$year = explode('.', $title)[3];

			$matches = $this->get_rows_from_table($page->xpath, $query, $table, $callback);
			$matches = array_map(function($old_match) use($columns, $year,
				$time_zone)
			{
				$old_match = array_slice($old_match, 0, 4);
				$match = array_combine($columns, $old_match);

				$match['date'] = \DateTime::createFromFormat('d.m. H:i Y',
					$match['date'] . ' ' . $year, $time_zone);

				return $match;
			}, $matches);

			$fixtures[] = compact('title', 'matches');
		}

		return $fixtures;
	}

	private function &get_page_object(\DOMDocument $doc) {
		$xpath = new \DOMXPath($doc);
		$container = $xpath->query('id("maincontainer")/table[@height="300"]//td[@width="500"]')->item(0);
		$tables470 = $xpath->query('.//table[@width="470"]', $container);

		$page = new \StdClass;
		$page->xpath = &$xpath;
		$page->container = &$container;
		$page->tables470 = &$tables470;

		return $page;
	}

	private function extract_text($query) {
		return function(\DOMXPath $xpath, \DOMElement $row) use($query) {
				$info = [];

				$texts = $xpath->query($query, $row);
				foreach ($texts as $text) {
					$info[] = $text->nodeValue;
				}

				return $info;
			};
	}

	private function get_rows_from_table(\DOMXPath $xpath, $rows_query,
		\DOMElement $table, $callback)
	{
		$matches = [];

		$rows = $xpath->query($rows_query, $table);
		foreach ($rows as $row) {
			$matches[] = $callback($xpath, $row);
		}

		return $matches;
	}
}

interface DocumentFetcher {
	public function fetch_rankings();
	public function fetch_results();
	public function fetch_fixtures();
}

class UrlFetcher implements DocumentFetcher {
	public function __construct($group_id) {
		$this->group_id = $group_id;
	}

	public function fetch_rankings() {
		return $this->load_document('Aktual');
	}

	public function fetch_results() {
		return $this->load_document('Vysledky');
	}

	public function fetch_fixtures() {
		return $this->load_document('Los');
	}

	private function &load_document($page) {
		$url = "http://nv.fotbal.cz/domaci-souteze/kao/souteze.asp" .
			"?soutez={$this->group_id}&show=$page";
		$doc = new \DOMDocument;
		if (!$doc->loadHTMLFile($url)) {
			throw new \Exception("Could not load page: $url");
		}
		error_log("Loaded page: $url");

		return $doc;
	}
}

class FileFetcher extends UrlFetcher {
	public function fetch_rankings() {
		return $this->load_document('Aktual');
	}

	public function fetch_results() {
		return $this->load_document('Vysledky');
	}

	public function fetch_fixtures() {
		return $this->load_document('Los');
	}

	private function &load_document($file_stem) {
		$path = realpath("fotbal.cz/$file_stem.html");
		$doc  = new \DOMDocument;
		if (!$doc->loadHTMLFile($path)) {
			throw new \Exception("Could not load file: $path");
		}
		error_log("Loaded file: $path");

		return $doc;
	}
}

