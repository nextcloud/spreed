<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use GuzzleHttp\Psr7\Response as Psr7Response;
use OCA\Talk\Controller\MatrixMediaController;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\EventMap;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\StreamResponse;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\IRequest;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class MatrixMediaControllerTest extends TestCase {
	private ParticipantService&MockObject $participantService;
	private Client&MockObject $client;
	private IComment&MockObject $comment;
	private MatrixMediaController $controller;

	protected function setUp(): void {
		parent::setUp();
		$this->client = $this->createMock(Client::class);
		$accountService = $this->createMock(AccountService::class);
		$accountService->method('getForUser')->with('alice')->willReturn(Account::fromRow(['id' => '7', 'user_id' => 'alice', 'status' => Account::STATUS_ACTIVE]));
		$accountService->method('getClient')->willReturn($this->client);
		$eventMapMapper = $this->createMock(EventMapMapper::class);
		$eventMapMapper->method('getById')->with('55')->willReturn(EventMap::fromRow(['id' => '55', 'matrix_room_id' => '100', 'comment_id' => 11]));
		$roomMapper = $this->createMock(MatrixRoomMapper::class);
		$roomMapper->method('getById')->with('100')->willReturn(MatrixRoom::fromRow(['id' => '100', 'room_id' => 23]));
		$room = $this->createMock(Room::class);
		$room->method('getId')->willReturn(23);
		$manager = $this->createMock(Manager::class);
		$manager->method('getRoomById')->with(23)->willReturn($room);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->comment = $this->createMock(IComment::class);
		$this->comment->method('getMessage')->willReturn(json_encode(['message' => 'object_shared', 'parameters' => ['metaData' => [
			'type' => 'highlight',
			'id' => 'matrix-media/55',
			'name' => 'cat "1".png',
			'mxc' => 'mxc://example.org/abc',
		]]]));
		$commentsManager = $this->createMock(ICommentsManager::class);
		$commentsManager->method('get')->with('11')->willReturn($this->comment);

		$this->controller = new MatrixMediaController(
			'spreed',
			$this->createMock(IRequest::class),
			'alice',
			$accountService,
			$eventMapMapper,
			$roomMapper,
			$manager,
			$this->participantService,
			$commentsManager,
		);
	}

	public function testDownloadImage(): void {
		$this->comment->method('getObjectId')->willReturn('23');
		$this->participantService->method('getParticipantByActor')->with(self::anything(), Attendee::ACTOR_USERS, 'alice')->willReturn($this->createMock(Participant::class));
		$this->client->expects(self::once())
			->method('downloadMedia')
			->with('mxc://example.org/abc')
			->willReturn(new Psr7Response(200, ['Content-Type' => 'image/png; charset=binary'], 'png'));

		$response = $this->controller->download('55');

		self::assertInstanceOf(StreamResponse::class, $response);
		self::assertSame('image/png', $response->getHeaders()['Content-Type']);
		self::assertSame('inline; filename="cat _1_.png"', $response->getHeaders()['Content-Disposition']);
		self::assertSame("default-src 'none'; sandbox", $response->getHeaders()['Content-Security-Policy']);
	}

	public function testDownloadOtherFile(): void {
		$this->comment->method('getObjectId')->willReturn('23');
		$this->participantService->method('getParticipantByActor')->willReturn($this->createMock(Participant::class));
		$this->client->method('downloadMedia')->willReturn(new Psr7Response(200, ['Content-Type' => 'text/html'], '<script></script>'));

		$response = $this->controller->download('55');

		self::assertSame('application/octet-stream', $response->getHeaders()['Content-Type']);
		self::assertStringStartsWith('attachment;', $response->getHeaders()['Content-Disposition']);
	}

	public function testDownloadNotParticipant(): void {
		$this->participantService->method('getParticipantByActor')->willThrowException(new ParticipantNotFoundException());
		$this->client->expects(self::never())->method('downloadMedia');

		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller->download('55')->getStatus());
	}

	public function testDownloadMessageOfOtherConversation(): void {
		$this->comment->method('getObjectId')->willReturn('42');
		$this->participantService->method('getParticipantByActor')->willReturn($this->createMock(Participant::class));
		$this->client->expects(self::never())->method('downloadMedia');

		self::assertSame(Http::STATUS_NOT_FOUND, $this->controller->download('55')->getStatus());
	}
}
