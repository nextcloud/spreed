<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Import;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\MessageParseEvent;
use OCA\Talk\Import\IHistoryReader;
use OCA\Talk\Import\ImportException;
use OCA\Talk\Import\ImportMessage;
use OCA\Talk\Import\ImportParticipant;
use OCA\Talk\Import\ImportReaction;
use OCA\Talk\Import\ImportResult;
use OCA\Talk\Import\ReadMessage;
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\Files\IRootFolder;
use OCP\Server;
use PHPUnit\Framework\Attributes\Group;

/**
 * The history reader against the real database: a person sees what Talk
 * shows them, and reading changes nothing.
 */
#[Group('DB')]
class HistoryReaderTest extends ImportTestCase {
	private IHistoryReader $reader;
	private string $alice;
	private string $bob;
	private string $carol;
	private Room $room;
	/** @var array<string, int> message ID by ref */
	private array $ids = [];
	private int $fileId;

	protected function setUp(): void {
		parent::setUp();
		$this->reader = Server::get(IHistoryReader::class);
		$service = $this->createUser('service');
		$this->alice = $this->createUser('alice');
		$this->bob = $this->createUser('bob');
		$this->carol = $this->createUser('carol');
		$this->room = $this->createGroupRoom($service, 'room', 'Project room');

		$this->fileId = Server::get(IRootFolder::class)->getUserFolder($this->alice)->newFile('plan.txt', 'plan')->getId();

		$results = $this->importer->importMessages(self::APP, $this->room, [
			$this->text('old-post', $this->user($this->alice), '2024-01-02T09:00:00Z', 'Old secret plan', reactions: [
				new ImportReaction($this->user($this->carol), '👍', new \DateTimeImmutable('2024-01-02T09:01:00Z')),
			]),
			new ImportMessage('bob-added', $this->user($this->alice), new \DateTimeImmutable('2024-01-04T00:00:00Z'),
				systemKind: ImportMessage::SYSTEM_USER_ADDED, members: [$this->user($this->bob)]),
			$this->text('new-reply', $this->user($this->alice), '2024-01-05T09:00:00Z', 'Answer to the old plan', 'old-post'),
			new ImportMessage('new-edited', $this->user($this->carol), new \DateTimeImmutable('2024-01-05T09:01:00Z'), 'Edited text',
				edited: new \DateTimeImmutable('2024-01-05T10:00:00Z'), reactions: [
					new ImportReaction($this->user($this->alice), '🎉', new \DateTimeImmutable('2024-01-05T09:02:00Z')),
					new ImportReaction($this->user($this->bob), '🎉', new \DateTimeImmutable('2024-01-05T09:03:00Z')),
				]),
			$this->text('new-file', $this->user($this->alice), '2024-01-05T09:04:00Z', 'The plan', fileIds: [$this->fileId]),
			$this->text('new-deleted', $this->user($this->carol), '2024-01-05T09:05:00Z', 'Deleted secret'),
			$this->text('new-last', $this->user($this->carol), '2024-01-05T09:06:00Z', 'Last one'),
		]);
		foreach ($results as $result) {
			$this->assertSame(ImportResult::STATUS_OK, $result->status, $result->ref . ': ' . $result->errorMessage);
			$this->ids[$result->ref] = $result->id;
		}
		$removed = $this->importer->removeMessages(self::APP, $this->room, ['new-deleted']);
		$this->assertSame(ImportResult::STATUS_OK, $removed[0]->status);

		$this->importer->setParticipants(self::APP, $this->room, [
			new ImportParticipant($this->alice),
			new ImportParticipant($this->bob, historySince: new \DateTimeImmutable('2024-01-04T00:00:00Z')),
			new ImportParticipant($this->carol),
		]);
		$this->importer->finish(self::APP, $this->room);
		$this->room = $this->freshRoom($this->room);
	}

	/**
	 * @param list<ReadMessage> $messages
	 * @return array<int, ReadMessage> by ID
	 */
	private function byId(array $messages): array {
		$out = [];
		foreach ($messages as $message) {
			$out[$message->id] = $message;
		}
		return $out;
	}

