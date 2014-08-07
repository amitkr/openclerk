<?php

/*
// average market prices
if (substr($graph['graph_type'], -strlen("_markets")) == "_markets") {
	$split = explode("_", $graph['graph_type']);
	if (count($split) == 3 && strlen($split[1]) == 6) {
		// if this is an average graph, go straight away
		if ($split[0] == "average") {
			// we won't check pairs, we just won't get any data
			$currency1 = substr($split[1], 0, 3);
			$currency2 = substr($split[1], 3);
			$q = db()->prepare("SELECT * FROM ticker_recent WHERE currency1=? AND currency2=? ORDER BY volume DESC");
			$q->execute(array($currency1, $currency2));
			$tickers = $q->fetchAll();

			$q = db()->prepare("SELECT * FROM average_market_count WHERE currency1=? AND currency2=?");
			$q->execute(array($currency1, $currency2));
			$market_count = $q->fetch();

			render_average_markets_table($graph, $tickers, $market_count);
			break;
		}
	}
}
*/

class GraphRenderer_AverageMarketData extends GraphRenderer {

	var $currency1;
	var $currency2;

	public function __construct($currency1, $currency2) {
		$this->currency1 = $currency1;
		$this->currency2 = $currency2;
	}

	public function getTitle() {
		return ct(":exchange :pair exchange data");
	}

	public function getTitleArgs() {
		return array(
			':exchange' => get_exchange_name("average"),
			':pair' => $pp,
		);
	}

	public function getURL() {
		return url_for('average#average_' .  $this->currency1, array(
			'currency1' => $this->currency1,
			'currency2' => $this->currency2,
		));
	}

	public function getLabel() {
		return ct("View market averages");
	}

	public function hasSubheading() {
		// do not try to calculate subheadings!
		return false;
	}

	public function canHaveTechnicals() {
		// do not try to calculate technicals; this also resorts the data by first key
		return false;
	}

	public function getChartType() {
		return "vertical";
	}

	function getClasses() {
		return "graph_average";
	}

	public function getData($days) {
		$columns = array();

		$columns[] = array('type' => 'string', 'title' => ct("Exchange"));
		$columns[] = array('type' => 'string', 'title' => ct("Exchange"), 'heading' => true);
		$columns[] = array('type' => 'string', 'title' => ct("Pair"));
		$columns[] = array('type' => 'string', 'title' => ct("Volume"));

		$q = db()->prepare("SELECT * FROM ticker_recent WHERE currency1=? AND currency2=? ORDER BY volume DESC");
		$q->execute(array($this->currency1, $this->currency2));
		$tickers = $q->fetchAll();

		$q = db()->prepare("SELECT * FROM average_market_count WHERE currency1=? AND currency2=?");
		$q->execute(array($this->currency1, $this->currency2));
		$market_count = $q->fetch();

		$average = false;
		foreach ($tickers as $ticker) {
			if ($ticker['exchange'] == 'average') {
				$average = $ticker;
			}
		}
		if (!$average) {
			throw new RenderGraphException(t("Could not find any average data"));
		}

		$volume_currency = $average['currency2'];

		// generate the table of data
		$data = array();
		foreach ($tickers as $ticker) {
			if ($ticker['exchange'] == "average") {
				continue;
			}
			if ($ticker['volume'] == 0) {
				continue;
			}

			$id = $ticker['exchange'] . "_" . $ticker['currency1'] . $ticker['currency2'] . "_daily";
			$data[$ticker['exchange']] = array(
				"<a href=\"" . htmlspecialchars(url_for('historical', array('id' => $id, 'days' => 180))) . "\">" . get_exchange_name($ticker['exchange']) . "</a>",
				average_currency_format_html($ticker['last_trade'], $ticker['last_trade']),
				currency_format($volume_currency, $ticker['volume'], 0) . " (" .
					($average['volume'] == 0 ? "-" : (number_format($ticker['volume'] * 100 / $average['volume']) . "%")) . ")",
			);
		}

		$last_updated = $average['created_at'];

		return array(
			'columns' => $columns,
			'data' => $data,
			'last_updated' => $last_updated,

			// special values that we can only access at runtime
			'h1' => get_currency_abbr($average['currency1']) . "/" . get_currency_abbr($average['currency2']) . ": " .
						currency_format($average['currency1'], $average['last_trade']),
			'h2' => "(" . number_format($average['volume']). " " . get_currency_abbr($volume_currency) . " total volume)",
		);

/*
?>
<div class="graph_average">
	<h1><?php echo get_currency_abbr($average['currency1']) . "/" . get_currency_abbr($average['currency2']); ?>:
		<?php echo currency_format($average['currency1'], $average['last_trade']); ?></h1>
	<h2>(<?php echo number_format($average['volume']); ?> <?php echo get_currency_abbr($volume_currency); ?> total volume)</h2>

	<?php render_table_vertical($graph, $data, $head); ?>
</div>

*/

	}

}