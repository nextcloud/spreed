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
use OCA\Talk\Config;
use OCA\Talk\Exceptions\RoomHasNoModeratorException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\RetryNotification;
use OCA\Talk\Model\RetryNotificationMapper;
use OCA\Talk\Room;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationNotification;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\HintException;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\OCM\Exceptions\OCMProviderException;
use OCP\Server;
use Psr\Log\LoggerInterface;
use SensitiveParameter;

class BackendNotifier {

	public function __construct(
		private ICloudFederationFactory $cloudFederationFactory,
		private AddressHandler $addressHandler,
		private LoggerInterface $logger,
		private ICloudFederationProviderManager $federationProviderManager,
		private IUserManager $userManager,
		private IURLGenerator $url,
		private IAppManager $appManager,
		private Config $talkConfig,
		private IAppConfig $appConfig,
		private RetryNotificationMapper $retryNotificationMapper,
		private ITimeFactory $timeFactory,
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

		/** @var IUser $roomOwner */
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

		try {
			$response = $this->federationProviderManager->sendCloudShare($share);
			if ($response->getStatusCode() === Http::STATUS_CREATED) {
				return true;
			}

			$this->logger->warning("Failed sharing $roomToken with $shareWith, received status code {code}\n{body}", [
				'code' => $response->getStatusCode(),
				'body' => (string) $response->getBody(),
			]);

			return false;
		} catch (OCMProviderException $e) {
			$this->logger->error("Failed sharing $roomToken with $shareWith, received OCMProviderException", ['exception' => $e]);
			return false;
		}
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
			]
		);

		return $this->sendUpdateToRemote($remote, $notification, retry: false) === true;
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
	): void {
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

		// We don't handle the return here as all local data is already deleted.
		// If the retry ever aborts due to "unknown" we are fine with it.
		$this->sendUpdateToRemote($remote, $notification);
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

		// We don't handle the return here as when the retry ever
		// aborts due to "unknown" we are fine with it.
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
	): ?bool {
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

		return $this->sendUpdateToRemote($remote, $notification);
	}

	/**
	 * Send information to remote participants that a message was posted
	 * Sent from Host server to Remote participant server
	 *
	 * @param array{remoteMessageId: int, actorType: string, actorId: string, actorDisplayName: string, messageType: string, systemMessage: string, expirationDatetime: string, message: string, messageParameter: string, creationDatetime: string, metaData: string} $messageData
	 * @param array{unreadMessages: int, unreadMention: bool, unreadMentionDirect: bool} $unreadInfo
	 */
	public function sendMessageUpdate(
		string $remoteServer,
		int $localAttendeeId,
		#[SensitiveParameter]
		string $accessToken,
		string $localToken,
		array $messageData,
		array $unreadInfo,
	): ?bool {
		$remote = $this->prepareRemoteUrl($remoteServer);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_MESSAGE_POSTED,
			FederationManager::TALK_ROOM_RESOURCE,
			(string) $localAttendeeId,
			[
				'remoteServerUrl' => $this->getServerRemoteUrl(),
				'sharedSecret' => $accessToken,
				'remoteToken' => $localToken,
				'messageData' => $messageData,
				'unreadInfo' => $unreadInfo,
			],
		);

		return $this->sendUpdateToRemote($remote, $notification);
	}

	protected function sendUpdateToRemote(string $remote, ICloudFederationNotification $notification, int $try = 0, bool $retry = true): ?bool {
		try {
			$response = $this->federationProviderManager->sendCloudNotification($remote, $notification);
			if ($response->getStatusCode() === Http::STATUS_CREATED) {
				return true;
			}

			if ($response->getStatusCode() === Http::STATUS_BAD_REQUEST) {
				$ocmBody = json_decode((string) $response->getBody(), true) ?? [];
				if (isset($ocmBody['message']) && $ocmBody['message'] === FederationManager::OCM_RESOURCE_NOT_FOUND) {
					// Remote exists but tells us the OCM notification can not be received (invalid invite data)
					// So we stop retrying
					return null;
				}
			}

			$this->logger->warning("Failed to send notification for share from $remote, received status code {code}\n{body}", [
				'code' => $response->getStatusCode(),
				'body' => (string) $response->getBody(),
			]);
		} catch (OCMProviderException $e) {
			$this->logger->error("Failed to send notification for share from $remote, received OCMProviderException", ['exception' => $e]);
		}

		if ($retry && $try === 0) {
			$now = $this->timeFactory->getTime();
			$now += $this->getRetryDelay(1);

			// Talk data
			$retryNotification = new RetryNotification();
			$retryNotification->setRemoteServer($remote);
			$retryNotification->setNumAttempts(1);
			$retryNotification->setNextRetry($this->timeFactory->getDateTime('@' . $now));

			// OCM notification data
			$data = $notification->getMessage();
			$retryNotification->setNotificationType($data['notificationType']);
			$retryNotification->setResourceType($data['resourceType']);
			$retryNotification->setProviderId($data['providerId']);
			$retryNotification->setNotification(json_encode($data['notification']));

			$this->retryNotificationMapper->insert($retryNotification);
		}

		return false;
	}

	public function retrySendingFailedNotifications(\DateTimeInterface $dueDateTime): void {
		$retryNotifications = $this->retryNotificationMapper->getAllDue($dueDateTime);

		foreach ($retryNotifications as $retryNotification) {
			$this->retrySendingFailedNotification($retryNotification);
		}
	}

	protected function retrySendingFailedNotification(RetryNotification $retryNotification): void {
		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			$retryNotification->getNotificationType(),
			$retryNotification->getResourceType(),
			$retryNotification->getProviderId(),
			json_decode($retryNotification->getNotification(), true, flags: JSON_THROW_ON_ERROR),
		);

		$success = $this->sendUpdateToRemote($retryNotification->getRemoteServer(), $notification, $retryNotification->getNumAttempts());

		if ($success) {
			$this->retryNotificationMapper->delete($retryNotification);
		} elseif ($success === null) {
			$this->logger->error('Server signaled the OCM notification is not accepted at ' . $retryNotification->getRemoteServer() . ', giving up!');
			$this->retryNotificationMapper->delete($retryNotification);
		} elseif ($retryNotification->getNumAttempts() === RetryNotification::MAX_NUM_ATTEMPTS) {
			$this->logger->error('Failed to send notification to ' . $retryNotification->getRemoteServer() . ' ' . RetryNotification::MAX_NUM_ATTEMPTS . ' times, giving up!');
			$this->retryNotificationMapper->delete($retryNotification);
		} else {
			$retryNotification->setNumAttempts($retryNotification->getNumAttempts() + 1);

			$now = $this->timeFactory->getTime();
			$now += $this->getRetryDelay($retryNotification->getNumAttempts());

			$retryNotification->setNextRetry($this->timeFactory->getDateTime('@' . $now));
			$this->retryNotificationMapper->update($retryNotification);
		}
	}

	/**
	 * First 5 attempts are retried on the next cron run.
	 * Attempts 6-10 we back off to cover slightly longer maintenance/downtimes (5 minutes * per attempt)
	 * And the last tries 11-20 are retried with ~8 hours delay
	 *
	 * This means the last retry is after ~84 hours so a downtime from Friday to Monday would be covered
	 */
	protected function getRetryDelay(int $attempt): int {
		if ($attempt < 5) {
			// Retry after "attempt" minutes
			return 5 * 60;
		}

		if ($attempt > 10) {
			// Retry after 8 hours
			return 8 * 3600;
		}

		// Retry after "attempt" * 5 minutes
		return $attempt * 5 * 60;
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
