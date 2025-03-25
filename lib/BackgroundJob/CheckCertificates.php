<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\BackgroundJob;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCA\Talk\Service\CertificateService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

class CheckCertificates extends TimedJob {
	public function __construct(
		protected CertificateService $certService,
		protected Config $talkConfig,
		protected ITimeFactory $timeFactory,
		protected IGroupManager $groupManager,
		protected IManager $notificationManager,
		protected LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);

		// Run once a week
		$this->setInterval(60 * 60 * 24 * 7);
		$this->setTimeSensitivity(IJob::TIME_INSENSITIVE);
	}

	/*
	 * @return string[]
	 */
	private function getUsersToNotify(): array {
		$users = [];

		$groupToNotify = $this->groupManager->get('admin');
		if ($groupToNotify instanceof IGroup) {
			foreach ($groupToNotify->getUsers() as $user) {
				$users[] = $user->getUID();
			}
		}

		return $users;
	}

	/**
	 * Create a notification and inform admins about the certificate which is about to expire
	 *
	 * @param string $host The host which was checked
	 * @param int $days Number of days until the certificate expires
	 */
	private function createNotifications(string $host, int $days): void {
		$notification = $this->notificationManager->createNotification();

		try {
			$notification->setApp(Application::APP_ID)
				->setDateTime(new \DateTime())
				->setObject('certificate_expiration', $host);

			$notification->setSubject('certificate_expiration', [
				'host' => $host,
				'days_to_expire' => $days,
			]);

			foreach ($this->getUsersToNotify() as $uid) {
				$notification->setUser($uid);
				$this->notificationManager->notify($notification);
			}
		} catch (\InvalidArgumentException $e) {
			return;
		}
	}

	/**
	 * Check the certificate of the specified host
	 *
	 * @param string $host The host to check the certificate of without scheme
	 */
	private function checkServerCertificate(string $host): void {
		$expirationInDays = $this->certService->getCertificateExpirationInDays($host);

		if ($expirationInDays == null) {
			return;
		}

		if ($expirationInDays < 10) {
			$this->logger->warning('Certificate of ' . $host . ' expires in less than ' . $expirationInDays . ' days');

			$this->createNotifications($host, $expirationInDays);
		} else {
			$this->logger->debug('Certificate of ' . $host . ' is valid for ' . $expirationInDays . ' days');
		}
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	protected function run($argument): void {
		$turnServers = $this->talkConfig->getTurnServers(false);

		foreach ($turnServers as $turnServer) {
			// Only check server which support the 'turns' protocol
			if (!str_contains($turnServer['schemes'], 'turns')) {
				continue;
			}

			$this->checkServerCertificate($turnServer['server']);
		}

		$signalingServers = $this->talkConfig->getSignalingServers();

		foreach ($signalingServers as $signalingServer) {
			if ((bool)$signalingServer['verify']) {
				$this->checkServerCertificate($signalingServer['server']);
			}
		}

		$recordingServers = $this->talkConfig->getRecordingServers();

		foreach ($recordingServers as $recordingServer) {
			if ((bool)$recordingServer['verify']) {
				$this->checkServerCertificate($recordingServer['server']);
			}
		}
	}
}
