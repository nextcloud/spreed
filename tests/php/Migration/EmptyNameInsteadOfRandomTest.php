<?php
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Spreed\Tests\php\Migration;


use OCA\Spreed\Migration\EmptyNameInsteadOfRandom;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * @group DB
 */
class EmptyNameInsteadOfRandomTest extends TestCase {

	/** @var IDBConnection */
	protected $connection;

	/** @var IConfig|\PHPUnit_Framework_MockObject_MockObject */
	protected $config;

	/** @var IGroupManager|\PHPUnit_Framework_MockObject_MockObject */
	protected $groupManager;

	/** @var EmptyNameInsteadOfRandom */
	protected $step;

	protected function setUp() {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->config = $this->createMock(IConfig::class);
		$this->groupManager = $this->createMock(IGroupManager::class);

		$this->step = new EmptyNameInsteadOfRandom($this->connection, $this->config, $this->groupManager);
		$this->clearRoomsTable();
	}

	protected function tearDown() {
		$this->clearRoomsTable();
		parent::tearDown();
	}

	protected function clearRoomsTable() {
		$query = $this->connection->getQueryBuilder();
		$query->delete('spreedme_rooms')
			->execute();
	}

	public function testGetName() {
		$this->assertInternalType('string', $this->step->getName());
		$this->assertNotEmpty($this->step->getName());
	}

	public function dataRun() {
		return [
			['1.1.4', null, null, null],
			['1.1.3', 'abcdefghijkl', true, 'abcdefghijkl'],
			['1.1.2', 'abcdefghijkl', false, ''],
			['1.1.1', 'admin', null, 'admin'],
		];
	}

	/**
	 * @dataProvider dataRun
	 * @param string $version
	 * @param string|null $originalName
	 * @param bool|null $isGroup
	 * @param string|null $updatedName
	 */
	public function testRun($version, $originalName, $isGroup, $updatedName) {
		$this->config->expects($this->once())
			->method('getAppValue')
			->with('spreed', 'installed_version', '0.0.0')
			->willReturn($version);

		if ($originalName === null || $isGroup === null) {
			$this->groupManager->expects($this->never())
				->method('groupExists');
		} else {
			$this->groupManager->expects($this->once())
				->method('groupExists')
				->with($originalName)
				->willReturn($isGroup);
		}

		$query = $this->connection->getQueryBuilder();
		$query->insert('spreedme_rooms')
			->values([
				'name' => $query->createNamedParameter($originalName),
				'type' => $query->createNamedParameter(1)
			]);
		$query->execute();
		$id = $query->getLastInsertId();

		$this->step->run($this->createMock(IOutput::class));

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms')
			->where($query->expr()->eq('id', $query->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$result = $query->execute();
		$row = $result->fetch();
		$result->closeCursor();

		$this->assertSame($updatedName, $row['name']);
	}
}
