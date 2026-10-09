<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Service;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use OC\Memcache\ArrayCache;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Exceptions\ReactionAlreadyExistsException;
use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Transport;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Model\EventMap;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Service\AccountService;
use OCA\Talk\Matrix\Service\SendException;
use OCA\Talk\Matrix\Service\SendService;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\ICacheFactory;
use OCP\IUserManager;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Test\TestCase;

class SendServiceTest extends TestCase {
	private AccountService&MockObject $accountService;
	private MatrixRoomMapper&MockObject $roomMapper;
	private ChatManager&MockObject $chatManager;
	private ReactionManager&MockObject $reactionManager;
	private ICommentsManager&MockObject $commentsManager;
	private Room&MockObject $room;
	private Participant&MockObject $participant;
	private ?Account $account;
	private bool $encrypted = false;
	private array $powerLevels = [];
	/** @var array<string, EventMap> */
	private array $eventMaps = [];
	/** @var list<ResponseInterface> Answers of the homeserver in order */
	private array $responses = [];
	/** @var list<RequestInterface> */
	private array $requests = [];
	/** @var list<int> */
	private array $sleeps = [];
	private SendService $service;

	protected function setUp(): void {
		parent::setUp();
		$this->account = Account::fromRow(['id' => '7', 'user_id' => 'alice', 'mxid' => '@alice:example.org', 'status' => Account::STATUS_ACTIVE]);
		$this->accountService = $this->createMock(AccountService::class);
		$this->accountService->method('getForUser')->willReturnCallback(fn (string $userId): ?Account => $userId === 'alice' ? $this->account : null);

		$http = new class($this) implements ClientInterface {
			public function __construct(
				private readonly SendServiceTest $test,
			) {
			}

			public function sendRequest(RequestInterface $request): ResponseInterface {
				return $this->test->respond($request);
			}
		};
		$this->accountService->method('getClient')->willReturnCallback(function () use ($http): Client {
			$factory = new HttpFactory();
			$transport = new Transport('https://matrix.example.org', $http, $factory, $factory);
			$transport->setSleep(function (int $milliseconds): void {
				$this->sleeps[] = $milliseconds;
			});
			return (new Client($transport))->withAccessToken('syt_token');
		});

		$accountMapper = $this->createMock(AccountMapper::class);
		$accountMapper->method('getByUserId')->willReturnCallback(static fn (string $userId): Account => match ($userId) {
			'bob' => Account::fromRow(['id' => '8', 'user_id' => 'bob', 'mxid' => '@bob:example.org']),
			default => throw new DoesNotExistException(''),
		});

		$this->roomMapper = $this->createMock(MatrixRoomMapper::class);
		$this->roomMapper->method('getById')->with('100')->willReturnCallback(fn (): MatrixRoom => MatrixRoom::fromRow([
			'id' => '100',
			'room_id' => 23,
			'matrix_room_id' => '!room:example.org',
			'encrypted' => $this->encrypted,
			'power_levels' => $this->powerLevels !== [] ? json_encode($this->powerLevels) : null,
			'creator' => '@carol:example.org',
		]));

		$eventMapMapper = $this->createMock(EventMapMapper::class);
		$eventMapMapper->method('insertIfNew')->willReturnCallback(function (EventMap $eventMap): bool {
			if (isset($this->eventMaps[$eventMap->getEventId()])) {
				return false;
			}
			$this->eventMaps[$eventMap->getEventId()] = $eventMap;
			return true;
		});
		$eventMapMapper->method('findByEventId')->willReturnCallback(fn (string $matrixRoomId, string $eventId): ?EventMap => $this->eventMaps[$eventId] ?? null);
		$eventMapMapper->method('findByCommentId')->willReturnCallback(function (string $matrixRoomId, int $commentId): ?EventMap {
			foreach ($this->eventMaps as $eventMap) {
				if ($eventMap->getCommentId() === $commentId) {
					return $eventMap;
				}
			}
			return null;
		});

		$this->chatManager = $this->createMock(ChatManager::class);
		$this->reactionManager = $this->createMock(ReactionManager::class);
		$this->commentsManager = $this->createMock(ICommentsManager::class);
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('getDisplayName')->willReturnMap([['bob', 'Bob']]);
		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom->method('generate')->willReturn('transaction');
		$timeFactory = $this->createMock(ITimeFactory::class);
		$timeFactory->method('getDateTime')->willReturn(new \DateTime('2026-10-09 12:00:00'));
		$cacheFactory = $this->createMock(ICacheFactory::class);
		$cacheFactory->method('createDistributed')->willReturn(new ArrayCache(''));

		$this->room = $this->createMock(Room::class);
		$this->room->method('getObjectId')->willReturn('100');
		$this->participant = $this->createMock(Participant::class);
		$this->participant->method('getAttendee')->willReturn(Attendee::fromRow([
			'actor_type' => Attendee::ACTOR_USERS,
			'actor_id' => 'alice',
			'display_name' => 'Alice',
		]));

		$this->service = new SendService(
			$this->accountService,
			$accountMapper,
			$this->roomMapper,
			$eventMapMapper,
			$this->chatManager,
			$this->reactionManager,
			$this->commentsManager,
			$userManager,
			$secureRandom,
			$timeFactory,
			$cacheFactory,
		);
	}

