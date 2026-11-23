<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Federation;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\Config;
use OCA\Talk\Federation\CloudFederationProviderTalk;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Manager;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Notification\FederationChatNotifier;
use OCA\Talk\Service\ConversationUnarchiveService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ProxyCacheMessageService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\Exceptions\BadRequestException;
use OCP\Federation\ICloudIdManager;
use OCP\ICacheFactory;
use OCP\ISession;
use OCP\IUserManager;
use OCP\Notification\IManager as INotificationManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CloudFederationProviderTalkTest extends TestCase {
	protected CloudFederationProviderTalk $provider;

	public function setUp(): void {
		parent::setUp();

		$this->provider = new CloudFederationProviderTalk(
			$this->createMock(ICloudIdManager::class),
			$this->createMock(IUserManager::class),
			$this->createMock(AddressHandler::class),
			$this->createMock(FederationManager::class),
			$this->createMock(Config::class),
			$this->createMock(IAppConfig::class),
			$this->createMock(INotificationManager::class),
			$this->createMock(ParticipantService::class),
			$this->createMock(RoomService::class),
			$this->createMock(AttendeeMapper::class),
			$this->createMock(InvitationMapper::class),
			$this->createMock(Manager::class),
			$this->createMock(ISession::class),
			$this->createMock(IEventDispatcher::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(ProxyCacheMessageMapper::class),
			$this->createMock(ProxyCacheMessageService::class),
			$this->createMock(FederationChatNotifier::class),
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
}
