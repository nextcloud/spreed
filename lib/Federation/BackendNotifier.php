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
use OCA\Talk\AppInfo\Application;
use OCA\Talk\BackgroundJob\RetryJob;
use OCA\Talk\Exceptions\RoomHasNoModeratorException;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\BackgroundJob\IJobList;
use OCP\DB\Exception;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationNotification;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\HintException;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class BackendNotifier {

	public function __construct(
		private ICloudFederationFactory $cloudFederationFactory,
		private AddressHandler $addressHandler,
		private LoggerInterface $logger,
		private ICloudFederationProviderManager $federationProviderManager,
		private IJobList $jobList,
		private IUserManager $userManager,
	) {
	}

	/**
	 * @throws HintException
	 * @throws RoomHasNoModeratorException
	 * @throws Exception
	 */
	public function sendRemoteShare(
		string $providerId,
		string $token,
		string $shareWith,
		string $sharedBy,
		string $sharedByFederatedId,
		string $shareType,
		Room $room,
		Attendee $roomOwnerAttendee,
	): bool {
		[$user, $remote] = $this->addressHandler->splitUserRemote($shareWith);

		$roomName = $room->getName();
		$roomType = $room->getType();
		$roomToken = $room->getToken();

		if (!($user && $remote)) {
			$this->logger->info(
				"could not share $roomToken, invalid contact $shareWith",
				['app' => Application::APP_ID]
			);
			return false;
		}

		/** @var IUser|null $roomOwner */
		$roomOwner = $this->userManager->get($roomOwnerAttendee->getActorId());

		$remote = $this->prepareRemoteUrl($remote);

		$share = $this->cloudFederationFactory->getCloudFederationShare(
			$user . '@' . $remote,
			$roomToken,
			'',
			$providerId,
			$roomOwner->getCloudId(),
			$roomOwner->getDisplayName(),
			$sharedByFederatedId,
			$sharedBy,
			$token,
			$shareType,
			FederationManager::TALK_ROOM_RESOURCE
		);

		// Put room name info in the share
		$protocol = $share->getProtocol();
		$protocol['roomName'] = $roomName;
		$protocol['roomType'] = $roomType;
		$protocol['name'] = FederationManager::TALK_PROTOCOL_NAME;
		$share->setProtocol($protocol);

		$response = $this->federationProviderManager->sendShare($share);
		if (is_array($response)) {
			return true;
		}
		$this->logger->info(
			"failed sharing $roomToken with $shareWith",
			['app' => Application::APP_ID]
		);

		return false;
	}

	/**
	 * send remote share acceptance notification to remote server
	 *
	 * @param string $remote remote server domain
	 * @param string $id share id
	 * @param string $token share secret token
	 * @return bool success
	 */
	public function sendShareAccepted(string $remote, string $id, string $token): bool {
		$remote = $this->prepareRemoteUrl($remote);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_SHARE_ACCEPTED,
			FederationManager::TALK_ROOM_RESOURCE,
			$id,
			[
				'sharedSecret' => $token,
				'message' => 'Recipient accepted the share',
			]);
		$response = $this->federationProviderManager->sendNotification($remote, $notification);
		if (!is_array($response)) {
			$this->logger->info(
				"failed to send share accepted notification for share from $remote",
				['app' => Application::APP_ID]
			);
			return false;
		}
		return true;
	}

	public function sendShareDeclined(string $remote, string $id, string $token): bool {
		$remote = $this->prepareRemoteUrl($remote);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_SHARE_DECLINED,
			FederationManager::TALK_ROOM_RESOURCE,
			$id,
			[
				'sharedSecret' => $token,
				'message' => 'Recipient declined the share',
			]
		);
		$response = $this->federationProviderManager->sendNotification($remote, $notification);
		if (!is_array($response)) {
			$this->logger->info(
				"failed to send share declined notification for share from $remote",
				['app' => Application::APP_ID]
			);
			return false;
		}
		return true;
	}

	public function sendRemoteUnShare(string $remote, string $id, string $token): void {
		$remote = $this->prepareRemoteUrl($remote);

		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			FederationManager::NOTIFICATION_SHARE_UNSHARED,
			FederationManager::TALK_ROOM_RESOURCE,
			$id,
			[
				'sharedSecret' => $token,
				'message' => 'This room has been unshared',
			]
		);

		$this->sendUpdateToRemote($remote, $notification);
	}

	public function sendUpdateDataToRemote(string $remote, array $data = [], int $try = 0): void {
		$notification = $this->cloudFederationFactory->getCloudFederationNotification();
		$notification->setMessage(
			$data['notificationType'],
			$data['resourceType'],
			$data['providerId'],
			$data['notification']
		);
		$this->sendUpdateToRemote($remote, $notification, $try);
	}

	public function sendUpdateToRemote(string $remote, ICloudFederationNotification $notification, int $try = 0): void {
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

	private function prepareRemoteUrl(string $remote): string {
		if (!$this->addressHandler->urlContainProtocol($remote)) {
			return 'https://' . $remote;
		}
		return $remote;
	}
}