	public function respond(RequestInterface $request): ResponseInterface {
		$this->requests[] = $request;
		return array_shift($this->responses) ?? new Response(404);
	}

	private function json(int $status, array $body): Response {
		return new Response($status, ['Content-Type' => 'application/json'], json_encode($body, JSON_THROW_ON_ERROR));
	}

	private function mapped(string $eventId, int $commentId, string $sender, string $type = 'm.room.message'): void {
		$this->eventMaps[$eventId] = EventMap::fromRow([
			'id' => '1' . $commentId,
			'matrix_room_id' => '100',
			'event_id' => $eventId,
			'event_type' => $type,
			'sender' => $sender,
			'comment_id' => $commentId,
		]);
	}

	private function comment(int $id): IComment&MockObject {
		$comment = $this->createMock(IComment::class);
		$comment->method('getId')->willReturn((string)$id);
		return $comment;
	}

	private function requestBody(int $index): array {
		return json_decode((string)$this->requests[$index]->getBody(), true);
	}

	public function testSendMessage(): void {
		$parent = $this->comment(11);
		$this->mapped('$parent', 11, '@bob:example.org');
		$this->responses[] = $this->json(200, ['event_id' => '$sent']);
		$comment = $this->comment(12);
		$this->chatManager->expects(self::once())
			->method('sendMessage')
			->with($this->room, $this->participant, Attendee::ACTOR_USERS, 'alice', 'Hi @"bob", **welcome**', self::anything(), $parent, 'ref', true)
			->willReturn($comment);

		self::assertSame($comment, $this->service->sendMessage($this->room, $this->participant, 'Hi @"bob", **welcome**', $parent, 'ref', true));

		self::assertSame('PUT', $this->requests[0]->getMethod());
		self::assertSame('/_matrix/client/v3/rooms/%21room%3Aexample.org/send/m.room.message/nctransaction', $this->requests[0]->getUri()->getPath());
		self::assertSame([
			'msgtype' => 'm.text',
			'body' => 'Hi Bob, **welcome**',
			'format' => 'org.matrix.custom.html',
			'formatted_body' => 'Hi <a href="https://matrix.to/#/%40bob%3Aexample.org">Bob</a>, <strong>welcome</strong>',
			'm.mentions' => ['user_ids' => ['@bob:example.org']],
			'm.relates_to' => ['m.in_reply_to' => ['event_id' => '$parent']],
		], $this->requestBody(0));
		self::assertSame(12, $this->eventMaps['$sent']->getCommentId());
		self::assertSame('@alice:example.org', $this->eventMaps['$sent']->getSender());
	}

	public function testSendMessageAlreadyMirroredBySync(): void {
		$this->mapped('$sent', 12, '@alice:example.org');
		$comment = $this->comment(12);
		$this->commentsManager->method('get')->with('12')->willReturn($comment);
		$this->responses[] = $this->json(200, ['event_id' => '$sent']);
		$this->chatManager->expects(self::never())->method('sendMessage');

		self::assertSame($comment, $this->service->sendMessage($this->room, $this->participant, 'Hi', null, '', false));
	}