	public function testConversationsWithTheHistoryStart(): void {
		$page = $this->reader->conversations($this->bob, '', 10);
		$this->assertSame('', $page['next_cursor']);
		$this->assertCount(1, $page['conversations']);
		$conversation = $page['conversations'][0];
		$this->assertSame($this->room->getToken(), $conversation->token);
		$this->assertSame('Project room', $conversation->name);
		$this->assertSame(IHistoryReader::TYPE_GROUP, $conversation->type);
		$this->assertSame('2024-01-04T00:00:00+00:00', $conversation->historySince?->format(\DATE_ATOM));
		$this->assertSame(3, $conversation->participants, 'the import manages the service account out of the list');
		$this->assertSame(6, $conversation->messages, 'from the history start on');

		$alices = $this->reader->conversations($this->alice, '', 10)['conversations'];
		$this->assertNull($alices[0]->historySince);
		$this->assertSame(7, $alices[0]->messages);

		$this->assertSame([], $this->reader->conversations($this->prefix . '-nobody', '', 10)['conversations']);
	}

	public function testConversationsArePagedById(): void {
		$second = $this->createGroupRoom($this->alice, 'second', 'Second room');
		$this->importer->setParticipants(self::APP, $second, [new ImportParticipant($this->bob)]);

		$first = $this->reader->conversations($this->bob, '', 1);
		$this->assertSame([$this->room->getToken()], array_map(static fn ($c) => $c->token, $first['conversations']));
		$this->assertSame((string)$this->room->getId(), $first['next_cursor']);
		$next = $this->reader->conversations($this->bob, $first['next_cursor'], 1);
		$this->assertSame([$second->getToken()], array_map(static fn ($c) => $c->token, $next['conversations']));
		$this->assertSame('', $next['next_cursor']);

		try {
			$this->reader->conversations($this->bob, 'x', 1);
			$this->fail('a cursor of another listing');
		} catch (ImportException $e) {
			$this->assertSame(ImportException::CODE_INVALID, $e->getImportCode());
		}
	}

	public function testALaterParticipantSeesNothingBeforeTheirHistoryStart(): void {
		$messages = $this->reader->messages($this->bob, $this->room->getToken(), 0, 100);
		foreach ($messages as $message) {
			$this->assertGreaterThanOrEqual(strtotime('2024-01-04T00:00:00Z'), $message->time->getTimestamp());
			$this->assertStringNotContainsString('secret', $message->text);
		}
		$ids = array_map(static fn (ReadMessage $m): int => $m->id, $messages);
		$this->assertNotContains($this->ids['old-post'], $ids);
		$this->assertContains($this->ids['bob-added'], $ids);

		$reply = $this->byId($messages)[$this->ids['new-reply']];
		$this->assertSame('Answer to the old plan', $reply->text);
		$this->assertSame(0, $reply->replyTo, 'the parent is from before the history start');

		$alices = $this->byId($this->reader->messages($this->alice, $this->room->getToken(), 0, 100));
		$this->assertSame($this->ids['old-post'], $alices[$this->ids['new-reply']]->replyTo);
		$this->assertSame(['👍' => 1], $alices[$this->ids['old-post']]->reactions);
	}

	public function testTheWholeHistoryForAnOrganisationExport(): void {
		$messages = $this->byId($this->reader->messages($this->bob, $this->room->getToken(), 0, 100, true));
		$this->assertSame('Old secret plan', $messages[$this->ids['old-post']]->text);
		$this->assertSame($this->ids['old-post'], $messages[$this->ids['new-reply']]->replyTo);
	}

	public function testMessagesAsTheySawThem(): void {
		$messages = $this->byId($this->reader->messages($this->bob, $this->room->getToken(), 0, 100));

		$deleted = $messages[$this->ids['new-deleted']];
		$this->assertTrue($deleted->deleted);
		$this->assertSame('', $deleted->text);
		$this->assertSame([], $deleted->parameters);
		$this->assertSame('', $deleted->systemKind);

		$edited = $messages[$this->ids['new-edited']];
		$this->assertSame('Edited text', $edited->text);
		$this->assertSame('2024-01-05T10:00:00+00:00', $edited->edited?->format(\DATE_ATOM));
		$this->assertSame(['🎉' => 2], $edited->reactions);
		$this->assertSame('users', $edited->actorType);
		$this->assertSame($this->carol, $edited->actorId);

		$added = $messages[$this->ids['bob-added']];
		$this->assertSame('user_added', $added->systemKind);
		$this->assertSame('{actor} added you', $added->text, 'in the words Talk shows bob');
		$this->assertSame($this->alice, $added->parameters['actor']['id'] ?? null);

		$file = $messages[$this->ids['new-file']];
		$fileParameter = array_values(array_filter($file->parameters, static fn (array $p): bool => ($p['type'] ?? '') === 'file'));
		$this->assertCount(1, $fileParameter);
		$this->assertSame((string)$this->fileId, $fileParameter[0]['id']);
		$this->assertSame('plan.txt', $fileParameter[0]['name']);
		$this->assertSame('4', $fileParameter[0]['size']);
	}

