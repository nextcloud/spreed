<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OC\DB\ConnectionAdapter;
use OC\DB\SchemaWrapper;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
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
	/** @var IDBConnection|ConnectionAdapter */
	protected $connection;

	/**
	 * @param ITimeFactory $timeFactory
	 * @param IJobList $jobList
	 * @param IConfig $serverConfig
	 * @param IDBConnection $connection
	 */
	public function __construct(
		ITimeFactory $timeFactory,
		protected IJobList $jobList,
		protected IConfig $serverConfig,
		IDBConnection $connection,
	) {
		parent::__construct($timeFactory);
		$this->connection = $connection;

		// Every hour
		$this->setInterval(3600);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
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
