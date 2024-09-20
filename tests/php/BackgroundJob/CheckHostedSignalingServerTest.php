<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\BackgroundJob;

use OCA\Talk\BackgroundJob\CheckHostedSignalingServer;
use OCA\Talk\Service\HostedSignalingServerService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\Notification\IManager;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class CheckHostedSignalingServerTest extends TestCase {
	protected ITimeFactory&MockObject $timeFactory;
	protected HostedSignalingServerService&MockObject $hostedSignalingServerService;
	protected IConfig&MockObject $config;
	protected IManager&MockObject $notificationManager;
	protected IGroupManager&MockObject $groupManager;
	protected IURLGenerator&MockObject $urlGenerator;
	protected LoggerInterface&MockObject $logger;

	public function setUp(): void {
		parent::setUp();

		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->hostedSignalingServerService = $this->createMock(HostedSignalingServerService::class);
		$this->config = $this->createMock(IConfig::class);
		$this->notificationManager = $this->createMock(IManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->logger = $this->createMock(LoggerInterface::class);
	}

	public function getBackgroundJob(): CheckHostedSignalingServer {
		return new CheckHostedSignalingServer(
			$this->timeFactory,
			$this->hostedSignalingServerService,
			$this->config,
			$this->notificationManager,
			$this->groupManager,
			$this->urlGenerator,
			$this->logger
		);
	}

	public function testRunWithNoChange(): void {
		$backgroundJob = $this->getBackgroundJob();

		$this->config
			->method('getAppValue')
			->will($this->returnValueMap([
				['spreed', 'hosted-signaling-server-account-id', '', 'my-account-id'],
				['spreed', 'hosted-signaling-server-account', '{}', '{"status": "pending"}']
			]));

		$this->hostedSignalingServerService->expects($this->once())
			->method('fetchAccountInfo')
			->willReturn(['status' => 'pending']);

		self::invokePrivate($backgroundJob, 'run', ['']);
	}

	public function testRunWithPendingToActiveChange(): void {
		$backgroundJob = $this->getBackgroundJob();
		$newStatus = [
			'status' => 'active',
			'signaling' => [
				'url' => 'signaling-url',
				'secret' => 'signaling-secret',
			],
		];

		$this->config
			->method('getAppValue')
			->willReturnMap([
				['spreed', 'hosted-signaling-server-account-id', '', 'my-account-id'],
				['spreed', 'hosted-signaling-server-account', '{}', '{"status": "pending"}']
			]);
		$this->config->expects($this->once())
			->method('deleteAppValue')
			->with('spreed', 'signaling_mode');

		$expectedCalls = [
			['spreed', 'signaling_servers', '{"servers":[{"server":"signaling-url","verify":true}],"secret":"signaling-secret"}'],
			['spreed', 'hosted-signaling-server-account', json_encode($newStatus)],
		];

		$i = 0;
		$this->config->expects($this->exactly(count($expectedCalls)))
			->method('setAppValue')
			->willReturnCallback(function () use ($expectedCalls, &$i): void {
				Assert::assertArrayHasKey($i, $expectedCalls);
				Assert::assertSame($expectedCalls[$i], func_get_args());
				$i++;
			});

		$group = $this->createMock(IGroup::class);
		$this->groupManager->expects($this->once())
			->method('get')
			->with('admin')
			->willReturn($group);
		$group->expects($this->once())
			->method('getUsers')
			->willReturn([]);

		$this->hostedSignalingServerService->expects($this->once())
			->method('fetchAccountInfo')
			->willReturn($newStatus);

		self::invokePrivate($backgroundJob, 'run', ['']);
	}
}
