<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Federation;

use OC\Federation\CloudFederationShare;
use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\Config;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Federation\CloudFederationProviderTalk;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Federation\RestrictionValidator;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Model\RetryNotificationMapper;
use OCA\Talk\Notification\FederationChatNotifier;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ProxyCacheMessageService;
use OCA\Talk\Service\RoomService;
use OCP\App\IAppManager;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationNotification;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudFederationShare;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IResponse;
use OCP\ICacheFactory;
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
	protected FederationManager&MockObject $federationManager;
	protected ICloudIdManager&MockObject $cloudIdManager;
	protected ICloudFederationProviderManager&MockObject $cloudFederationProviderManager;
	protected ICloudFederationFactory&MockObject $cloudFederationFactory;
	protected Config&MockObject $config;
	protected IAppConfig&MockObject $appConfig;
	protected LoggerInterface&MockObject $logger;
	protected AddressHandler&MockObject $addressHandler;
	protected IUserManager&MockObject $userManager;
	protected IAppManager&MockObject $appManager;
	protected IURLGenerator&MockObject $url;
	protected INotificationManager&MockObject $notificationManager;
	protected AttendeeMapper&MockObject $attendeeMapper;
	protected ProxyCacheMessageMapper&MockObject $proxyCacheMessageMapper;
	protected ProxyCacheMessageService&MockObject $proxyCacheMessageService;
	protected FederationChatNotifier&MockObject $federationChatNotifier;
	protected UserConverter&MockObject $userConverter;
	protected ICacheFactory&MockObject $cacheFactory;
	protected RetryNotificationMapper&MockObject $retryNotificationMapper;
	protected ITimeFactory&MockObject $timeFactory;
	protected RestrictionValidator&MockObject $restrictionValidator;
	protected ?CloudFederationProviderTalk $cloudFederationProvider = null;
	protected ?BackendNotifier $backendNotifier = null;

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
		$this->proxyCacheMessageMapper = $this->createMock(ProxyCacheMessageMapper::class);
		$this->proxyCacheMessageService = $this->createMock(ProxyCacheMessageService::class);
		$this->cacheFactory = $this->createMock(ICacheFactory::class);
		$this->retryNotificationMapper = $this->createMock(RetryNotificationMapper::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->restrictionValidator = $this->createMock(RestrictionValidator::class);

		$this->backendNotifier = new BackendNotifier(
			$this->cloudFederationFactory,
			$this->addressHandler,
			$this->logger,
			$this->cloudFederationProviderManager,
			$this->userManager,
			$this->url,
			$this->retryNotificationMapper,
			$this->timeFactory,
			$this->cloudIdManager,
			$this->restrictionValidator,
		);

		$this->federationManager = $this->createMock(FederationManager::class);
		$this->notificationManager = $this->createMock(INotificationManager::class);
		$this->federationChatNotifier = $this->createMock(FederationChatNotifier::class);
		$this->userConverter = $this->createMock(UserConverter::class);

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
			$this->logger,
			$this->proxyCacheMessageMapper,
			$this->proxyCacheMessageService,
			$this->federationChatNotifier,
			$this->userConverter,
			$this->timeFactory,
			$this->cacheFactory,
		);
	}

	public function testSendRemoteShareWithOwner(): void {
		$cloudShare = $this->createMock(ICloudFederationShare::class);

		$providerId = '3';
		$token = 'abcdefghijklmno';
		$shareWith = 'test@remote.test.local';
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
			->method('sendCloudShare')
			->with($cloudShare);

		$cloudId = $this->createMock(ICloudId::class);
		$cloudId->method('getRemote')
			->willReturn('remote.test.local');
		$cloudId->method('getUser')
			->willReturn('test');

		$this->cloudIdManager->expects($this->once())
			->method('resolveCloudId')
			->with($shareWith)
			->willReturn($cloudId);

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

	public function testReceiveRemoteShare(): void {
		$providerId = '3';
		$token = 'abcdefghijklmno';
		$shareWith = 'test@remote.test.local';
		$name = 'abcdefgh';
		$owner = 'Owner\'s name';
		$ownerFederatedId = 'owner@test.local';
		$sharedBy = 'Owner\'s name';
		$sharedByFederatedId = 'owner@test.local';
		$remote = 'https://test.local';
		$shareType = 'user';
		$roomType = Room::TYPE_GROUP;
		$roomName = 'Room name';
		$roomDefaultPermissions = Attendee::PERMISSIONS_CUSTOM | Attendee::PERMISSIONS_CHAT;

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
			'roomDefaultPermissions' => $roomDefaultPermissions,
			'options' => [
				'sharedSecret' => $token,
			],
			'invitedCloudId' => 'test@remote.test.local',
		]);

		$invite = Invitation::fromRow(['id' => 20]);

		// Test receiving federation expectations
		$this->federationManager->expects($this->once())
			->method('addRemoteRoom')
			->with($shareWithUser, $providerId, $roomType, $roomName, $roomDefaultPermissions, $name, $remote, $token)
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

		$this->addressHandler->expects($this->once())
			->method('urlContainProtocol')
			->willReturnCallback(static fn (string $url) => str_starts_with($url, 'http://') || str_starts_with($url, 'https://'));

		$this->userManager->expects($this->once())
			->method('get')
			->with($shareWith)
			->willReturn($shareWithUser);

		// Test sending notification expectations
		$shareWithUser->method('getUID')
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

	public function testSendAcceptNotification(): void {
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
					'displayName' => 'Foo Bar',
					'cloudId' => 'cloudId@example.tld',
				]
			);

		$this->cloudFederationFactory->expects($this->once())
			->method('getCloudFederationNotification')
			->with()
			->willReturn($notification);

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')
			->willReturn(Http::STATUS_CREATED);
		$this->cloudFederationProviderManager->expects($this->once())
			->method('sendCloudNotification')
			->with($remote, $notification)
			->willReturn($response);

		$this->addressHandler->method('urlContainProtocol')
			->with($remote)
			->willReturn(true);

		$this->url->method('getAbsoluteURL')
			->with('/')
			->willReturn('http://example.tld/index.php/');

		$success = $this->backendNotifier->sendShareAccepted($remote, $id, $token, 'Foo Bar', 'cloudId@example.tld');

		$this->assertTrue($success);
	}

	public function testSendRejectNotification(): void {
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
					'remoteServerUrl' => 'https://example.tld',
				]
			);

		$this->cloudFederationFactory->expects($this->once())
			->method('getCloudFederationNotification')
			->with()
			->willReturn($notification);

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')
			->willReturn(Http::STATUS_CREATED);
		$this->cloudFederationProviderManager->expects($this->once())
			->method('sendCloudNotification')
			->with($remote, $notification)
			->willReturn($response);

		$this->addressHandler->method('urlContainProtocol')
			->with($remote)
			->willReturn(true);

		$this->url->method('getAbsoluteURL')
			->with('/')
			->willReturn('https://example.tld/index.php/');

		$this->backendNotifier->sendShareDeclined($remote, $id, $token);
	}
}
