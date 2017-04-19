<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Hail\Cache\Simple;

use Hail\Facade\Serialize;
use MongoBinData;
use MongoCollection;
use MongoCursorException;
use MongoDate;

/**
 * MongoDB cache provider.
 *
 * @since  1.1
 * @author Jeremy Mikola <jmikola@gmail.com>
 * @author Hao Feng <flyinghail@msn.com>
 */
class MongoDB extends AbstractAdapter
{
	/**
	 * The data field will store the serialized PHP value.
	 */
	const DATA_FIELD = 'd';

	/**
	 * The expiration field will store a MongoDate value indicating when the
	 * cache entry should expire.
	 *
	 * With MongoDB 2.2+, entries can be automatically deleted by MongoDB by
	 * indexing this field with the "expireAfterSeconds" option equal to zero.
	 * This will direct MongoDB to regularly query for and delete any entries
	 * whose date is older than the current time. Entries without a date value
	 * in this field will be ignored.
	 *
	 * The cache provider will also check dates on its own, in case expired
	 * entries are fetched before MongoDB's TTLMonitor pass can expire them.
	 *
	 * @see http://docs.mongodb.org/manual/tutorial/expire-data/
	 */
	const EXPIRATION_FIELD = 'e';

	/**
	 * @var MongoCollection
	 */
	private $collection;

	/**
	 * Constructor.
	 *
	 * This provider will default to the write concern and read preference
	 * options set on the MongoCollection instance (or inherited from MongoDB or
	 * MongoClient). Using an unacknowledged write concern (< 1) may make the
	 * return values of delete() and save() unreliable. Reading from secondaries
	 * may make contain() and fetch() unreliable.
	 *
	 * @see http://www.php.net/manual/en/mongo.readpreferences.php
	 * @see http://www.php.net/manual/en/mongo.writeconcerns.php
	 *
	 * @param array $params [collection => MongoCollection]
	 */
	public function __construct($params)
	{
		$this->collection = $params['collection'] ?? null;
		parent::__construct($params);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doGet(string $key)
	{
		$document = $this->collection->findOne(['_id' => $key], [self::DATA_FIELD, self::EXPIRATION_FIELD]);

		if ($document === null) {
			return null;
		}

		if ($this->isExpired($document)) {
			$this->doDelete($key);

			return null;
		}

		return Serialize::decode($document[self::DATA_FIELD]->bin);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doHas(string $key)
	{
		$document = $this->collection->findOne(['_id' => $key], [self::EXPIRATION_FIELD]);

		if ($document === null) {
			return false;
		}

		if ($this->isExpired($document)) {
			$this->doDelete($key);

			return false;
		}

		return true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doSet(string $key, $value, int $ttl = 0)
	{
		try {
			$result = $this->collection->update(
				['_id' => $key],
				[
					'$set' => [
						self::EXPIRATION_FIELD => $ttl > 0 ? new MongoDate(NOW + $ttl) : null,
						self::DATA_FIELD => new MongoBinData(
							Serialize::encode($value), MongoBinData::BYTE_ARRAY
						),
					],
				],
				['upsert' => true, 'multiple' => false]
			);
		} catch (MongoCursorException $e) {
			return false;
		}

		return isset($result['ok']) ? $result['ok'] == 1 : true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doDelete(string $key)
	{
		$result = $this->collection->remove(['_id' => $key]);

		return isset($result['ok']) ? $result['ok'] == 1 : true;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function doClear()
	{
		// Use remove() in lieu of drop() to maintain any collection indexes
		$result = $this->collection->remove();

		return isset($result['ok']) ? $result['ok'] == 1 : true;
	}

	/**
	 * Check if the document is expired.
	 *
	 * @param array $document
	 *
	 * @return bool
	 */
	private function isExpired(array $document)
	{
		return isset($document[self::EXPIRATION_FIELD]) &&
			$document[self::EXPIRATION_FIELD] instanceof MongoDate &&
			$document[self::EXPIRATION_FIELD]->sec < time();
	}
}
