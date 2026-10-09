<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use GuzzleHttp\Psr7\Response as Psr7Response;
use OCA\Talk\Controller\AvatarController;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\MatrixMember;
use OCA\Talk\Matrix\Model\MatrixMemberMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\RoomFormatter;
use OCP\Federation\ICloudIdManager;
use OCP\Files\SimpleFS\InMemoryFile;
use OCP\IAvatarManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class AvatarControllerTest extends TestCase {
	private Client&MockObject $client;
	private MatrixMemberMapper&MockObject $memberMapper;
	private AvatarController $controller;

	protected function setUp(): void {
		parent::setUp();
		$avatarService = $this->createMock(AvatarService::class);
		$avatarService->method('getPersonPlaceholder')->willReturn(new InMemoryFile('placeholder', '<svg/>'));
		$this->client = $this->createMock(Client::class);
		$accountService = $this->createMock(AccountService::class);
		$accountService->method('getForUser')->with('alice')->willReturn(Account::fromRow(['id' => '7', 'user_id' => 'alice', 'status' => Account::STATUS_ACTIVE]));
		$accountService->method('getClient')->willReturn($this->client);
		$this->memberMapper = $this->createMock(MatrixMemberMapper::class);

		$this->controller = new AvatarController(
			'spreed',
			$this->createMock(IRequest::class),
			$this->createMock(RoomFormatter::class),
			$avatarService,
			$this->createMock(IUserSession::class),
			$this->createMock(IL10N::class),
			$this->createMock(LoggerInterface::class),
			$this->createMock(ICloudIdManager::class),
			$this->createMock(IAvatarManager::class),
			$accountService,
			$this->memberMapper,
		);

		$room = $this->createMock(Room::class);
		$room->method('getObjectType')->willReturn(Room::OBJECT_TYPE_MATRIX);
		$room->method('getObjectId')->willReturn('100');
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn(Attendee::fromRow(['actor_type' => Attendee::ACTOR_USERS, 'actor_id' => 'alice']));
		$this->controller->setRoom($room);
		$this->controller->setParticipant($participant);
	}

	private function member(?string $avatarUrl): void {
		$this->memberMapper->method('getForRoom')->with('100')->willReturn([
			'@bob:example.org' => MatrixMember::fromRow(['mxid' => '@bob:example.org', 'membership' => 'join', 'avatar_url' => $avatarUrl]),
		]);
	}

	public function testMatrixUserAvatar(): void {
		$this->member('mxc://example.org/bob');
		$this->client->expects(self::once())
			->method('downloadThumbnail')
			->with('mxc://example.org/bob', 64)
			->willReturn(new Psr7Response(200, ['Content-Type' => 'image/jpeg'], 'jpeg'));

		$response = $this->controller->getMatrixUserAvatar(64, '@bob:example.org');

		self::assertSame('image/jpeg', $response->getHeaders()['Content-Type']);
		self::assertSame('jpeg', self::invokePrivate($response, 'file')->getContent());
	}

	public function testMatrixUserWithoutAvatar(): void {
		$this->member(null);
		$this->client->expects(self::never())->method('downloadThumbnail');

		self::assertSame('<svg/>', self::invokePrivate($this->controller->getMatrixUserAvatar(64, '@bob:example.org'), 'file')->getContent());
	}

	public function testMatrixUserAvatarThatIsNoImage(): void {
		$this->member('mxc://example.org/bob');
		$this->client->method('downloadThumbnail')->willReturn(new Psr7Response(200, ['Content-Type' => 'text/html'], '<script></script>'));

		self::assertSame('<svg/>', self::invokePrivate($this->controller->getMatrixUserAvatar(512, '@bob:example.org'), 'file')->getContent());
	}

	public function testUnknownMatrixUser(): void {
		$this->member('mxc://example.org/bob');
		$this->client->expects(self::never())->method('downloadThumbnail');

		self::assertSame('<svg/>', self::invokePrivate($this->controller->getMatrixUserAvatar(64, '@mallory:example.org'), 'file')->getContent());
	}
}
