<?php
namespace Hail\DB;

use Hail\Facades\DB;
use Hail\Tracy\Dumper;
use Hail\Utils\Json;

class Event
{
	/** event type */
	const CONNECT = 'CONNECT',
		SELECT = 'SELECT',
		INSERT = 'INSERT',
		DELETE = 'DELETE',
		UPDATE = 'UPDATE',
		TRUNCATE = 'TRUNCATE',
		QUERY = 'QUERY', // SELECT | INSERT | DELETE | UPDATE
		TRANSACTION = 'TRANSACTION', // BEGIN | COMMIT | ROLLBACK
		ALL = 'ALL';

	/** @var string */
	protected $type;

	protected $storageType;
	protected $database;

	/** @var string */
	protected $sql;

	/** @var mixed */
	protected $result;

	/** @var float[] */
	protected $time;

	/** @var int */
	protected $count;

	protected $error = false;

	public function __construct($storageType, $database, $type = self::ALL)
	{
		$start = -microtime(true);
		$this->time = [
			'build' => $start,
			'query' => $start,
			'elapsed' => $start,
		];

		$this->storageType = $storageType;
		$this->database = $database;

		$this->type = $type;
	}

	public function error()
	{
		$this->error = true;
	}

	public function sql($sql, $build = true)
	{
		$time = microtime(true);

		if ($build) {
			$this->time['build'] += $time;
		}

		$this->time['query'] = -$time;
		$this->sql = $sql;

		if ($this->type === self::QUERY &&
			preg_match('#\(?\s*(SELECT|UPDATE|INSERT|DELETE|TRUNCATE|SET)#iA', $sql, $matches)
		) {
			$this->type = strtoupper($matches[1]);
		}
	}

	public function query()
	{
		$time = microtime(true);
		$this->time['query'] += $time;
		$this->time['fetch'] = -$time;
	}

	public function done($result = null)
	{
		$time = microtime(true);

		$this->result = $result;
		$this->count = count($result);

		if (isset($this->time['fetch'])) {
			$this->time['fetch'] += $time;
		}

		if ($this->time['query'] < 0) {
			$this->time['query'] += $time;
		}

		$this->time['elapsed'] += $time;

		Collector::add($this);
	}

	public function isError()
	{
		return $this->error;
	}

	/**
	 * Suggested behavior: print Tracy\Dumper::toHtml() array
	 * of returned rows so row count is immediately visible.
	 *
	 * @return NULL|string
	 */
	public function getResult()
	{
		if (!$this->result) {
			return null;
		}

		return Dumper::toHtml($this->result, [
			Dumper::COLLAPSE => true,
			Dumper::TRUNCATE => 50,
		]);
	}

	/**
	 * Arbitrary identifier such as mysql, postgres, elastic, neo4j
	 *
	 * @return string
	 */
	public function getStorageType()
	{
		return $this->storageType;

	}

	/**
	 * Database, fulltext index or similar, NULL if not applicable
	 *
	 * @return NULL|string
	 */
	public function getDatabaseName()
	{
		return $this->database;
	}

	/**
	 * Actual formatted query, e.g. 'SELECT * FROM ...'
	 *
	 * @return string
	 */
	public function getQuery()
	{
		return $this->dump($this->sql);
	}

	/**
	 * @return NULL|float ms
	 */
	public function getBuildTime()
	{
		return $this->time['build'] > 0 ? $this->time['build'] * 1000 : null;
	}

	/**
	 * @return NULL|float ms
	 */
	public function getQueryTime()
	{
		return $this->time['query'] > 0 ? $this->time['query'] * 1000 : null;
	}

	/**
	 * @return NULL|float ms
	 */
	public function getFetchTime()
	{
		return isset($this->time['fetch']) ? $this->time['fetch'] * 1000 : null;
	}

	/**
	 * @return NULL|float ms
	 */
	public function getElapsedTime()
	{
		return $this->time['elapsed'] * 1000;
	}

	public function getType()
	{
		return $this->type;
	}

	/**
	 * e.g. SQL explain
	 *
	 * @return NULL|string
	 * @throws \Exception
	 */
	public function getInfo()
	{
		if ($this->error || !$this->sql) {
			return null;
		}

		$query = 'EXPLAIN FORMAT=JSON ' . $this->sql;
		$query = DB::query($query);

		$data = [];
		if ($query) {
			$data = $query->fetch();
		}

		DB::release();

		$data = Json::decode($data['EXPLAIN'] ?? '[]');

		return Dumper::toHtml($data, [
			Dumper::COLLAPSE => true,
			Dumper::DEPTH => 6,
		]);
	}

	/**
	 * Prints out a syntax highlighted version of the SQL command or Result.
	 *
	 * @param  string|Result
	 *
	 * @return string
	 */
	public function dump($sql = null)
	{
		static $keywords1 = 'SELECT|(?:ON\s+DUPLICATE\s+KEY)?UPDATE|INSERT(?:\s+INTO)?|REPLACE(?:\s+INTO)?|DELETE|CALL|UNION|FROM|WHERE|HAVING|GROUP\s+BY|ORDER\s+BY|LIMIT|OFFSET|FETCH\s+NEXT|SET|VALUES|LEFT\s+JOIN|INNER\s+JOIN|TRUNCATE|START\s+TRANSACTION|BEGIN|COMMIT|ROLLBACK(?:\s+TO\s+SAVEPOINT)?|(?:RELEASE\s+)?SAVEPOINT';
		static $keywords2 = 'ALL|DISTINCT|DISTINCTROW|IGNORE|AS|USING|ON|AND|OR|IN|IS|NOT|NULL|LIKE|RLIKE|REGEXP|TRUE|FALSE';

		// insert new lines
		$sql = " $sql ";
		$sql = preg_replace("#(?<=[\\s,(])($keywords1)(?=[\\s,)])#i", "\n\$1", $sql);
		// reduce spaces
		$sql = preg_replace('#[ \t]{2,}#', ' ', $sql);
		$sql = wordwrap($sql, 100);
		$sql = preg_replace("#([ \t]*\r?\n){2,}#", "\n", $sql);
		// syntax highlight
		$highlighter = "#(/\\*.+?\\*/)|(\\*\\*.+?\\*\\*)|(?<=[\\s,(])($keywords1)(?=[\\s,)])|(?<=[\\s,(=])($keywords2)(?=[\\s,)=])#is";

		$sql = htmlSpecialChars($sql);
		$sql = preg_replace_callback($highlighter, function ($m) {
			if (!empty($m[1])) { // comment
				return '<em style="color:gray">' . $m[1] . '</em>';
			} elseif (!empty($m[2])) { // error
				return '<strong style="color:red">' . $m[2] . '</strong>';
			} elseif (!empty($m[3])) { // most important keywords
				return '<strong style="color:blue">' . $m[3] . '</strong>';
			} elseif (!empty($m[4])) { // other keywords
				return '<strong style="color:green">' . $m[4] . '</strong>';
			}
		}, $sql);

		return '<pre class="dump">' . trim($sql) . "</pre>\n\n";
	}
}