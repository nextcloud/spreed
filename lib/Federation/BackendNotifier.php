<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
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

namespace OCA\Talk\Federation;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\Federation\TrustedServers;
use OCA\Talk\BackgroundJob\RetryJob;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\RoomHasNoModeratorException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationNotification;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\HintException;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Server;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

class BackendNotifier {

	public function __construct(
		private ICloudFederationFactory $cloudFederationFactory,
		private AddressHandler $addressHandler,
		private LoggerInterface $logger,
		private ICloudFederationProviderManager $federationProviderManager,
		private IJobList $jobList,
		private IUserManager $userManager,
		private IURLGenerator $url,
		private IAppManager $appManager,
		private Config $talkConfig,
		private IAppConfig $appConfig,
	) {
	}

	/**
	 * Send the invitation to participant to join the federated room
	 * Sent from Host server to Remote participant server
	 *
	 * @throws HintException
	 * @throws RoomHasNoModeratorException
	 * @throws Exception
	 */
	public function sendRemoteShare(
		string $providerId,
		string $token,
		string $shareWith,
		IUser $sharedBy,
		string $shareType,
		Room $room,
		Attendee $roomOwnerAttendee,
	): bool {
		[$user, $remote] = $this->addressHandler->splitUserRemote($shareWith);

		$roomName = $room->getName();
		$roomType = $room->getType();
		$roomToken = $room->getToken();

		if (!($user && $remote)) {
			$this->logger->info("Could not share conversation $roomToken as the recipient is invalid: $shareWith");
			return false;
		}

		if (!$this->appConfig->getAppValueBool('federation_outgoing_enabled', true)) {
			$this->logger->info("Could not share conversation $roomToken as outgoing federation is disabled");
			return false;
		}

		if (!$this->talkConfig->isFederationEnabledForUserId($sharedBy)) {
			$this->logger->info('Talk federation not allowed for user ' . $sharedBy->getUID());
			return false;
		}

		if ($this->appConfig->getAppValueBool('federation_only_trusted_servers')) {
			if (!$this->appManager->isEnabledForUser('federation')) {
				$this->logger->error('Federation is limited to trusted servers but the "federation" app is disabled');
				return false;
			}

			$trustedServers = Server::get(TrustedServers::class);
			$serverUrl = $this->addressHandler->removeProtocolFromUrl($remote);
			if (!$trustedServers->isTrustedServer($serverUrl)) {
				$this->logger->warning(
					'Tried to send Talk federation invite to untrusted server {serverUrl}',
					['serverUrl' => $serverUrl]
				);
				return false;
			}
		}

		/** @var IUser|null $roomOwner */
		$roomOwner = $this->userManager->get($roomOwnerAttendee->getActorId());

		$invitedCloudId = $user . '@' . $remote;
		$remote = $this->prepareRemoteUrl($remote);

		$share = $this->cloudFederationFactory->getCloudFederationShare(
			$user . '@' . $remote,
			$roomToken,
			'',
			$providerId,
			$roomOwner->getCloudId(),
			$roomOwner->getDisplayName(),
			$sharedBy->getCloudId(),
			$sharedBy->getDisplayName(),
			$token,
			$shareType,
			FederationManager::TALK_ROOM_RESOURCE
		);

		// Put room name info in the share
		$protocol = $share->getProtocol();
		$protocol['invitedCloudId'] = $invitedCloudId;
		$protocol['roomName'] = $roomName;
		$protocol['roomType'] = $roomType;
		$protocol['name'] = FederationManager::TALK_PROTOCOL_NAME;
		$share->setProtocol($protocol);

		$response = $this->federationProviderManager->sendShare($share);
		if (is_array($response)) {
			return true;
		}
		$this->logger->info("Failed sharing $roomToken with $shareWith");

		return false;
	}

