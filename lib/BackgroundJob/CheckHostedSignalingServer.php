<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

class CheckHostedSignalingServer extends TimedJob {

	/** @var HostedSignalingServerService */
	private $hostedSignalingServerService;
	/** @var IConfig */
	private $config;
	/** @var IManager */
	private $notificationManager;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IURLGenerator */
	private $urlGenerator;
	/** @var LoggerInterface */
	private $logger;

	public function __construct(ITimeFactory $timeFactory,
								HostedSignalingServerService $hostedSignalingServerService,
								IConfig $config,
								IManager $notificationManager,
								IGroupManager $groupManager,
								IURLGenerator $urlGenerator,
								LoggerInterface $logger) {
		parent::__construct($timeFactory);
		$this->setInterval(3600);
		$this->hostedSignalingServerService = $hostedSignalingServerService;
		$this->config = $config;
		$this->notificationManager = $notificationManager;
		$this->groupManager = $groupManager;
		$this->urlGenerator = $urlGenerator;
		$this->logger = $logger;
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

		$oldStatus = $oldAccountInfo['status'] ?? '';
		$newStatus = $accountInfo['status'];

		$notificationSubject = null;
		$notificationParameters = [];

		// the status has changed
		if ($oldStatus !== $newStatus) {
			if ($oldStatus === 'active') {
				// remove signaling servers if account is not active anymore
				$this->config->setAppValue('spreed', 'signaling_mode', 'internal');
				$this->config->deleteAppValue('spreed', 'signaling_servers');

				$notificationSubject = 'removed';
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

				$notificationSubject = 'added';
			}

			if (is_null($notificationSubject)) {
				$notificationSubject = 'changed-status';
				$notificationParameters = [
					'oldstatus' => $oldAccountInfo['status'],
					'newstatus' => $accountInfo['status'],
				];
			}

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
		}

		// store new account info
		if ($oldAccountInfo !== $accountInfo) {
			$this->config->setAppValue('spreed', 'hosted-signaling-server-account', json_encode($accountInfo));
		}

		$this->config->setAppValue('spreed', 'hosted-signaling-server-account-last-checked', $this->time->getTime());

		if (!is_null($notificationSubject)) {
			$this->logger->info('Hosted signaling server background job caused a notification: ' . $notificationSubject . ' ' . json_encode($notificationParameters));

			$notification = $this->notificationManager->createNotification();
			$notification
				->setApp('spreed')
				->setDateTime(new \DateTime())
				->setObject('hosted-signaling-server', $notificationSubject)
				->setSubject($notificationSubject, $notificationParameters)
				->setLink($this->urlGenerator->linkToRouteAbsolute('settings.AdminSettings.index', ['section' => 'talk']) . '#signaling_server')
				->setIcon($this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('spreed', 'app-dark.svg')))
			;

			$users = $this->groupManager->get('admin')->getUsers();
			foreach ($users as $user) {
				// Now add the new notification
				$notification->setUser($user->getUID());
				$this->notificationManager->notify($notification);
			}
		}
	}
}
