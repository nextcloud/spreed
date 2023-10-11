<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 * @copyright Copyright (c) 2021 Gary Kim <gary@garykim.dev>
 *
 * @author Bjoern Schiessle <bjoern@schiessle.org>
 * @author Björn Schießle <bjoern@schiessle.org>
 * @author Joas Schilling <coding@schilljs.com>
 * @author Lukas Reschke <lukas@statuscode.ch>
 * @author Morris Jobke <hey@morrisjobke.de>
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 * @author Gary Kim <gary@garykim.dev>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */
namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Federation\BackendNotifier;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJobList;
use OCP\BackgroundJob\Job;
use OCP\ILogger;

/**
 * Class RetryJob
 *
 * Background job to re-send update of federated re-shares to the remote server in
 * case the server was not available on the first try
 *
 * @package OCA\Talk\BackgroundJob
 */
class RetryJob extends Job {

	/** @var int max number of attempts to send the request */
	private int $maxTry = 20;


	public function __construct(
		private BackendNotifier $backendNotifier,
		ITimeFactory $timeFactory,
	) {
		parent::__construct($timeFactory);
	}

	/**
	 * run the job, then remove it from the jobList
	 *
	 * @param IJobList $jobList
	 * @param ILogger|null $logger
	 */
	public function execute(IJobList $jobList, ?ILogger $logger = null): void {
		if (((int)$this->argument['try']) > $this->maxTry) {
			$jobList->remove($this, $this->argument);
			return;
		}
		if ($this->shouldRun($this->argument)) {
			parent::execute($jobList, $logger);
			$jobList->remove($this, $this->argument);
		}
	}

	protected function run($argument): void {
		$remote = $argument['remote'];
		$data = json_decode($argument['data'], true);
		$try = (int)$argument['try'] + 1;

		$this->backendNotifier->sendUpdateDataToRemote($remote, $data, $try);
	}

	/**
	 * test if it is time for the next run
	 *
	 * @param array $argument
	 * @return bool
	 */
	protected function shouldRun(array $argument): bool {
		$lastRun = (int)$argument['lastRun'];
		$try = (int)$argument['try'];
		return (($this->time->getTime() - $lastRun) > $this->nextRunBreak($try));
	}

	protected function nextRunBreak(int $try): int {
		return min(($try + 1) * 300, 3600);
	}
}
