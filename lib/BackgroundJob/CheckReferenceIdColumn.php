<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\BackgroundJob;

use OC\DB\ConnectionAdapter;
use OC\DB\SchemaWrapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IDBConnection;

/**
 * Class CheckReferenceIdColumn
 *
 * @package OCA\Talk\BackgroundJob
 */
class CheckReferenceIdColumn extends TimedJob {

	/** @var IJobList */
	protected $jobList;
	/** @var IConfig */
	protected $serverConfig;
	/** @var IDBConnection|ConnectionAdapter */
	protected $connection;

	public function __construct(ITimeFactory $timeFactory,
								IJobList $jobList,
								IConfig $serverConfig,
								IDBConnection $connection) {
		parent::__construct($timeFactory);
		$this->jobList = $jobList;
		$this->serverConfig = $serverConfig;
		$this->connection = $connection;
		$this->setInterval(3600);
	}

	protected function run($argument): void {
		$schema = new SchemaWrapper($this->connection->getInner());
		if ($schema->hasTable('comments')) {
			$table = $schema->getTable('comments');
			if ($table->hasColumn('reference_id')) {
				$this->serverConfig->setAppValue('spreed', 'has_reference_id', 'yes');
				$this->jobList->remove(self::class);
			}
		}
	}
}