	public static function dataTextContent(): array {
		return [
			'plain text' => ['Hello', ['msgtype' => 'm.text', 'body' => 'Hello', 'm.mentions' => []]],
			'mention all' => ['Hello @all', ['msgtype' => 'm.text', 'body' => 'Hello @room', 'format' => 'org.matrix.custom.html', 'formatted_body' => 'Hello @room', 'm.mentions' => ['room' => true]]],
			'unknown user' => ['Hello @"carol"', ['msgtype' => 'm.text', 'body' => 'Hello @"carol"', 'm.mentions' => []]],
		];
	}

	#[DataProvider('dataTextContent')]
	public function testGetTextContent(string $message, array $expected): void {
		self::assertSame($expected, json_decode(json_encode($this->service->getTextContent($message), JSON_THROW_ON_ERROR), true));
	}

	public function testEncryptedRoomIsRefused(): void {
		$this->encrypted = true;

		$this->expectExceptionObject(new SendException('encrypted', 400));
		$this->service->sendMessage($this->room, $this->participant, 'Hi', null, '', false);
	}

	public function testRejectedTokenMarksAccount(): void {
		$this->responses[] = $this->json(401, ['errcode' => 'M_UNKNOWN_TOKEN', 'error' => 'Token expired']);
		$this->accountService->expects(self::once())->method('markTokenInvalid')->with($this->account, 'Token expired');
		$this->chatManager->expects(self::never())->method('sendMessage');

		$this->expectExceptionObject(new SendException('account', 403));
		$this->service->sendMessage($this->room, $this->participant, 'Hi', null, '', false);
	}

	public function testInactiveAccountIsRefused(): void {
		$this->account->setStatus(Account::STATUS_TOKEN_INVALID);

		$this->expectExceptionObject(new SendException('account', 403));
		$this->service->sendMessage($this->room, $this->participant, 'Hi', null, '', false);
	}

	public function testServerErrorsAreRetried(): void {
		$this->responses = [
			$this->json(429, ['errcode' => 'M_LIMIT_EXCEEDED', 'error' => 'Slow down', 'retry_after_ms' => 300]),
			$this->json(502, ['errcode' => 'M_UNKNOWN', 'error' => 'Bad gateway']),
			$this->json(502, ['errcode' => 'M_UNKNOWN', 'error' => 'Bad gateway']),
		];
		$this->chatManager->expects(self::never())->method('sendMessage');

		try {
			$this->service->sendMessage($this->room, $this->participant, 'Hi', null, '', false);
			self::fail('Expected exception');
		} catch (SendException $e) {
			self::assertSame('matrix', $e->getMessage());
			self::assertSame(502, $e->getStatus());
		}
		self::assertCount(3, $this->requests);
		self::assertSame([300, 500], $this->sleeps);
		self::assertSame([], $this->eventMaps);
	}

	public function testEditMessage(): void {
		$comment = $this->comment(11);
		$this->mapped('$original', 11, '@alice:example.org');
		$this->responses[] = $this->json(200, ['event_id' => '$edit']);
		$systemMessage = $this->comment(12);
		$this->chatManager->expects(self::once())
			->method('editMessage')
			->with($this->room, $comment, $this->participant, self::anything(), 'Fixed')
			->willReturn($systemMessage);

		self::assertSame($systemMessage, $this->service->editMessage($this->room, $this->participant, $comment, 'Fixed'));
		self::assertSame([
			'msgtype' => 'm.text',
			'body' => '* Fixed',
			'm.mentions' => [],
			'm.new_content' => ['msgtype' => 'm.text', 'body' => 'Fixed', 'm.mentions' => []],
			'm.relates_to' => ['rel_type' => 'm.replace', 'event_id' => '$original'],
		], $this->requestBody(0));
		self::assertArrayHasKey('$edit', $this->eventMaps);
	}

	public function testEditMessageOfOtherSender(): void {
		$comment = $this->comment(11);
		$this->mapped('$original', 11, '@bob:example.org');
		$this->chatManager->expects(self::never())->method('editMessage');

		$this->expectExceptionObject(new SendException('permission', 403));
		$this->service->editMessage($this->room, $this->participant, $comment, 'Fixed');
	}

