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

use OCA\Talk\DataObjects\AccountId;
use OCA\Talk\Exceptions\HostedSignalingServerAPIException;
use OCA\Talk\Service\HostedSignalingServerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\Notification\IManager;

class CheckHostedSignalingServer extends TimedJob {

	/** @var HostedSignalingServerService */
	private $hostedSignalingServerService;
	/** @var IConfig */
	private $config;
	/** @var IManager */
	private $notificationManager;

	public function __construct(ITimeFactory $timeFactory,
								HostedSignalingServerService $hostedSignalingServerService,
								IConfig $config,
								IManager $notificationManager) {
		parent::__construct($timeFactory);
		$this->setInterval(3600);
		$this->hostedSignalingServerService = $hostedSignalingServerService;
		$this->config = $config;
		$this->notificationManager = $notificationManager;
	}

	protected function run($argument): void {
		$accountId = $this->config->getAppValue('spreed', 'hosted-signaling-server-account-id', '');
		$oldAccountInfo = json_decode($this->config->getAppValue('spreed', 'hosted-signaling-server-account', '{}'), true);

		if ($accountId === '') {
			return;
		}
		$accountId = new AccountId($accountId);
		try {
			$accountInfo = $this->hostedSignalingServerService->fetchAccountInfo($accountId);
		} catch (HostedSignalingServerAPIException $e) { // API or connection issues
			// do nothing and just try again later
			return;
		}
		// TODO set last checked

		$oldStatus = $oldAccountInfo['status'] ?? '';
		$newStatus = $accountInfo['status'];

		// the status has changed
		if ($oldStatus !== $newStatus) {
			if ($oldStatus === 'active') {
				// remove signaling servers if account is not active anymore
				$this->config->setAppValue('spreed', 'signaling_mode', 'internal');
				$this->config->setAppValue('spreed', 'signaling_servers', json_encode([
					'servers' => [],
					'secret' => '',
				]));
			}

			if ($newStatus === 'active') {
				// add signaling servers if account got active
				$this->config->setAppValue('spreed', 'signaling_mode', 'external');
				$this->config->setAppValue('spreed', 'signaling_servers', json_encode([
					'servers' => [
						[
							'server' => $accountInfo['signaling']['url'],
							'verify' => true,
						]
					],
					'secret' => $accountInfo['signaling']['secret'],
				]));
			}

			// TODO send notification "Account has changed state from OLD to NEW"

			// only credentials have changed
		} elseif ($newStatus === 'active' && (
			$oldAccountInfo['signaling']['url'] !== $accountInfo['signaling']['url'] ||
			$oldAccountInfo['signaling']['secret'] !== $accountInfo['signaling']['secret'])
		) {
			$this->config->setAppValue('spreed', 'signaling_mode', 'external');
			$this->config->setAppValue('spreed', 'signaling_servers', json_encode([
				'servers' => [
					[
						'server' => $accountInfo['signaling']['url'],
						'verify' => true,
					]
				],
				'secret' => $accountInfo['signaling']['secret'],
			]));

			// TODO send notification "New signaling server was configured"
		}

		// store new account info
		if ($oldAccountInfo !== $accountInfo) {
			$this->config->setAppValue('spreed', 'hosted-signaling-server-account', json_encode($accountInfo));
		}

		$this->config->setAppValue('spreed', 'hosted-signaling-server-account-last-checked', $this->time->getTime());
	}
}
