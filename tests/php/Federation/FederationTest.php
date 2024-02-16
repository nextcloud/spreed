<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2021 Gary Kim <gary@garykim.dev>
 *
 * @author Gary Kim <gary@garykim.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Talk\Tests\php\Federation;

use OC\Federation\CloudFederationShare;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\Config;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Federation\CloudFederationProviderTalk;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\BackgroundJob\IJobList;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationNotification;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudIdManager;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Notification\INotification;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class FederationTest extends TestCase {
	protected ?FederationManager $federationManager = null;

	protected ?BackendNotifier $backendNotifier = null;

	protected ICloudIdManager|MockObject $cloudIdManager;
	/** @var ICloudFederationProviderManager|MockObject */
	protected $cloudFederationProviderManager;

	/** @var ICloudFederationFactory|MockObject */
	protected $cloudFederationFactory;

	/** @var Config|MockObject */
	protected $config;
	protected IAppConfig|MockObject $appConfig;
	/** @var LoggerInterface|MockObject */
	protected $logger;

	/** @var AddressHandler|MockObject */
	protected $addressHandler;

	protected ?CloudFederationProviderTalk $cloudFederationProvider = null;

	/** @var IUserManager|MockObject */
	protected $userManager;
	protected IAppManager|MockObject $appManager;

	/** @var IURLGenerator|MockObject */
	protected $url;

	/** @var INotificationManager|MockObject */
	protected $notificationManager;

	/** @var AttendeeMapper|MockObject */
	protected $attendeeMapper;

	public function setUp(): void {
		parent::setUp();

		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->cloudFederationProviderManager = $this->createMock(ICloudFederationProviderManager::class);
		$this->cloudFederationFactory = $this->createMock(ICloudFederationFactory::class);
		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->attendeeMapper = $this->createMock(AttendeeMapper::class);
		$this->config = $this->createMock(Config::class);
		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->appManager = $this->createMock(IAppManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->url = $this->createMock(IURLGenerator::class);

		$this->backendNotifier = new BackendNotifier(
			$this->cloudFederationFactory,
			$this->addressHandler,
			$this->logger,
			$this->cloudFederationProviderManager,
			$this->createMock(IJobList::class),
			$this->userManager,
			$this->url,
			$this->appManager,
			$this->config,
			$this->appConfig,
		);

		$this->federationManager = $this->createMock(FederationManager::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);

		$this->cloudFederationProvider = new CloudFederationProviderTalk(
			$this->cloudIdManager,
			$this->userManager,
			$this->addressHandler,
			$this->federationManager,
			$this->config,
			$this->appConfig,
			$this->notificationManager,
			$this->createMock(ParticipantService::class),
			$this->createMock(RoomService::class),
			$this->attendeeMapper,
			$this->createMock(InvitationMapper::class),
			$this->createMock(Manager::class),
			$this->createMock(ISession::class),
			$this->createMock(IEventDispatcher::class),
			$this->logger
		);
	}

	public function testSendRemoteShareWithOwner() {
		$cloudShare = $this->createMock(ICloudFederationShare::class);

		$providerId = '3';
		$roomId = 5;
		$token = 'abcdefghijklmno';
		$shareWith = 'test@https://remote.test.local';
		$name = 'abcdefgh';
		$owner = 'Owner\'s name';
		$ownerId = 'owner';
		$ownerFederatedId = $ownerId . '@test.local';
		$sharedByDisplayName = 'Owner\'s name';
		$sharedByFederatedId = 'owner@test.local';
		$shareType = 'user';
		$roomType = Room::TYPE_GROUP;
		$roomName = 'Room name';

		$room = $this->createMock(Room::class);
		$attendee = $this->createStub(Attendee::class);
		$ownerUser = $this->createMock(IUser::class);
		$sharedBy = $this->createMock(IUser::class);
		$sharedBy->expects($this->once())
			->method('getCloudId')
			->with()
			->willReturn($sharedByFederatedId);
		$sharedBy->expects($this->once())
			->method('getDisplayName')
			->with()
			->willReturn($sharedByDisplayName);

		$room->expects($this->once())
			->method('getName')
			->with()
			->willReturn($roomName);

		$room->expects($this->once())
			->method('getType')
			->with()
			->willReturn($roomType);

		$room->expects($this->once())
			->method('getToken')
			->with()
			->willReturn($name);

		$this->userManager->expects($this->once())
			->method('get')
			->willReturn($ownerUser);

		$ownerUser->expects($this->once())
			->method('getCloudId')
			->with()
			->willReturn($ownerFederatedId);

		$ownerUser->expects($this->once())
			->method('getDisplayName')
			->with()
			->willReturn($owner);

		$this->cloudFederationFactory->expects($this->once())
			->method('getCloudFederationShare')
			->with(
				$shareWith,
				$name,
				'',
				$providerId,
				$ownerFederatedId,
				$owner,
				$sharedByFederatedId,
				$sharedByDisplayName,
				$token,
				$shareType,
				'talk-room'
			)
			->willReturn($cloudShare);

		$this->cloudFederationProviderManager->expects($this->once())
			->method('sendShare')
			->with($cloudShare);

		$this->addressHandler->expects($this->once())
			->method('splitUserRemote')
			->with($shareWith)
			->willReturn(['test', 'remote.test.local']);

		$this->appConfig->method('getAppValueBool')
			->willReturnMap([
				['federation_outgoing_enabled', true, false, true],
				['federation_only_trusted_servers', false, false, false],
			]);

		$this->config->method('isFederationEnabledForUserId')
			->with($sharedBy)
			->willReturn(true);

		$this->backendNotifier->sendRemoteShare($providerId, $token, $shareWith, $sharedBy, $shareType, $room, $attendee);
	}

	public function testReceiveRemoteShare() {
		$providerId = '3';
		$token = 'abcdefghijklmno';
		$shareWith = 'test@remote.test.local';
		$name = 'abcdefgh';
		$owner = 'Owner\'s name';
		$ownerFederatedId = 'owner@test.local';
		$sharedBy = 'Owner\'s name';
		$sharedByFederatedId = 'owner@test.local';
		$remote = 'test.local';
		$shareType = 'user';
		$roomType = Room::TYPE_GROUP;
		$roomName = 'Room name';

		$shareWithUser = $this->createMock(IUser::class);
		$shareWithUserID = '10';

		$share = new CloudFederationShare(
			$shareWith,
			$name,
			'',
			$providerId,
			$ownerFederatedId,
			$owner,
			$sharedByFederatedId,
			$sharedBy,
			$shareType,
			'talk-room',
			$token
		);
		$share->setProtocol([
			'name' => 'nctalk',
			'roomType' => $roomType,
			'roomName' => $roomName,
			'options' => [
				'sharedSecret' => $token,
			],
			'invitedCloudId' => 'test@remote.test.local',
		]);

		$invite = Invitation::fromRow(['id' => 20]);

		// Test receiving federation expectations
		$this->federationManager->expects($this->once())
			->method('addRemoteRoom')
			->with($shareWithUser, $providerId, $roomType, $roomName, $name, $remote, $token)
			->willReturn($invite);

		$this->config->method('isFederationEnabled')
			->willReturn(true);

		$this->appConfig->method('getAppValueBool')
			->willReturnMap([
				['federation_incoming_enabled', true, false, true],
			]);

		$this->config->method('isDisabledForUser')
			->with($shareWithUser)
			->willReturn(false);

		$this->config->method('isFederationEnabledForUserId')
			->with($shareWithUser)
			->willReturn(true);

		$this->addressHandler->expects($this->once())
			->method('splitUserRemote')
			->with($ownerFederatedId)
			->willReturn(['owner', $remote]);

		$this->userManager->expects($this->once())
			->method('get')
			->with($shareWith)
			->willReturn($shareWithUser);

		// Test sending notification expectations
		$shareWithUser->expects($this->once())
			->method('getUID')
			->with()
			->willReturn($shareWithUserID);

		$notification = $this->createMock(INotification::class);

		$notification->expects($this->once())
			->method('setApp')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setUser')
			->with($shareWithUserID)
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setDateTime')
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setObject')
			->with('remote_talk_share', 20)
			->willReturnSelf();

		$notification->expects($this->once())
			->method('setSubject')
			->with('remote_talk_share', [
				'sharedByDisplayName' => $sharedBy,
				'sharedByFederatedId' => $sharedByFederatedId,
				'roomName' => $roomName,
				'serverUrl' => $remote,
				'roomToken' => $name,
			]);

		$this->notificationManager->expects($this->once())
			->method('createNotification')
			->with()
			->willReturn($notification);

		$this->notificationManager->expects($this->once())
			->method('notify')
			->with($notification);

		$this->assertSame('20',
			$this->cloudFederationProvider->shareReceived($share)
		);
	}

	public function testSendAcceptNotification() {
		$remote = 'https://remote.test.local';
		$id = 50;
		$token = 'abcdefghijklmno';

		$notification = $this->createMock(ICloudFederationNotification::class);
		$notification->expects($this->once())
			->method('setMessage')
			->with(
				'SHARE_ACCEPTED',
				FederationManager::TALK_ROOM_RESOURCE,
				$id,
				[
					'sharedSecret' => $token,
					'message' => 'Recipient accepted the share',
					'remoteServerUrl' => 'http://example.tld',
				]
			);

		$this->cloudFederationFactory->expects($this->once())
			->method('getCloudFederationNotification')
			->with()
			->willReturn($notification);

		$this->cloudFederationProviderManager->expects($this->once())
			->method('sendNotification')
			->with($remote, $notification)
			->willReturn([]);

		$this->addressHandler->method('urlContainProtocol')
			->with($remote)
			->willReturn(true);

		$this->url->method('getAbsoluteURL')
			->with('/')
			->willReturn('http://example.tld/index.php/');

		$success = $this->backendNotifier->sendShareAccepted($remote, $id, $token);

		$this->assertEquals(true, $success);
	}

	public function testSendRejectNotification() {
		$remote = 'https://remote.test.local';
		$id = 50;
		$token = 'abcdefghijklmno';

		$notification = $this->createMock(ICloudFederationNotification::class);
		$notification->expects($this->once())
			->method('setMessage')
			->with(
				'SHARE_DECLINED',
				FederationManager::TALK_ROOM_RESOURCE,
				$id,
				[
					'sharedSecret' => $token,
					'message' => 'Recipient declined the share',
					'remoteServerUrl' => 'example.tld',
				]
			);

		$this->cloudFederationFactory->expects($this->once())
			->method('getCloudFederationNotification')
			->with()
			->willReturn($notification);

		$this->cloudFederationProviderManager->expects($this->once())
			->method('sendNotification')
			->with($remote, $notification)
			->willReturn([]);

		$this->addressHandler->method('urlContainProtocol')
			->with($remote)
			->willReturn(true);

		$this->url->method('getAbsoluteURL')
			->with('/')
			->willReturn('https://example.tld/index.php/');

		$success = $this->backendNotifier->sendShareDeclined($remote, $id, $token);

		$this->assertEquals(true, $success);
	}
}