	public function testDeleteMessageOfOtherSenderRequiresPowerLevel(): void {
		$comment = $this->comment(11);
		$this->mapped('$original', 11, '@bob:example.org');
		$this->chatManager->expects(self::never())->method('deleteMessage');

		$this->expectExceptionObject(new SendException('permission', 403));
		$this->service->deleteMessage($this->room, $this->participant, $comment);
	}

	public function testDeleteMessageAsModerator(): void {
		$this->powerLevels = ['users' => ['@alice:example.org' => 50]];
		$comment = $this->comment(11);
		$this->mapped('$original', 11, '@bob:example.org');
		$this->responses[] = $this->json(200, ['event_id' => '$redaction']);
		$systemMessage = $this->comment(12);
		$this->chatManager->expects(self::once())->method('deleteMessage')->with($this->room, $comment, $this->participant)->willReturn($systemMessage);

		self::assertSame($systemMessage, $this->service->deleteMessage($this->room, $this->participant, $comment));
		self::assertSame('/_matrix/client/v3/rooms/%21room%3Aexample.org/redact/%24original/nctransaction', $this->requests[0]->getUri()->getPath());
		self::assertArrayHasKey('$redaction', $this->eventMaps);
	}

	public function testAddReaction(): void {
		$this->mapped('$original', 11, '@bob:example.org');
		$this->commentsManager->method('getReactionComment')->willThrowException(new NotFoundException());
		$this->responses[] = $this->json(200, ['event_id' => '$reaction']);
		$reaction = $this->comment(12);
		$this->reactionManager->expects(self::once())
			->method('addReactionMessage')
			->with($this->room, Attendee::ACTOR_USERS, 'alice', 'Alice', 11, '👍')
			->willReturn($reaction);

		self::assertSame($reaction, $this->service->addReaction($this->room, $this->participant, 11, '👍'));
		self::assertSame(['m.relates_to' => ['rel_type' => 'm.annotation', 'event_id' => '$original', 'key' => '👍']], $this->requestBody(0));
		self::assertSame(12, $this->eventMaps['$reaction']->getCommentId());
	}

	public function testAddExistingReactionIsNotSent(): void {
		$this->mapped('$original', 11, '@bob:example.org');
		$this->commentsManager->method('getReactionComment')->with(11, Attendee::ACTOR_USERS, 'alice', '👍')->willReturn($this->comment(12));

		$this->expectException(ReactionAlreadyExistsException::class);
		$this->service->addReaction($this->room, $this->participant, 11, '👍');
	}

	public function testRemoveReaction(): void {
		$this->mapped('$original', 11, '@bob:example.org');
		$this->mapped('$reaction', 12, '@alice:example.org', 'm.reaction');
		$this->commentsManager->method('getReactionComment')->with(11, Attendee::ACTOR_USERS, 'alice', '👍')->willReturn($this->comment(12));
		$this->responses[] = $this->json(200, ['event_id' => '$redaction']);
		$this->reactionManager->expects(self::once())
			->method('deleteReactionMessage')
			->with($this->room, Attendee::ACTOR_USERS, 'alice', 'Alice', 11, '👍');

		$this->service->removeReaction($this->room, $this->participant, 11, '👍');
		self::assertSame('/_matrix/client/v3/rooms/%21room%3Aexample.org/redact/%24reaction/nctransaction', $this->requests[0]->getUri()->getPath());
	}

	public function testSendReadMarkerOnlyOnce(): void {
		$this->mapped('$message', 11, '@bob:example.org');
		$this->responses[] = $this->json(200, []);

		$this->service->sendReadMarker($this->room, $this->participant, 11);
		$this->service->sendReadMarker($this->room, $this->participant, 11);
		$this->service->sendReadMarker($this->room, $this->participant, 10);

		self::assertCount(1, $this->requests);
		self::assertSame('/_matrix/client/v3/rooms/%21room%3Aexample.org/read_markers', $this->requests[0]->getUri()->getPath());
		self::assertSame(['m.read' => '$message', 'm.fully_read' => '$message'], $this->requestBody(0));
	}
}
