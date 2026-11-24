<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Federation;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\CannotReachRemoteException;
use OCA\Talk\Federation\CloudFederationProviderTalk;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Manager;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Model\ProxyCacheMessage;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Notification\FederationChatNotifier;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ConversationUnarchiveService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ProxyCacheMessageService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Federation\ICloudIdManager;
use OCP\ICacheFactory;
use OCP\ISession;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use OCP\Share\Exceptions\ShareNotFound;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CloudFederationProviderTalkTest extends TestCase {
	protected Config&MockObject $config;
	protected ParticipantService&MockObject $participantService;
	protected InvitationMapper&MockObject $invitationMapper;
	protected Manager&MockObject $manager;
	protected ProxyCacheMessageService&MockObject $pcmService;
	protected FederationChatNotifier&MockObject $federationChatNotifier;
	protected CloudFederationProviderTalk $provider;

	public function setUp(): void {
		parent::setUp();

		$this->config = $this->createMock(Config::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->invitationMapper = $this->createMock(InvitationMapper::class);
		$this->manager = $this->createMock(Manager::class);
		$this->pcmService = $this->createMock(ProxyCacheMessageService::class);
		$this->federationChatNotifier = $this->createMock(FederationChatNotifier::class);

		$this->provider = new CloudFederationProviderTalk(
			$this->createMock(ICloudIdManager::class),
			$this->createMock(IUserManager::class),
			$this->createMock(AddressHandler::class),
			$this->createMock(FederationManager::class),
			$this->config,
			$this->createMock(IAppConfig::class),
			$this->createMock(INotificationManager::class),
			$this->participantService,
			$this->createMock(RoomService::class),
			$this->createMock(AttendeeMapper::class),
			$this->invitationMapper,
			$this->manager,
			$this->createMock(ISession::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(ProxyCacheMessageMapper::class),
			$this->pcmService,
			$this->federationChatNotifier,
			$this->createMock(UserConverter::class),
			$this->createMock(ITimeFactory::class),
			$this->createMock(ConversationUnarchiveService::class),
			$this->createMock(ICacheFactory::class),
		);
	}

	public static function dataInvalidReactionData(): array {
		return [
			'missing reactionData' => [[]],
			'reactionData not an array' => [['reactionData' => 'not-an-array']],
			'missing remoteMessageId' => [['reactionData' => [
				'actorType' => 'users',
				'actorId' => 'reactor',
				'actorDisplayName' => 'Reactor',
				'reaction' => '👍',
				'creationDatetime' => '2025-01-01T00:00:00+00:00',
			]]],
			'non-numeric remoteMessageId' => [['reactionData' => [
				'remoteMessageId' => 'abc',
				'actorType' => 'users',
				'actorId' => 'reactor',
				'actorDisplayName' => 'Reactor',
				'reaction' => '👍',
				'creationDatetime' => '2025-01-01T00:00:00+00:00',
			]]],
			'string remoteMessageId' => [['reactionData' => [
				'remoteMessageId' => '42',
				'actorType' => 'users',
				'actorId' => 'reactor',
				'actorDisplayName' => 'Reactor',
				'reaction' => '👍',
				'creationDatetime' => '2025-01-01T00:00:00+00:00',
			]]],
			'float remoteMessageId' => [['reactionData' => [
				'remoteMessageId' => 42.5,
				'actorType' => 'users',
				'actorId' => 'reactor',
				'actorDisplayName' => 'Reactor',
				'reaction' => '👍',
				'creationDatetime' => '2025-01-01T00:00:00+00:00',
			]]],
			'missing reaction' => [['reactionData' => [
				'remoteMessageId' => 42,
				'actorType' => 'users',
				'actorId' => 'reactor',
				'actorDisplayName' => 'Reactor',
				'creationDatetime' => '2025-01-01T00:00:00+00:00',
			]]],
			'unparseable creationDatetime' => [['reactionData' => [
				'remoteMessageId' => 42,
				'actorType' => 'users',
				'actorId' => 'reactor',
				'actorDisplayName' => 'Reactor',
				'reaction' => '👍',
				'creationDatetime' => 'not-a-date',
			]]],
		];
	}

	#[DataProvider('dataInvalidReactionData')]
	public function testReactionAddedValidatesReactionData(array $notification): void {
		$this->expectException(BadRequestException::class);

		$this->provider->notificationReceived(
			FederationManager::NOTIFICATION_REACTION_ADDED,
			'1',
			$notification,
		);
	}

	protected function mockReactionRoom(string $remoteToken): array {
		$this->config->method('isFederationEnabled')->willReturn(true);

		$invite = new Invitation();
		$invite->setLocalRoomId(23);
		$invite->setUserId('author');
		$this->invitationMapper->method('getByRemoteAndAccessToken')->willReturn($invite);

		$room = $this->createMock(Room::class);
		$room->method('isFederatedConversation')->willReturn(true);
		$room->method('getRemoteToken')->willReturn($remoteToken);
		$this->manager->method('getRoomById')->with(23)->willReturn($room);

		$participant = $this->createMock(Participant::class);
		$this->participantService->method('getParticipantWithActiveSession')->willReturn($participant);

		$notification = [
			'remoteServerUrl' => 'https://remote.example',
			'sharedSecret' => 'secret',
			'remoteToken' => 'remoteToken',
			'reactionData' => [
				'remoteMessageId' => 42,
				'actorType' => 'users',
				'actorId' => 'reactor',
				'actorDisplayName' => 'Reactor',
				'reaction' => '👍',
				'creationDatetime' => '2025-01-01T00:00:00+00:00',
			],
		];

		return [$room, $participant, $notification];
	}

	public function testReactionAddedRejectsMismatchingRemoteToken(): void {
		[, , $notification] = $this->mockReactionRoom('otherRemoteToken');

		$this->pcmService->expects($this->never())->method('findByRemote');
		$this->federationChatNotifier->expects($this->never())->method('handleReaction');

		$this->expectException(ShareNotFound::class);
		$this->provider->notificationReceived(FederationManager::NOTIFICATION_REACTION_ADDED, '1', $notification);
	}

	public function testReactionAddedSyncsUncachedMessage(): void {
		[$room, $participant, $notification] = $this->mockReactionRoom('remoteToken');
		$message = new ProxyCacheMessage();

		$this->pcmService->method('findByRemote')->willThrowException(new DoesNotExistException(''));
		$this->pcmService->expects($this->once())
			->method('syncRemoteMessage')
			->with($room, $participant, 42)
			->willReturn($message);
		$this->federationChatNotifier->expects($this->once())
			->method('handleReaction')
			->with($room, $participant, $message, $notification['reactionData']);

		$this->provider->notificationReceived(FederationManager::NOTIFICATION_REACTION_ADDED, '1', $notification);
	}

	public function testReactionAddedSkipsWhenSyncFails(): void {
		[, , $notification] = $this->mockReactionRoom('remoteToken');

		$this->pcmService->method('findByRemote')->willThrowException(new DoesNotExistException(''));
		$this->pcmService->method('syncRemoteMessage')->willThrowException(new CannotReachRemoteException());
		$this->federationChatNotifier->expects($this->never())->method('handleReaction');

		$this->assertSame([], $this->provider->notificationReceived(FederationManager::NOTIFICATION_REACTION_ADDED, '1', $notification));
	}
}
