<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Marcel Müller <marcel.mueller@nextcloud.com>
 *
 * @author Marcel Müller <marcel.mueller@nextcloud.com>
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

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Config;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\IJob;
use OCP\BackgroundJob\TimedJob;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\Notification\IManager;
use Psr\Log\LoggerInterface;

class CheckTurnCertificate extends TimedJob {
	private Config $talkConfig;
	private ITimeFactory $timeFactory;
	private IGroupManager $groupManager;
	private IManager $notificationManager;
	private LoggerInterface $logger;

	public function __construct(
		ITimeFactory $timeFactory,
		Config $talkConfig,
		IGroupManager $groupManager,
		IManager $notificationManager,
		LoggerInterface $logger,
	) {
		parent::__construct($timeFactory);
		$this->talkConfig = $talkConfig;
		$this->timeFactory = $timeFactory;
		$this->groupManager = $groupManager;
		$this->notificationManager = $notificationManager;
		$this->logger = $logger;

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
	 * @param string $turnHost The host which was checked
	 * @param int $days Number of days until the certificate expires
	 */
	private function createNotifications(string $turnHost, int $days): void {
		$notification = $this->notificationManager->createNotification();

		try {
			$notification->setApp(Application::APP_ID)
				->setDateTime(new \DateTime())
				->setObject('turn_certificate_expiration', $turnHost);

			$notification->setSubject('turn_certificate_expiration', [
				'host' => $turnHost,
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
	 * Check the certificate of the specified TURN host
	 *
	 * @param string $turnHost The TURN host to check the certificate
	 */
	private function checkTurnServerCertificate(string $turnHost): void {
		// We need to disable verification here to also get an expired certificate
		$streamContext = stream_context_create([
			'ssl' => [
				'capture_peer_cert' => true,
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true,
			],
		]);

		$this->logger->debug('Checking certificate of ' . $turnHost);

		// In case no port was specified, use port 443 for the check
		if (!str_contains($turnHost, ':')) {
			$turnHost .= ':443';
		}

		$streamClient = stream_socket_client('ssl://' . $turnHost, $errorNumber, $errorString, 30, STREAM_CLIENT_CONNECT, $streamContext);

		if ($errorNumber !== 0) {
			// Unable to connect or invalid server address
			$this->logger->debug('Unable to check certificate of ' . $turnHost);
			return;
		}

		$streamCertificate = stream_context_get_params($streamClient);
		$certificateInfo = openssl_x509_parse($streamCertificate['options']['ssl']['peer_certificate']);
		$certificateValidTo = $this->timeFactory->getDateTime('@' . $certificateInfo['validTo_time_t']);

		$now = $this->timeFactory->getDateTime();
		$diff = $now->diff($certificateValidTo);
		$days = $diff->days;

		// $days will always be positive -> invert it, when the end date of the certificate is in the past
		if ($diff->invert) {
			$days *= -1;
		}

		if ($days < 10) {
			$this->logger->warning('Certificate of ' . $turnHost . ' expires in less than ' . $days . ' days');

			$this->createNotifications($turnHost, $days);
		} else {
			$this->logger->debug('Certificate of ' . $turnHost . ' is valid for ' . $days . ' days');
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function run($argument): void {
		$turnServers = $this->talkConfig->getTurnServers(false);

		foreach ($turnServers as $turnServer) {
			// Only check server which support the 'turns' protocol
			if (!str_contains($turnServer['schemes'], 'turns')) {
				continue;
			}

			$this->checkTurnServerCertificate($turnServer['server']);
		}
	}
}
