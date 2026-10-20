<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\DataObjects\AccountId;
use OCA\Talk\DataObjects\RegisterAccountData;
use OCA\Talk\Service\HostedSignalingServerService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Services\IAppConfig;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClient;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class HostedSignalingServerServiceTest extends TestCase {
	protected IClient&MockObject $client;
	protected IEventDispatcher&MockObject $dispatcher;
	protected HostedSignalingServerService $service;

	public function setUp(): void {
		parent::setUp();

		$config = $this->createMock(IConfig::class);
		$config->method('getSystemValue')->willReturnArgument(1);
		$this->client = $this->createMock(IClient::class);
		$clientService = $this->createMock(IClientService::class);
		$clientService->method('newClient')->willReturn($this->client);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);

		$this->service = new HostedSignalingServerService(
			$config,
			$this->createMock(IAppConfig::class),
			$clientService,
			$this->createMock(LoggerInterface::class),
			$this->createMock(IL10N::class),
			$this->createMock(ISecureRandom::class),
			$this->dispatcher,
		);
	}

	protected function createResponse(int $status, string $body = ''): IResponse {
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn($status);
		$response->method('getBody')->willReturn($body);
		return $response;
	}

	public function testRegisterAccountLogsCriticalActionWithoutContactData(): void {
		$this->client->method('post')->willReturn($this->createResponse(Http::STATUS_CREATED, json_encode(['account_id' => 'account-1'])));

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.hosted_signaling_account.created'
				&& $event->getParameters() === ['accountId' => 'account-1', 'url' => 'https://cloud.example']));

		$this->service->registerAccount(new RegisterAccountData('https://cloud.example', 'Admin', 'admin@example.com', 'en', 'DE'));
	}

	public function testDeleteAccountLogsCriticalAction(): void {
		$this->client->method('delete')->willReturn($this->createResponse(Http::STATUS_NO_CONTENT));

		$this->dispatcher->expects($this->once())
			->method('dispatchTyped')
			->with($this->callback(fn (CriticalActionPerformedEvent $event): bool => $event->getOperation() === 'spreed.hosted_signaling_account.deleted'
				&& $event->getParameters() === ['accountId' => 'account-1']));

		$this->service->deleteAccount(new AccountId('account-1'));
	}
}
