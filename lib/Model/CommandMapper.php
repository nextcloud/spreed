<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class CommandMapper extends QBMapper {

	/**
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'talk_commands', Command::class);
	}

	/**
	 * @return Command[]
	 */
	public function findAll(): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->orderBy('id', 'ASC');

		return $this->findEntities($query);
	}

	/**
	 * @param int $id
	 * @return Command
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function findById(int $id): Command {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($id)));

		return $this->findEntity($query);
	}

	/**
	 * @param string $app
	 * @return Command[]
	 */
	public function findByApp(string $app): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('app', $query->createNamedParameter($app)))
			->orderBy('id', 'ASC');

		return $this->findEntities($query);
	}

	/**
	 * @param string $app
	 * @param string $cmd
	 * @return Command
	 * @throws \OCP\AppFramework\Db\DoesNotExistException
	 */
	public function find(string $app, string $cmd): Command {
		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from($this->getTableName())
			->where($query->expr()->eq('command', $query->createNamedParameter($cmd)));

		if ($app === '') {
			$query->andWhere($query->expr()->emptyString('app'));
		} else {
			$query->andWhere($query->expr()->eq('app', $query->createNamedParameter($app)));
		}

		return $this->findEntity($query);
	}
}
