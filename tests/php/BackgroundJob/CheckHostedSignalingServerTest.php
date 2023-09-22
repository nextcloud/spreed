<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Morris Jobke <hey@morrisjobke.de>
 *
 * @author Morris Jobke <hey@morrisjobke.de>
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
	/** @var ITimeFactory|MockObject */
	protected $timeFactory;
	/** @var HostedSignalingServerService|MockObject */
	protected $hostedSignalingServerService;
	/** @var IConfig|MockObject */
	protected $config;
	/** @var IManager|MockObject */
	protected $notificationManager;
	/** @var IGroupManager|MockObject */
	protected $groupManager;
	/** @var IURLGenerator|MockObject */
	protected $urlGenerator;
	/** @var LoggerInterface|MockObject */
	protected $logger;

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

	public function testRunWithNoChange() {
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

		$this->invokePrivate($backgroundJob, 'run', ['']);
	}

	public function testRunWithPendingToActiveChange() {
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
			->willReturnCallback(function () use ($expectedCalls, &$i) {
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

		$this->invokePrivate($backgroundJob, 'run', ['']);
	}
}
