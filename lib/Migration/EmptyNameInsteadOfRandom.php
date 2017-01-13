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

namespace OCA\Spreed\Migration;


use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IGroupManager;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

class EmptyNameInsteadOfRandom implements IRepairStep {

	/** @var IDBConnection */
	protected $connection;

	/** @var IConfig */
	protected $config;

	/** @var IGroupManager */
	protected $groupManager;

	/**
	 * @param IDBConnection $connection
	 * @param IConfig $config
	 * @param IGroupManager $groupManager
	 */
	public function __construct(IDBConnection $connection, IConfig $config, IGroupManager $groupManager) {
		$this->connection = $connection;
		$this->config = $config;
		$this->groupManager = $groupManager;
	}

	/**
	 * Returns the step's name
	 *
	 * @return string
	 * @since 9.1.0
	 */
	public function getName() {
		return 'Remove random call names';
	}

	/**
	 * Run repair step.
	 * Must throw exception on error.
	 *
	 * @since 9.1.0
	 * @param IOutput $output
	 * @throws \Exception in case of failure
	 */
	public function run(IOutput $output) {
		if (!version_compare($this->config->getAppValue('spreed', 'installed_version', '0.0.0'), '1.1.4', '<')) {
			return;
		}

		$update = $this->connection->getQueryBuilder();
		$update->update('spreedme_rooms')
			->set('name', $update->createNamedParameter(''))
			->where($update->expr()->eq('id', $update->createParameter('room_id')));

		$query = $this->connection->getQueryBuilder();
		$query->select('*')
			->from('spreedme_rooms');
		$result = $query->execute();

		$output->startProgress();
		while ($row = $result->fetch()) {
			$output->advance();

			if (strlen($row['name']) !== 12 || $this->groupManager->groupExists($row['name'])) {
				continue;
			}

			$update->setParameter('room_id', (int) $row['id'], IQueryBuilder::PARAM_INT)
				->execute();
		}
		$output->finishProgress();
	}
}
