<?php

/**
 * VirtEx ticker job.
 * VirtEx does not actually have an API (yet), so we have to do screen scraping.
 */

$exchange_name = "virtex";
$get_exchange_pairs = get_exchange_pairs();

require(__DIR__ . '/../../inc/html5lib/Parser.php');

function virtex_table_first_row($xml, $table_id, $expected = 4) {
	$orderbook = $xml->xpath("//html:div[@id='$table_id']/html:table/html:tbody/html:tr");
	if (!$orderbook) {
		$orderbook = $xml->xpath("//html:table[@id='$table_id']/html:tbody/html:tr");
	}
	$last_trade = false;
	foreach ($orderbook as $ob) {
		$ob->registerXPathNamespace('html', 'http://www.w3.org/1999/xhtml');
		if ($ob->xpath("html:th")) {
			continue;
		}
		// try <b> first
		$queries = array("html:td/html:b", "html:td");
		foreach ($queries as $q) {
			$nodes = $ob->xpath($q);
			if (!$nodes)
				continue;
			crypto_log("First $table_id row: " . implode(",", $nodes));
			if (count($nodes) == $expected) {
				$r = array();
				for ($i = 0; $i < $expected; $i++) {
					$r[] = (string) $nodes[$i];
				}
				return $r;
			} else {
				throw new ExternalAPIException("Expected $expected rows in $table_id table, found " . implode(",", $nodes));
			}
		}
	}
	throw new ExternalAPIException("Found no first row in table '$table_id'");
}


$first = true;
foreach ($get_exchange_pairs['virtex'] as $pair) {
	// sleep between requests
	if (!$first) {
		set_time_limit(30 + (get_site_config('sleep_virtex_ticker') * 2));
		sleep(get_site_config('sleep_virtex_ticker'));
	}
	$first = false;

	// they're swapped around
	$currency1 = $pair[1];
	$currency2 = $pair[0];

	if ($currency1 == "ltc" && $currency2 == "btc") {
		$url = "https://www.cavirtex.com/orderbook?filterby=" . get_currency_abbr($currency2) . get_currency_abbr($currency1);
	} else {
		$url = "https://www.cavirtex.com/orderbook?filterby=" . get_currency_abbr($currency1) . get_currency_abbr($currency2);		
	}
	$html = crypto_get_contents(crypto_wrap_url($url));

	// this doesn't return valid XHTML, so we use the HTML5 parser as a temporary solution
	$dom = HTML5_Parser::parse($html);

	// now load as XML
	// crypto_log($dom->saveXML());
	$xml = new SimpleXMLElement($dom->saveXML());
	$xml->registerXPathNamespace('html', 'http://www.w3.org/1999/xhtml');

	$last_trade_row = virtex_table_first_row($xml, 'orderbook_trades');
	crypto_log("Found last_trade " . print_r($last_trade_row, true));

	$buy_row = virtex_table_first_row($xml, 'orderbook_buy');
	crypto_log("Found buy " . print_r($buy_row, true));

	$sell_row = virtex_table_first_row($xml, 'orderbook_sell');
	crypto_log("Found sell " . print_r($sell_row, true));

	// virtex HTML no longer includes 24h volume or anything like that

	$last_trade = $last_trade_row[3];
	$buy = $buy_row[2];
	$sell = $sell_row[2];

	if ($currency1 == "ltc" && $currency2 == "btc") {
		// LTC/BTC is swapped around
		$last_trade = 1 / $last_trade;
		$tmp = 1 / $buy;
		$buy = 1 / $sell;
		$sell = $tmp;
	}

	insert_new_ticker($job, $exchange, $currency1, $currency2, array(
		"last_trade" => $last_trade,
		// Virtex returns buy/sell in the incorrect order
		"bid" => $buy,
		"ask" => $sell,
		// "volume" => $volume,
	));

}