	public function testMessagesArePagedByIdOldestFirst(): void {
		$all = $this->reader->messages($this->alice, $this->room->getToken(), 0, 100);
		$ids = array_map(static fn (ReadMessage $m): int => $m->id, $all);
		$sorted = $ids;
		sort($sorted);
		$this->assertSame($sorted, $ids);
		$this->assertCount(7, $all);

		$paged = [];
		$after = 0;
		do {
			$page = $this->reader->messages($this->alice, $this->room->getToken(), $after, 3);
			foreach ($page as $message) {
				$paged[] = $message->id;
				$after = $message->id;
			}
		} while (count($page) === 3);
		$this->assertSame($ids, $paged);
	}

	public function testOnlyParticipantsAreRead(): void {
		$outsider = $this->createUser('outsider');
		foreach ([[$outsider, $this->room->getToken()], [$this->alice, 'nosuchroom1']] as [$userId, $token]) {
			try {
				$this->reader->messages($userId, $token, 0, 10);
				$this->fail('read by ' . $userId);
			} catch (ImportException $e) {
				$this->assertSame(ImportException::CODE_NOT_FOUND, $e->getImportCode());
			}
		}
		$this->assertSame([], $this->reader->conversations($outsider, '', 10)['conversations']);
	}

	public function testAPersonAddedInTalkSeesTheWholeHistory(): void {
		$dave = $this->createUser('dave');
		$this->importer->setParticipants(self::APP, $this->room, [
			new ImportParticipant($this->alice),
			new ImportParticipant($this->bob, historySince: new \DateTimeImmutable('2024-01-04T00:00:00Z')),
			new ImportParticipant($this->carol),
			new ImportParticipant($dave),
		]);
		$conversation = $this->reader->conversations($dave, '', 10)['conversations'][0];
		$this->assertNull($conversation->historySince);
		$this->assertCount(7, $this->reader->messages($dave, $this->room->getToken(), 0, 100));
	}

	public function testReadingChangesNothing(): void {
		// A live message, so that read markers and unread counters have something to count.
		$chatManager = Server::get(ChatManager::class);
		$chatManager->sendMessage($this->room, $this->participant($this->room, $this->alice), 'users', $this->alice,
			'Live hello @"' . $this->bob . '"', new \DateTime());

		$before = $this->snapshot();
		$this->startRecordingEvents();
		foreach ([$this->alice, $this->bob, $this->carol] as $userId) {
			$this->reader->conversations($userId, '', 10);
			$this->reader->messages($userId, $this->room->getToken(), 0, 100);
			$this->reader->messages($userId, $this->room->getToken(), 0, 100, true);
		}
		$this->stopRecordingEvents();

		// Parsing a message for display is the only event, as in Talk's own chat.
		$this->assertSame([], array_values(array_diff($this->recordedEvents, [MessageParseEvent::class])));
		$this->assertSame($before, $this->snapshot());
	}

	/**
	 * Read markers, unread counters and last activity of every participant
	 * and the room, and the rows of the tables live chat writes to
	 *
	 * @return array<string, mixed>
	 */
	private function snapshot(): array {
		$query = $this->db->getQueryBuilder();
		$query->select('actor_id', 'last_read_message', 'last_mention_message', 'last_mention_direct', 'last_attendee_activity', 'unread_messages', 'notification_level', 'has_unread_threads', 'has_unread_thread_mentions', 'has_unread_thread_directs')
			->from('talk_attendees')
			->where($query->expr()->eq('room_id', $query->createNamedParameter($this->room->getId())))
			->orderBy('id');
		$result = $query->executeQuery();
		$attendees = $result->fetchAllAssociative();
		$result->closeCursor();

		$room = Server::get(Manager::class)->getRoomById($this->room->getId());
		return [
			'attendees' => $attendees,
			'last_activity' => $room->getLastActivity()?->getTimestamp(),
			'last_message' => $room->getLastMessageId(),
			'comments' => $this->countComments($room),
			'read_marks' => $this->countRows('comments_read_markers'),
			'side_effects' => $this->countSideEffects(),
		];
	}
}
