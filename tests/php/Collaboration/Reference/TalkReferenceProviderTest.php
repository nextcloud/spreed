<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Collaboration\Resources;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Collaboration\Reference\TalkReferenceProvider;
use OCA\Talk\Manager;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\IL10N;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TalkReferenceProviderTest extends TestCase {
	protected IURLGenerator&MockObject $urlGenerator;
	protected Manager&MockObject $roomManager;
	protected ParticipantService&MockObject $participantService;
	protected ChatManager&MockObject $chatManager;
	protected ProxyCacheMessageMapper&MockObject $proxyCacheMessageMapper;
	protected AvatarService&MockObject $avatarService;
	protected MessageParser&MockObject $messageParser;
	protected IL10N&MockObject $l;
	protected ?TalkReferenceProvider $provider = null;

	public function setUp(): void {
		parent::setUp();

		$this->urlGenerator = $this->createMock(IURLGenerator::class);
		$this->roomManager = $this->createMock(Manager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->proxyCacheMessageMapper = $this->createMock(ProxyCacheMessageMapper::class);
		$this->avatarService = $this->createMock(AvatarService::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->l = $this->createMock(IL10N::class);

		$this->provider = new TalkReferenceProvider(
			$this->urlGenerator,
			$this->roomManager,
			$this->participantService,
			$this->chatManager,
			$this->proxyCacheMessageMapper,
			$this->avatarService,
			$this->messageParser,
			$this->l,
			'test'
		);
	}

	public static function dataGetTalkAppLinkToken(): array {
		return [
			['https://localhost/', null],
			['https://localhost/call', null],
			['https://localhost/call/abcdef', ['token' => 'abcdef', 'message' => null]],
			['https://localhost/call/abcdef?query=1', ['token' => 'abcdef', 'message' => null]],
			['https://localhost/call/abcdef#hash=1', ['token' => 'abcdef', 'message' => null]],
			['https://localhost/call/abcdef#message_123', ['token' => 'abcdef', 'message' => 123]],
			['https://localhost/call/abcdef?query=1#message_123', ['token' => 'abcdef', 'message' => 123]],
			['https://localhost/call/abcdef?query=1#message_123bcd', ['token' => 'abcdef', 'message' => null]],
		];
	}

	#[DataProvider('dataGetTalkAppLinkToken')]
	public function testGetTalkAppLinkToken(string $reference, ?array $expected): void {
		$this->urlGenerator->expects($this->any())
			->method('getAbsoluteURL')
			->willReturnCallback(static fn ($url) => 'https://localhost' . $url);

		$actual = self::invokePrivate($this->provider, 'getTalkAppLinkToken', [$reference]);
		self::assertSame($expected, $actual);
	}
}
