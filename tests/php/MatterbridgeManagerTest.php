<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php;

use OC\Authentication\Token\IProvider;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Manager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Security\IRemoteHostValidator;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

#[Group(name: 'DB')]
class MatterbridgeManagerTest extends TestCase {
	protected IDBConnection&MockObject $db;
	protected IConfig&MockObject $config;
	protected IURLGenerator&MockObject $url;
	protected IUserManager&MockObject $userManager;
	protected Manager&MockObject $manager;
	protected ParticipantService&MockObject $participantService;
	protected ChatManager&MockObject $chatManager;
	protected IProvider&MockObject $tokenProvider;
	protected ISecureRandom&MockObject $random;
	protected IAvatarManager&MockObject $avatarManager;
	protected LoggerInterface&MockObject $logger;
	protected ITimeFactory&MockObject $timeFactory;
	protected IRemoteHostValidator&MockObject $hostValidator;
	protected MatterbridgeManager $matterbridgeManager;

	public function setUp(): void {
		parent::setUp();

		$this->db = $this->createMock(IDBConnection::class);
		$this->config = $this->createMock(IConfig::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->manager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->tokenProvider = $this->createMock(IProvider::class);
		$this->random = $this->createMock(ISecureRandom::class);
		$this->avatarManager = $this->createMock(IAvatarManager::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->hostValidator = $this->createMock(IRemoteHostValidator::class);

		$this->matterbridgeManager = new MatterbridgeManager(
			$this->db,
			$this->config,
			$this->url,
			$this->userManager,
			$this->manager,
			$this->participantService,
			$this->chatManager,
			$this->tokenProvider,
			$this->random,
			$this->avatarManager,
			$this->logger,
			$this->timeFactory,
			$this->hostValidator,
		);
	}

	public static function dataValidateParts(): array {
		return [
			'Only strings allowed' => [
				[['type' => false]],
				[],
				true,
			],
			'Only strings allowed unless editing' => [
				[['type' => 'other', 'editing' => true]],
				[],
				false,
			],
			'Mattermost - Host only' => [
				[['type' => 'mattermost', 'server' => 'yourmattermostserver.example.tld']],
				[['yourmattermostserver.example.tld', true]],
				false,
			],
			'IRC - With port' => [
				[['type' => 'irc', 'server' => 'irc.example.tld:6667']],
				[['irc.example.tld', true]],
				false,
			],
			'Rocketchat - Full' => [
				[['type' => 'rocketchat', 'server' => 'https://yourrocketchatserver.example.tld:443']],
				[['yourrocketchatserver.example.tld', true]],
				false,
			],
			'Rocketchat - Internal' => [
				[['type' => 'rocketchat', 'server' => 'https://localhost']],
				[['localhost', false]],
				true,
			],
			'Talk' => [
				[['type' => 'nctalk', 'server' => 'https://cloud.example2.tld']],
				[['cloud.example2.tld', true]],
				false,
			],
			'Talk - Internal' => [
				[['type' => 'nctalk', 'server' => 'https://cloud.example.tld']],
				[['cloud.example.tld', false]],
				false,
			],
			'Talk - Internal port scan' => [
				[['type' => 'nctalk', 'server' => 'https://cloud.example.tld:8080']],
				[['cloud.example.tld', false]],
				true,
			],
		];
	}

	#[DataProvider('dataValidateParts')]
	public function testValidateParts(array $parts, array $hostValidatorData, bool $throws): void {
		$this->hostValidator->method('isValid')
			->willReturnMap($hostValidatorData);
		$this->url->method('getAbsoluteURL')
			->willReturn('https://cloud.example.tld/');

		if ($throws) {
			$this->expectException(\InvalidArgumentException::class);
			self::invokePrivate($this->matterbridgeManager, 'validateParts', [$parts]);
		} else {
			$this->assertEquals($parts, self::invokePrivate($this->matterbridgeManager, 'validateParts', [$parts]));
		}
	}
}
