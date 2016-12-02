<?php
/**
 * Created by IntelliJ IDEA.
 * User: Hao
 * Date: 2016/11/30 0030
 * Time: 18:52
 */

namespace Hail\Tracy\Bar;


use Hail\DB\Collector;
use Hail\DB\Event;

class QueryPanel implements PanelInterface
{
	/** @var array [float $min, float $max] */
	private $extremes;

	/**
	 * @return string
	 * @internal
	 */
	public function getTitle()
	{
		$c = Collector::count();
		if ($c === 0)
		{
			$title = 'no queries';
		}
		else if ($c === 1)
		{
			$title = '1 query';
		}
		else
		{
			$title = "$c queries";
		}
		return "$title, " . number_format(Collector::getTotalElapsedTime(), 1) . '&nbsp;ms';
	}
	/**
	 * Renders HTML code for custom tab.
	 *
	 * @return string
	 * @internal
	 */
	public function getTab()
	{
		ob_start(function () {});
		$title = $this->getTitle();
		require __DIR__ . '/templates/query.tab.phtml';
		return ob_get_clean();
	}
	/**
	 * Renders HTML code for custom panel.
	 *
	 * @return string html
	 * @internal
	 */
	public function getPanel()
	{
		ob_start(function () {});
		$title = $this->getTitle();
		$aggregations = Collector::getAggregations();
		$queries = Collector::get();
		if ($queries)
		{
			$this->extremes = Collector::getTimeExtremes();
		}
		require __DIR__ . '/templates/query.panel.phtml';
		return ob_get_clean();
	}
	/**
	 * @internal
	 * @param Event $query
	 * @return string
	 * @internal
	 */
	public function getStorageId($query)
	{
		if ($query instanceof Event) {
			return $query->getStorageType() . '|' . $query->getDatabaseName();
		}

		return $query['storageType'] . '|' . $query['databaseName'];
	}
	/**
	 * Linear color gradient
	 * @param float $value
	 * @return string hex color
	 * @internal
	 */
	public function getColorInRange($value)
	{
		$a = [54, 170, 31];
		$b = [220, 1, 57];
		list($min, $max) = $this->extremes;
		$d = $max - $min;
		$lin = ($value - $min) / ($d ?: 0.5); // prevent x/0
		$color = [];
		for ($i = 0; $i < 3; ++$i)
		{
			$color[$i] = (int) ($a[$i] + ($b[$i] - $a[$i]) * $lin);
		}
		return 'rgb(' . implode(',', $color) . ')';
	}
}