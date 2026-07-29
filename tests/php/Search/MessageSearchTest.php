<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Search;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Config;
use OCA\Talk\Manager as RoomManager;
use OCA\Talk\Search\MessageSearch;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ThreadService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUserSession;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class MessageSearchTest extends TestCase {
	protected RoomManager&MockObject $roomManager;
	protected ParticipantService&MockObject $participantService;
	protected ChatManager&MockObject $chatManager;
	protected MessageParser&MockObject $messageParser;
	protected ITimeFactory&MockObject $timeFactory;
	protected IURLGenerator&MockObject $url;
	protected IL10N&MockObject $l;
	protected Config&MockObject $talkConfig;
	protected IUserSession&MockObject $userSession;
	protected ThreadService&MockObject $threadService;
	protected MessageSearch $search;

	public function setUp(): void {
		parent::setUp();

		$this->roomManager = $this->createMock(RoomManager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->chatManager = $this->createMock(ChatManager::class);
		$this->messageParser = $this->createMock(MessageParser::class);
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->l = $this->createMock(IL10N::class);
		$this->talkConfig = $this->createMock(Config::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->threadService = $this->createMock(ThreadService::class);

		$this->search = new MessageSearch(
			$this->roomManager,
			$this->participantService,
			$this->chatManager,
			$this->messageParser,
			$this->timeFactory,
			$this->url,
			$this->l,
			$this->talkConfig,
			$this->userSession,
			$this->threadService,
		);
	}

	public static function dataCutMessageToSearchResult(): array {
		return [
			'short message is kept' => [
				'Hello there', 'there', false, 'Hello there',
			],
			'match at the beginning of a long message is kept' => [
				'Hello there, this is a long message that is not cut off at all', 'Hello', false,
				'Hello there, this is a long message that is not cut off at all',
			],
			'match far in the message cuts the beginning' => [
				'This is a really long message with the needle somewhere at the end', 'needle', false,
				'… with the needle somewhere at the end',
			],
			'sensitive cuts before and after' => [
				'This is a really long message with the needle somewhere at the end', 'needle', true,
				'… with the needle somewhere…',
			],
			'sensitive keeps the beginning when the match is early' => [
				'The needle is somewhere at the beginning', 'needle', true,
				'The needle is somewh…',
			],
			'sensitive without trailing content' => [
				'Nothing but the needle', 'needle', true,
				'…g but the needle',
			],
			'sensitive is case insensitive' => [
				'This is a really long message with the NEEDLE somewhere at the end', 'needle', true,
				'… with the NEEDLE somewhere…',
			],
			'sensitive without a match only shows the beginning' => [
				'This is a really long message without the search term', 'unmatched', true,
				'This is a …',
			],
			'sensitive with multibyte characters' => [
				'äöüäöüäöüäöüäöüäöü nädle äöüäöüäöüäöüäöüäöü', 'nädle', true,
				'…äöüäöüäöü nädle äöüäöüäöü…',
			],
		];
	}

	#[DataProvider('dataCutMessageToSearchResult')]
	public function testCutMessageToSearchResult(string $messageStr, string $term, bool $isSensitive, string $expected): void {
		$this->assertSame($expected, self::invokePrivate($this->search, 'cutMessageToSearchResult', [$messageStr, $term, $isSensitive]));
	}
}