	/**
	 * The invited participant accepted joining the federated room
	 * Sent from Remote participant server to Host server
	 *
	 * @return bool success
	 */
	public function sendShareAccepted(
		string $remoteServerUrl,
		int $remoteAttendeeId,
		#[SensitiveParameter]
		string $accessToken,
	): bool {
		$remote = $this->prepareRemoteUrl($remoteServerUrl);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_SHARE_ACCEPTED,
			FederationManager::TALK_ROOM_RESOURCE,
			(string) $remoteAttendeeId,
			[
				'remoteServerUrl' => $this->getServerRemoteUrl(),
				'sharedSecret' => $accessToken,
				'message' => 'Recipient accepted the share',
			]);
		$response = $this->federationProviderManager->sendNotification($remote, $notification);
		if (!is_array($response)) {
			$this->logger->info("Failed to send share accepted notification for share from $remote");
			return false;
		}
		return true;
	}

	/**
	 * The invited participant declined joining the federated room
	 * Sent from Remote participant server to Host server
	 */
	public function sendShareDeclined(
		string $remoteServerUrl,
		int $remoteAttendeeId,
		#[SensitiveParameter]
		string $accessToken,
	): bool {
		$remote = $this->prepareRemoteUrl($remoteServerUrl);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_SHARE_DECLINED,
			FederationManager::TALK_ROOM_RESOURCE,
			(string) $remoteAttendeeId,
			[
				'remoteServerUrl' => $this->getServerRemoteUrl(),
				'sharedSecret' => $accessToken,
				'message' => 'Recipient declined the share',
			]
		);
		$response = $this->federationProviderManager->sendNotification($remote, $notification);
		if (!is_array($response)) {
			$this->logger->info("Failed to send share declined notification for share from $remote");
			return false;
		}
		return true;
	}

	public function sendRemoteUnShare(
		string $remoteServerUrl,
		int $localAttendeeId,
		#[SensitiveParameter]
		string $accessToken,
	): void {
		$remote = $this->prepareRemoteUrl($remoteServerUrl);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_SHARE_UNSHARED,
			FederationManager::TALK_ROOM_RESOURCE,
			(string) $localAttendeeId,
			[
				'remoteServerUrl' => $this->getServerRemoteUrl(),
				'sharedSecret' => $accessToken,
				'message' => 'This room has been unshared',
			]
		);

		$this->sendUpdateToRemote($remote, $notification);
	}

	/**
	 * Send information to remote participants that the room meta info updated
	 * Sent from Host server to Remote participant server
	 */
	public function sendRoomModifiedUpdate(
		string $remoteServer,
		int $localAttendeeId,
		#[SensitiveParameter]
		string $accessToken,
		string $localToken,
		string $changedProperty,
		string|int|bool|null $newValue,
		string|int|bool|null $oldValue,
	): void {
		$remote = $this->prepareRemoteUrl($remoteServer);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_ROOM_MODIFIED,
			FederationManager::TALK_ROOM_RESOURCE,
			(string) $localAttendeeId,
			[
				'remoteServerUrl' => $this->getServerRemoteUrl(),
				'sharedSecret' => $accessToken,
				'remoteToken' => $localToken,
				'changedProperty' => $changedProperty,
				'newValue' => $newValue,
				'oldValue' => $oldValue,
			],
		);

		$this->sendUpdateToRemote($remote, $notification);
	}

	/**
	 * @param string $remote
	 * @param array{notificationType: string, resourceType: string, providerId: string, notification: array} $data
	 * @param int $try
	 * @return void
	 * @internal Used to send retries in background jobs
	 */
	public function sendUpdateDataToRemote(string $remote, array $data, int $try): void {
		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			$data['notificationType'],
			$data['resourceType'],
			$data['providerId'],
			$data['notification']
		);
		$this->sendUpdateToRemote($remote, $notification, $try);
	}

	protected function sendUpdateToRemote(string $remote, ICloudFederationNotification $notification, int $try = 0): void {
		$response = $this->federationProviderManager->sendNotification($remote, $notification);
		if (!is_array($response)) {
			$this->jobList->add(RetryJob::class,
				[
					'remote' => $remote,
					'data' => json_encode($notification->getMessage()),
					'try' => $try,
				]
			);
		}
	}

	protected function prepareRemoteUrl(string $remote): string {
		if (!$this->addressHandler->urlContainProtocol($remote)) {
			return 'https://' . $remote;
		}
		return $remote;
	}

	protected function getServerRemoteUrl(): string {
		$server = rtrim($this->url->getAbsoluteURL('/'), '/');
		if (str_ends_with($server, '/index.php')) {
			$server = substr($server, 0, -10);
		}

		if (str_starts_with($server, 'https://')) {
			return substr($server, strlen('https://'));
		}

		return $server;
	}
}
