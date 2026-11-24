<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Federation;

use OCA\FederatedFileSharing\AddressHandler;
use OCA\Talk\Federation\BackendNotifier;
use OCA\Talk\Federation\FederationManager;
use OCA\Talk\Federation\RestrictionValidator;
use OCA\Talk\Model\RetryNotificationMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Federation\ICloudFederationFactory;
use OCP\Federation\ICloudFederationNotification;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Federation\ICloudIdManager;
use OCP\Http\Client\IResponse;
use OCP\IURLGenerator;
use OCP\IUserManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BackendNotifierTest extends TestCase {
	protected ICloudFederationFactory&MockObject $cloudFederationFactory;
	protected AddressHandler&MockObject $addressHandler;
	protected ICloudFederationProviderManager&MockObject $federationProviderManager;
	protected IURLGenerator&MockObject $url;
	protected RetryNotificationMapper&MockObject $retryNotificationMapper;
	protected BackendNotifier $backendNotifier;

	public function setUp(): void {
		parent::setUp();

		$this->cloudFederationFactory = $this->createMock(ICloudFederationFactory::class);
		$this->addressHandler = $this->createMock(AddressHandler::class);
		$this->federationProviderManager = $this->createMock(ICloudFederationProviderManager::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->retryNotificationMapper = $this->createMock(RetryNotificationMapper::class);

		$this->backendNotifier = new BackendNotifier(
			$this->cloudFederationFactory,
			$this->addressHandler,
			$this->createMock(LoggerInterface::class),
			$this->federationProviderManager,
			$this->createMock(IUserManager::class),
			$this->url,
			$this->retryNotificationMapper,
			$this->createMock(ITimeFactory::class),
			$this->createMock(ICloudIdManager::class),
			$this->createMock(RestrictionValidator::class),
		);

		$this->addressHandler->method('urlContainProtocol')
			->willReturn(true);
		$this->url->method('getAbsoluteURL')
			->willReturn('https://local/');
	}

	protected function createReactionData(): array {
		return [
			'remoteMessageId' => 42,
			'actorType' => 'users',
			'actorId' => 'reactor',
			'actorDisplayName' => 'Reactor',
			'reaction' => '👍',
			'creationDatetime' => '2025-01-01T00:00:00+00:00',
		];
	}

	public function testSendReactionAddedSuccess(): void {
		$notification = $this->createMock(ICloudFederationNotification::class);
		$notification->expects($this->once())
			->method('setMessage')
			->with(
				FederationManager::NOTIFICATION_REACTION_ADDED,
				FederationManager::TALK_ROOM_RESOURCE,
				'1',
				$this->callback(static fn (array $data): bool => $data['sharedSecret'] === 'token'
					&& $data['remoteToken'] === 'room'
					&& $data['reactionData']['reaction'] === '👍'),
			);
		$this->cloudFederationFactory->method('getCloudFederationNotification')
			->willReturn($notification);

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(Http::STATUS_CREATED);
		$this->federationProviderManager->expects($this->once())
			->method('sendCloudNotification')
			->with('https://remote', $notification)
			->willReturn($response);

		$this->assertTrue($this->backendNotifier->sendReactionAdded(
			'https://remote',
			1,
			'token',
			'room',
			$this->createReactionData(),
		));
	}

	public function testSendReactionAddedResourceNotFound(): void {
		$notification = $this->createMock(ICloudFederationNotification::class);
		$this->cloudFederationFactory->method('getCloudFederationNotification')
			->willReturn($notification);

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(Http::STATUS_BAD_REQUEST);
		$response->method('getBody')->willReturn(json_encode(['message' => FederationManager::OCM_RESOURCE_NOT_FOUND], JSON_THROW_ON_ERROR));
		$this->federationProviderManager->method('sendCloudNotification')
			->willReturn($response);

		$this->assertNull($this->backendNotifier->sendReactionAdded(
			'https://remote',
			1,
			'token',
			'room',
			$this->createReactionData(),
		));
	}

	public function testSendReactionAddedDoesNotRetry(): void {
		// A failed send must not be queued for retry: the push could arrive
		// long after the reaction was removed again
		$notification = $this->createMock(ICloudFederationNotification::class);
		$this->cloudFederationFactory->method('getCloudFederationNotification')
			->willReturn($notification);

		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(Http::STATUS_INTERNAL_SERVER_ERROR);
		$response->method('getBody')->willReturn('');
		$this->federationProviderManager->method('sendCloudNotification')
			->willReturn($response);

		$this->retryNotificationMapper->expects($this->never())
			->method('insert');

		$this->assertFalse($this->backendNotifier->sendReactionAdded(
			'https://remote',
			1,
			'token',
			'room',
			$this->createReactionData(),
		));
	}
}
