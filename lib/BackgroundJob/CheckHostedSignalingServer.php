<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\Config;
use OCA\Talk\DataObjects\AccountId;
use OCA\Talk\Exceptions\HostedSignalingServerAPIException;
use OCA\Talk\Service\HostedSignalingServerService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

class CheckHostedSignalingServer extends TimedJob {

	public function __construct(
		ITimeFactory $timeFactory,
		private HostedSignalingServerService $hostedSignalingServerService,
		private IConfig $config,
		private IManager $notificationManager,
		private IGroupManager $groupManager,
		private IURLGenerator $urlGenerator,
		private LoggerInterface $logger,
		private Config $talkConfig,
	) {
		parent::__construct($timeFactory);

		// Every hour
		$this->setInterval(3600);
		$this->setTimeSensitivity(IJob::TIME_SENSITIVE);

	}

	private function formatTurnSchemes(array $schemes): string {
		if (in_array('turn', $schemes) && in_array('turns', $schemes)) {
			return 'turn,turns';
		} elseif (in_array('turn', $schemes)) {
			return 'turn';
		} elseif (in_array('turns', $schemes)) {
			return 'turns';
		} else {
			return 'turn';
		}
	}

	private function formatTurnProtocols(array $protocols): string {
		if (in_array('udp', $protocols) && in_array('tcp', $protocols)) {
			return 'udp,tcp';
		} elseif (in_array('udp', $protocols)) {
			return 'udp';
		} elseif (in_array('tcp', $protocols)) {
			return 'tcp';
		} else {
			return 'udp';
		}
	}

	private function updateStunTurnSettings(array $oldAccountInfo, array $accountInfo) {
		if (!empty($accountInfo['stun']['servers'])) {
			if ($this->talkConfig->getStunServers() !== $accountInfo['stun']['servers']) {
				// STUN servers were added / changed
				$this->config->setAppValue('spreed', 'stun_servers', json_encode($accountInfo['stun']['servers']));
			}
		} elseif (!empty($oldAccountInfo['stun']['servers'])) {
			// STUN servers are no longer available, reset to default.
			$this->config->deleteAppValue('spreed', 'stun_servers');
		}

		if (!empty($accountInfo['turn']['servers'])) {
			$newTurnServers = [];
			foreach ($accountInfo['turn']['servers'] as $server) {
				$newTurnServers[] = [
					'server' => $server['server'],
					'secret' => $server['secret'],
					'schemes' => $this->formatTurnSchemes($server['schemes']),
					'protocols' => $this->formatTurnProtocols($server['protocols']),
				];
			}

			if ($this->talkConfig->getTurnServers() !== $newTurnServers) {
				// TURN servers were added / changed
				$this->config->setAppValue('spreed', 'turn_servers', json_encode($newTurnServers));
			}
		} elseif (!empty($oldAccountInfo['turn']['servers'])) {
			// TURN servers are no longer available, reset to default.
			$this->config->deleteAppValue('spreed', 'turn_servers');
		}
	}

	#[\Override]
	protected function run($argument): void {
		$accountId = $this->config->getAppValue('spreed', 'hosted-signaling-server-account-id', '');
		$oldAccountInfo = json_decode($this->config->getAppValue('spreed', 'hosted-signaling-server-account', '{}'), true);

		if ($accountId === '') {
			return;
		}
		$accountId = new AccountId($accountId);
		try {
			$accountInfo = $this->hostedSignalingServerService->fetchAccountInfo($accountId);
		} catch (HostedSignalingServerAPIException $e) {
			if ($e->getCode() === Http::STATUS_NOT_FOUND) {
				// Account was deleted, so remove the information locally
				$accountInfo = ['status' => 'deleted'];
			} elseif ($e->getCode() === Http::STATUS_UNAUTHORIZED) {
				// Account is expired and deletion is pending unless it's reactivated.
				$accountInfo = ['status' => 'expired'];
			} else {
				// API or connection issues - do nothing and just try again later
				return;
			}
		}

		$oldStatus = $oldAccountInfo['status'] ?? '';
		$newStatus = $accountInfo['status'];

		$notificationSubject = null;
		$notificationParameters = [];

		// the status has changed
		if ($oldStatus !== $newStatus) {
			if ($newStatus === 'deleted') {
				// remove signaling servers if account is not active anymore
				$this->config->deleteAppValue('spreed', 'signaling_mode');
				$this->config->deleteAppValue('spreed', 'signaling_servers');

				$notificationSubject = 'removed';
			} elseif ($newStatus === 'active') {
				// add signaling servers if account got active
				$this->config->deleteAppValue('spreed', 'signaling_mode');
				$this->config->setAppValue('spreed', 'signaling_servers', json_encode([
					'servers' => [
						[
							'server' => $accountInfo['signaling']['url'],
							'verify' => true,
						]
					],
					'secret' => $accountInfo['signaling']['secret'],
				]));
				$this->updateStunTurnSettings($oldAccountInfo, $accountInfo);

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
		} elseif ($newStatus === 'active') {
			if ($oldAccountInfo['signaling']['url'] !== $accountInfo['signaling']['url']
				|| $oldAccountInfo['signaling']['secret'] !== $accountInfo['signaling']['secret']) {
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
			$this->updateStunTurnSettings($oldAccountInfo, $accountInfo);
		}

		// store new account info
		if ($oldAccountInfo !== $accountInfo) {
			$this->config->setAppValue('spreed', 'hosted-signaling-server-account', json_encode($accountInfo));
		}

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

			$adminGroup = $this->groupManager->get('admin');
			if ($adminGroup instanceof IGroup) {
				$users = $adminGroup->getUsers();
				foreach ($users as $user) {
					// Now add the new notification
					$notification->setUser($user->getUID());
					$this->notificationManager->notify($notification);
				}
			}
		}
	}
}
