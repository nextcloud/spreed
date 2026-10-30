<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Import;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Import\CanonicalForm;
use OCA\Talk\Import\ImportException;
use OCA\Talk\Import\ImportMessage;
use OCA\Talk\Import\ImportParticipant;
use OCA\Talk\Import\ImportReaction;
use OCA\Talk\Import\ImportResult;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attachment;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ThreadService;
use OCP\Files\IRootFolder;
use OCP\Server;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

#[Group('DB')]
class HistoryImporterTest extends ImportTestCase {
	private CommentsManager $comments;

	protected function setUp(): void {
		parent::setUp();
		$this->comments = Server::get(CommentsManager::class);
	}

	public function testEnsureConversationIsFoundAgainAndQuiet(): void {
		$service = $this->createUser('service');
		$this->startRecordingEvents();
		$sideEffects = $this->countSideEffects();

		$room = $this->createGroupRoom($service);
		$again = $this->importer->ensureConversation(self::APP, $this->prefix . '/room', Room::TYPE_GROUP, 'Other name', '',
			new \DateTimeImmutable(), false, '', '', $service);

		$this->assertSame($room->getToken(), $again->getToken());
		$this->assertSame('Imported channel', $again->getName());
		$this->assertSame('2024-01-01T08:00:00+00:00', $room->getLastActivity()?->format(\DATE_ATOM));
		$this->assertSame($room->getToken(), $this->importer->findConversation(self::APP, $this->prefix . '/room')?->getToken());
		$this->assertNull($this->importer->findConversation(self::APP, $this->prefix . '/other'));
		$this->assertSame($room->getId(), $this->importer->getConversation(self::APP, $room->getToken())->getId());

		$owner = $this->participant($room, $service);
		$this->assertSame(Participant::OWNER, $owner->getAttendee()->getParticipantType());
		$this->assertSame(0, $this->countComments($room), 'no "conversation created" message');
		$this->assertSame([], $this->recordedEvents);
		$this->assertSame($sideEffects, $this->countSideEffects());
	}

	public function testReadOnlyAndObjectLink(): void {
		$service = $this->createUser('service');
		$room = $this->importer->ensureConversation(self::APP, $this->prefix . '/meeting', Room::TYPE_GROUP, 'Weekly', 'Notes',
			new \DateTimeImmutable('2024-01-01T08:00:00Z'), true, Room::OBJECT_TYPE_EVENT, 'uid-1234@example.org', $service);
		$this->rooms[] = $room;

		$this->assertSame(Room::READ_ONLY, $room->getReadOnly());
		$this->assertSame(Room::OBJECT_TYPE_EVENT, $room->getObjectType());
		$this->assertSame('uid-1234@example.org', $room->getObjectId());
		$this->assertSame('Notes', $room->getDescription());
	}

	public static function dataInvalidConversation(): array {
		return [
			'public' => [Room::TYPE_PUBLIC, 'Name', '', '', ImportException::CODE_INVALID],
			'empty name' => [Room::TYPE_GROUP, ' ', '', '', ImportException::CODE_INVALID],
			'event with retention id' => [Room::TYPE_GROUP, 'Name', Room::OBJECT_TYPE_EVENT, '1700000000#1700003600', ImportException::CODE_INVALID],
			'file without id' => [Room::TYPE_GROUP, 'Name', Room::OBJECT_TYPE_FILE, 'abc', ImportException::CODE_INVALID],
			'phone' => [Room::TYPE_GROUP, 'Name', Room::OBJECT_TYPE_PHONE_PERSIST, '123', ImportException::CODE_INVALID],
			'one-to-one name' => [Room::TYPE_ONE_TO_ONE, 'Name', '', '', ImportException::CODE_INVALID],
		];
	}

	#[DataProvider('dataInvalidConversation')]
	public function testInvalidConversation(int $type, string $name, string $objectType, string $objectId, string $code): void {
		$service = $this->createUser('service');
		try {
			$this->importer->ensureConversation(self::APP, $this->prefix . '/bad', $type, $name, '', new \DateTimeImmutable(), false, $objectType, $objectId, $service);
			$this->fail('Expected an ImportException');
		} catch (ImportException $e) {
			$this->assertSame($code, $e->getImportCode());
		}
		$this->assertNull($this->importer->findConversation(self::APP, $this->prefix . '/bad'));
	}

	public function testRefusesConversationsOfOthers(): void {
		$service = $this->createUser('service');
		$room = Server::get(Manager::class)->createRoom(Room::TYPE_GROUP, 'Live');
		$this->rooms[] = $room;

		$this->expectException(ImportException::class);
		$this->importer->importMessages(self::APP, $room, [$this->text('m1', $this->user($service), '2024-01-01T09:00:00Z', 'Hi')]);
	}

	public function testRefusesConversationsOfAnotherApp(): void {
		$service = $this->createUser('service');
		$room = $this->createGroupRoom($service);

		try {
			$this->importer->getConversation('otherapp', $room->getToken());
			$this->fail('Expected an ImportException');
		} catch (ImportException $e) {
			$this->assertSame(ImportException::CODE_NOT_IMPORTED, $e->getImportCode());
		}
	}

	public function testOneToOneConversation(): void {
		$alice = $this->createUser('alice');
		$bob = $this->createUser('bob');
		$service = $this->createUser('service');
		$name = json_encode([$bob, $alice]);

		$room = $this->importer->ensureConversation(self::APP, $this->prefix . '/dm', Room::TYPE_ONE_TO_ONE, $name, '',
			new \DateTimeImmutable('2024-01-01T08:00:00Z'), false, '', '', $service);
		$this->rooms[] = $room;
		$this->assertSame(Room::TYPE_ONE_TO_ONE, $room->getType());
		$this->assertSame(json_encode([$alice, $bob]), $room->getName());
		$this->assertSame([], Server::get(\OCA\Talk\Service\ParticipantService::class)->getParticipantUserIds($room), 'nobody until the participants are set');

		$results = $this->importer->setParticipants(self::APP, $room, [new ImportParticipant($alice), new ImportParticipant($bob)]);
		$this->assertSame([ImportResult::STATUS_OK, ImportResult::STATUS_OK], array_map(static fn (ImportResult $r): string => $r->status, $results));
		$this->assertSame(Participant::OWNER, $this->participant($room, $bob)->getAttendee()->getParticipantType());

		try {
			$this->importer->setParticipants(self::APP, $room, [new ImportParticipant($alice)]);
			$this->fail('Expected an ImportException');
		} catch (ImportException $e) {
			$this->assertSame(ImportException::CODE_INVALID, $e->getImportCode());
		}

		// A live one-to-one conversation of two other users blocks an import of theirs
		$carol = $this->createUser('carol');
		$live = Server::get(Manager::class)->createRoom(Room::TYPE_ONE_TO_ONE, json_encode([$alice, $carol]));
		$this->rooms[] = $live;
		try {
			$this->importer->ensureConversation(self::APP, $this->prefix . '/dm2', Room::TYPE_ONE_TO_ONE, json_encode([$alice, $carol]), '',
				new \DateTimeImmutable(), false, '', '', $service);
			$this->fail('Expected an ImportException');
		} catch (ImportException $e) {
			$this->assertSame(ImportException::CODE_EXISTS, $e->getImportCode());
		}
	}

	public function testImportWritesHistoryQuietly(): void {
		$service = $this->createUser('service');
		$alice = $this->createUser('alice');
		$bob = $this->createUser('bob');
		$room = $this->createGroupRoom($service);
		$this->importer->setParticipants(self::APP, $room, [new ImportParticipant($alice), new ImportParticipant($bob)]);
		$alicesReadMarker = $this->participant($room, $alice)->getAttendee()->getLastReadMessage();

		$former = $this->placeholder('ph-' . $this->prefix, 'Dana Departed');
		$messages = [
			new ImportMessage('sys', $former, new \DateTimeImmutable('2024-01-02T09:00:00Z'),
				systemKind: ImportMessage::SYSTEM_USER_ADDED, members: [$this->user($alice), $this->placeholder('ph2-' . $this->prefix, 'Guest Person')]),
			$this->text('post', $former, '2024-01-02T09:05:00Z', 'Kick-off @"' . $bob . '"', threadTitle: 'Kick-off', reactions: [
				new ImportReaction($this->user($alice), '👍', new \DateTimeImmutable('2024-01-02T09:06:00Z')),
				new ImportReaction($this->user($bob), '👍', new \DateTimeImmutable('2024-01-02T09:07:00Z')),
				new ImportReaction($former, '🎉', new \DateTimeImmutable('2024-01-02T09:08:00Z')),
			]),
			$this->text('reply1', $this->user($alice), '2024-01-02T09:10:00Z', 'Sounds good', 'post'),
			new ImportMessage('reply2', $this->user($bob), new \DateTimeImmutable('2024-01-02T09:11:00Z'), 'Edited text', 'post',
				edited: new \DateTimeImmutable('2024-01-02T09:12:00Z')),
		];

		$this->startRecordingEvents();
		$sideEffects = $this->countSideEffects();
		$before = $this->countComments($room);
		$results = $this->importer->importMessages(self::APP, $room, $messages);

		$this->assertSame(['ok', 'ok', 'ok', 'ok'], array_map(static fn (ImportResult $r): string => $r->status, $results));
		foreach ($messages as $i => $message) {
			$this->assertSame(CanonicalForm::hashMessage($message), $results[$i]->sha256);
			$this->assertSame($message->ref, $results[$i]->ref);
		}
		// 2 system messages (one per member), 3 messages, 3 reactions
		$this->assertSame($before + 8, $this->countComments($room));
		$this->assertSame([], $this->recordedEvents, 'no chat, participant, comment or share event');
		$this->assertSame($sideEffects, $this->countSideEffects(), 'no notification, activity, signaling or federation row');

		$post = $this->comments->get((string)$results[1]->id);
		$this->assertSame(Attendee::ACTOR_IMPORTED, $post->getActorType());
		$this->assertSame('ph-' . $this->prefix, $post->getActorId());
		$this->assertSame('2024-01-02T09:05:00+00:00', $post->getCreationDateTime()->format(\DATE_ATOM));
		$this->assertSame(['👍' => 2, '🎉' => 1], $post->getReactions());
		$this->assertSame($results[1]->id, $post->getMetaData()[Message::METADATA_THREAD_ID]);
		$this->assertSame('Kick-off', $post->getMetaData()[Message::METADATA_THREAD_TITLE]);
		$this->assertSame(5, $post->getChildrenCount(), 'two replies and three reactions');

		$reply = $this->comments->get((string)$results[3]->id);
		$this->assertSame((string)$results[1]->id, $reply->getParentId());
		$this->assertSame((string)$results[1]->id, $reply->getTopmostParentId());
		$this->assertSame($results[1]->id, $reply->getMetaData()[Message::METADATA_THREAD_ID]);
		$this->assertSame(Attendee::ACTOR_USERS, $reply->getMetaData()[Message::METADATA_LAST_EDITED_BY_TYPE]);
		$this->assertSame(strtotime('2024-01-02T09:12:00Z'), $reply->getMetaData()[Message::METADATA_LAST_EDITED_TIME]);

		$thread = Server::get(ThreadService::class)->findByThreadId($room->getId(), $results[1]->id);
		$this->assertSame(2, $thread->getNumReplies());
		$this->assertSame($results[3]->id, $thread->getLastMessageId());
		$this->assertSame('Kick-off', $thread->getName());

		$system = $this->comments->get((string)$results[0]->id);
		$this->assertSame(ChatManager::VERB_SYSTEM, $system->getVerb());
		$this->assertSame(['message' => 'user_added', 'parameters' => ['user' => 'ph2-' . $this->prefix, 'actorType' => 'imported']], json_decode($system->getMessage(), true));

		// Nobody is mentioned, nothing is unread, and the room's last message waits for finish()
		$bobParticipant = $this->participant($room, $bob);
		$this->assertSame(0, $bobParticipant->getAttendee()->getLastMentionMessage());
		$this->assertSame(0, $bobParticipant->getAttendee()->getLastMentionDirect());
		$this->assertSame($alicesReadMarker, $this->participant($room, $alice)->getAttendee()->getLastReadMessage());
		$this->assertSame(0, $this->freshRoom($room)->getLastMessageId());

		$this->importer->finish(self::APP, $room);
		$room = $this->freshRoom($room);
		$this->assertSame($results[3]->id, $room->getLastMessageId());
		$this->assertSame('2024-01-02T09:11:00+00:00', $room->getLastActivity()?->format(\DATE_ATOM));
		foreach ([$alice, $bob] as $userId) {
			$attendee = $this->participant($room, $userId)->getAttendee();
			$this->assertGreaterThanOrEqual($results[3]->id, $attendee->getLastReadMessage());
			$this->assertSame(0, Server::get(ChatManager::class)->getUnreadCount($room, $attendee->getLastReadMessage()), 'no unread messages for ' . $userId);
		}
		$this->assertSame([], $this->recordedEvents);
		$this->assertSame($sideEffects, $this->countSideEffects());
	}

	public function testRepeatIsANoOp(): void {
		$service = $this->createUser('service');
		$room = $this->createGroupRoom($service);
		$messages = [
			$this->text('a', $this->user($service), '2024-01-02T09:00:00Z', 'One', threadTitle: 'One'),
			$this->text('b', $this->user($service), '2024-01-02T09:01:00Z', 'Two', 'a', reactions: [
				new ImportReaction($this->user($service), '👍', new \DateTimeImmutable('2024-01-02T09:02:00Z')),
			]),
		];
		$first = $this->importer->importMessages(self::APP, $room, $messages);
		$comments = $this->countComments($room);
		$refs = $this->countRows('talk_import_refs');

		$second = $this->importer->importMessages(self::APP, $room, $messages);

		$this->assertEquals($first, $second);
		$this->assertSame($comments, $this->countComments($room));
		$this->assertSame($refs, $this->countRows('talk_import_refs'));
	}

	public function testChangedContentIsReportedNotOverwritten(): void {
		$service = $this->createUser('service');
		$room = $this->createGroupRoom($service);
		$original = $this->text('m', $this->user($service), '2024-01-02T09:00:00Z', 'Original');
		$written = $this->importer->importMessages(self::APP, $room, [$original])[0];

		$result = $this->importer->importMessages(self::APP, $room, [$this->text('m', $this->user($service), '2024-01-02T09:00:00Z', 'Changed in the source')])[0];

		$this->assertSame(ImportResult::STATUS_CONFLICT, $result->status);
		$this->assertSame(ImportException::CODE_EXISTS, $result->errorCode);
		$this->assertSame($written->id, $result->id);
		$this->assertSame($written->sha256, $result->sha256);
		$this->assertSame('Original', $this->comments->get((string)$written->id)->getMessage());
	}

	public function testNewVersionReplacesUnchangedMessage(): void {
		$service = $this->createUser('service');
		$alice = $this->createUser('alice');
		$room = $this->createGroupRoom($service);
		$written = $this->importer->importMessages(self::APP, $room, [
			$this->text('m', $this->user($service), '2024-01-02T09:00:00Z', 'Original', reactions: [
				new ImportReaction($this->user($alice), '👍', new \DateTimeImmutable('2024-01-02T09:01:00Z')),
			]),
		])[0];

		$newVersion = new ImportMessage('m', $this->user($service), new \DateTimeImmutable('2024-01-02T09:00:00Z'), 'Edited in the source',
			edited: new \DateTimeImmutable('2024-01-02T10:00:00Z'),
			reactions: [new ImportReaction($this->user($alice), '❤️', new \DateTimeImmutable('2024-01-02T09:05:00Z'))],
			newVersion: true);
		$result = $this->importer->importMessages(self::APP, $room, [$newVersion])[0];

		$this->assertSame(ImportResult::STATUS_OK, $result->status);
		$this->assertSame($written->id, $result->id);
		$this->assertSame(CanonicalForm::hashMessage($newVersion), $result->sha256);
		$comment = $this->comments->get((string)$written->id);
		$this->assertSame('Edited in the source', $comment->getMessage());
		$this->assertSame(['❤️' => 1], $comment->getReactions());

		$stat = $this->importer->statMessages(self::APP, $room, ['m'], true)[0];
		$this->assertSame($result->sha256, $stat->sha256);
		$this->assertFalse($stat->changed);

		$moved = new ImportMessage('m', $this->user($service), new \DateTimeImmutable('2024-01-02T09:00:00Z'), 'Edited in the source',
			parentRef: 'other', newVersion: true);
		$this->importer->importMessages(self::APP, $room, [$this->text('other', $this->user($service), '2024-01-01T09:00:00Z', 'Other')]);
		$refused = $this->importer->importMessages(self::APP, $room, [$moved])[0];
		$this->assertSame(ImportResult::STATUS_CONFLICT, $refused->status);
		$this->assertSame(ImportException::CODE_EXISTS, $refused->errorCode);
	}

	public function testMessageChangedInTalkIsNeverOverwrittenOrRemoved(): void {
		$service = $this->createUser('service');
		$room = $this->createGroupRoom($service);
		$written = $this->importer->importMessages(self::APP, $room, [$this->text('m', $this->user($service), '2024-01-02T09:00:00Z', 'Original')])[0];

		// The author edits the message in Talk
		$comment = $this->comments->get((string)$written->id);
		$comment->setMessage('Edited in Talk');
		$this->comments->save($comment);

		$stat = $this->importer->statMessages(self::APP, $room, ['m'], true)[0];
		$this->assertTrue($stat->exists);
		$this->assertTrue($stat->changed);
		$this->assertNotSame($written->sha256, $stat->sha256);

		$update = $this->importer->importMessages(self::APP, $room, [
			new ImportMessage('m', $this->user($service), new \DateTimeImmutable('2024-01-02T09:00:00Z'), 'Newer source', newVersion: true),
		])[0];
		$this->assertSame(ImportResult::STATUS_CONFLICT, $update->status);
		$this->assertSame(ImportException::CODE_CHANGED, $update->errorCode);

		$removal = $this->importer->removeMessages(self::APP, $room, ['m'])[0];
		$this->assertSame(ImportResult::STATUS_CONFLICT, $removal->status);
		$this->assertSame(ImportException::CODE_CHANGED, $removal->errorCode);
		$this->assertSame('Edited in Talk', $this->comments->get((string)$written->id)->getMessage());
	}

	public function testRemoveMessages(): void {
		$service = $this->createUser('service');
		$room = $this->createGroupRoom($service);
		[$keep, $remove] = $this->importer->importMessages(self::APP, $room, [
			$this->text('keep', $this->user($service), '2024-01-02T09:00:00Z', 'Stays'),
			$this->text('remove', $this->user($service), '2024-01-02T09:01:00Z', 'Deleted in the source'),
		]);

		$this->startRecordingEvents();
		$results = $this->importer->removeMessages(self::APP, $room, ['remove', 'unknown']);
		$this->assertSame([ImportResult::STATUS_OK, ImportResult::STATUS_OK], array_map(static fn (ImportResult $r): string => $r->status, $results));
		$this->assertSame($remove->id, $results[0]->id);
		$this->assertSame([], $this->recordedEvents);

		$comment = $this->comments->get((string)$remove->id);
		$this->assertSame(ChatManager::VERB_MESSAGE_DELETED, $comment->getVerb());
		$this->assertStringNotContainsString('Deleted in the source', $comment->getMessage());

		$this->assertFalse($this->importer->statMessages(self::APP, $room, ['remove'], false)[0]->exists);
		$this->assertSame([['ref' => 'keep', 'message_id' => $keep->id]], $this->importer->listMessages(self::APP, $room, '', 10)['entries']);
		$this->assertSame(ImportResult::STATUS_OK, $this->importer->removeMessages(self::APP, $room, ['remove'])[0]->status, 'repeated removal');
	}

	public function testInvalidEntriesAreAnsweredOneByOne(): void {
		$service = $this->createUser('service');
		$room = $this->createGroupRoom($service);

		$results = $this->importer->importMessages(self::APP, $room, [
			$this->text('ok1', $this->user($service), '2024-01-02T09:00:00Z', 'Fine'),
			$this->text('orphan', $this->user($service), '2024-01-02T09:01:00Z', 'Reply', 'never-imported'),
			$this->text('reaction', $this->user($service), '2024-01-02T09:02:00Z', 'Text', reactions: [
				new ImportReaction($this->user($service), ':thumbsup:', new \DateTimeImmutable('2024-01-02T09:03:00Z')),
			]),
			$this->text('long', $this->user($service), '2024-01-02T09:04:00Z', str_repeat('x', ChatManager::MAX_CHAT_LENGTH + 1)),
			$this->text('nobody', $this->user($this->prefix . '-nobody'), '2024-01-02T09:05:00Z', 'Who?'),
			$this->text('noname', $this->placeholder('ph', ''), '2024-01-02T09:06:00Z', 'Who?'),
			$this->text('future', $this->user($service), '2099-01-01T00:00:00Z', 'Later'),
			new ImportMessage('sysText', $this->user($service), new \DateTimeImmutable('2024-01-02T09:07:00Z'), 'Text',
				systemKind: ImportMessage::SYSTEM_USER_ADDED, members: [$this->user($service)]),
			$this->text('threadReply', $this->user($service), '2024-01-02T09:08:00Z', 'Reply', 'ok1', threadTitle: 'Not allowed'),
			$this->text('ok2', $this->user($service), '2024-01-02T09:09:00Z', 'Also fine', 'ok1'),
		]);

		$this->assertSame([
			['ok', ''],
			['error', ImportException::CODE_PARENT_MISSING],
			['error', ImportException::CODE_INVALID_REACTION],
			['error', ImportException::CODE_TOO_LONG],
			['error', ImportException::CODE_UNKNOWN_USER],
			['error', ImportException::CODE_INVALID],
			['error', ImportException::CODE_INVALID],
			['error', ImportException::CODE_INVALID],
			['error', ImportException::CODE_INVALID],
			['ok', ''],
		], array_map(static fn (ImportResult $r): array => [$r->status, $r->errorCode], $results));
		$this->assertSame(2, $this->countComments($room));
	}

	public function testFilesAreSharedIntoTheConversation(): void {
		$service = $this->createUser('service');
		$alice = $this->createUser('alice');
		$room = $this->createGroupRoom($service);
		$folder = Server::get(IRootFolder::class)->getUserFolder($alice);
		$report = $folder->newFile('report.pdf', '%PDF-1.4 test');
		$photo = $folder->newFile('photo.jpg', 'not really a photo');

		$this->startRecordingEvents();
		$message = $this->text('files', $this->user($alice), '2024-01-02T09:00:00Z', 'Have a look', fileIds: [$report->getId(), $photo->getId()]);
		$result = $this->importer->importMessages(self::APP, $room, [$message])[0];
		$this->assertSame(ImportResult::STATUS_OK, $result->status);
		$this->assertSame([], $this->recordedEvents, 'no share events, so no "shared a file" message of its own');

		// One file share message per file, the text as caption of the last
		$this->assertSame(2, $this->countComments($room));
		$main = $this->comments->get((string)$result->id);
		$this->assertSame(ChatManager::VERB_OBJECT_SHARED, $main->getVerb());
		$data = json_decode($main->getMessage(), true);
		$this->assertSame('file_shared', $data['message']);
		$this->assertSame('Have a look', $data['parameters']['metaData']['caption']);

		$shares = Server::get(\OCA\Talk\Share\RoomShareProvider::class)->getSharesByPath($photo);
		$this->assertCount(1, $shares);
		$this->assertSame($room->getToken(), $shares[0]->getSharedWith());
		$this->assertSame($alice, $shares[0]->getShareOwner());
		$this->assertSame(IShare::TYPE_ROOM, $shares[0]->getShareType());
		$this->assertSame(strtotime('2024-01-02T09:00:00Z'), $shares[0]->getShareTime()->getTimestamp());

		$attachments = Server::get(\OCA\Talk\Service\AttachmentService::class);
		$this->assertCount(1, $attachments->getAttachmentsByType($room, Attachment::TYPE_FILE, 0, 10));
		$this->assertCount(1, $attachments->getAttachmentsByType($room, Attachment::TYPE_MEDIA, 0, 10));

		$stat = $this->importer->statMessages(self::APP, $room, ['files'], true)[0];
		$this->assertSame(CanonicalForm::hashMessage($message), $stat->sha256);
		$this->assertFalse($stat->changed);

		// The same file again reuses the share; removing one message keeps it for the other
		$second = $this->importer->importMessages(self::APP, $room, [
			$this->text('again', $this->user($alice), '2024-01-03T09:00:00Z', '', fileIds: [$photo->getId()]),
		])[0];
		$this->assertSame(ImportResult::STATUS_OK, $second->status);
		$shares = Server::get(\OCA\Talk\Share\RoomShareProvider::class)->getSharesByPath($photo);
		$this->assertCount(1, $shares);
		$this->assertSame(strtotime('2024-01-03T09:00:00Z'), $shares[0]->getShareTime()->getTimestamp(), 'the share follows the newest message');

		$this->importer->removeMessages(self::APP, $room, ['again']);
		$this->assertCount(1, Server::get(\OCA\Talk\Share\RoomShareProvider::class)->getSharesByPath($photo));
		$this->importer->removeMessages(self::APP, $room, ['files']);
		$this->assertCount(0, Server::get(\OCA\Talk\Share\RoomShareProvider::class)->getSharesByPath($photo));
		$this->assertCount(0, $attachments->getAttachmentsByType($room, Attachment::TYPE_FILE, 0, 10));
		$this->assertCount(0, $attachments->getAttachmentsByType($room, Attachment::TYPE_MEDIA, 0, 10));

		$missing = $this->importer->importMessages(self::APP, $room, [
			$this->text('missing', $this->user($alice), '2024-01-02T09:00:00Z', 'Gone', fileIds: [PHP_INT_MAX]),
		])[0];
		$this->assertSame(ImportException::CODE_FILE_NOT_FOUND, $missing->errorCode);
	}

	public function testListMessagesPages(): void {
		$service = $this->createUser('service');
		$room = $this->createGroupRoom($service);
		$messages = [];
		for ($i = 0; $i < 5; $i++) {
			$messages[] = $this->text('m' . $i, $this->user($service), '2024-01-02T09:0' . $i . ':00Z', 'Message ' . $i);
		}
		$results = $this->importer->importMessages(self::APP, $room, $messages);

		$page1 = $this->importer->listMessages(self::APP, $room, '', 3);
		$this->assertSame(['m0', 'm1', 'm2'], array_column($page1['entries'], 'ref'));
		$this->assertSame((string)$results[2]->id, $page1['next_cursor']);
		$page2 = $this->importer->listMessages(self::APP, $room, $page1['next_cursor'], 3);
		$this->assertSame(['m3', 'm4'], array_column($page2['entries'], 'ref'));
		$this->assertSame('', $page2['next_cursor']);
	}

	public function testSetParticipants(): void {
		$service = $this->createUser('service');
		$alice = $this->createUser('alice');
		$bob = $this->createUser('bob');
		$carol = $this->createUser('carol');
		$room = $this->createGroupRoom($service);
		$written = $this->importer->importMessages(self::APP, $room, [
			$this->text('m1', $this->user($service), '2024-01-02T09:00:00Z', 'Before'),
			$this->text('m2', $this->user($service), '2024-01-03T09:00:00Z', 'After'),
		]);

		// Carol was added by a moderator in Talk
		$liveRoom = $this->freshRoom($room);
		Server::get(\OCA\Talk\Service\ParticipantService::class)->addUsers($liveRoom, [[
			'actorType' => Attendee::ACTOR_USERS,
			'actorId' => $carol,
			'displayName' => $carol,
		]]);

		$this->startRecordingEvents();
		$sideEffects = $this->countSideEffects();
		$results = $this->importer->setParticipants(self::APP, $room, [
			new ImportParticipant($alice, Participant::MODERATOR),
			new ImportParticipant($bob, Participant::USER, new \DateTimeImmutable('2024-01-03T00:00:00Z'), archived: true, muted: true, favorite: true,
				lastRead: new \DateTimeImmutable('2024-01-02T12:00:00Z')),
			new ImportParticipant($carol),
			new ImportParticipant($this->prefix . '-nobody'),
		]);
		$this->assertSame([
			['ok', ''], ['ok', ''], ['conflict', ImportException::CODE_EXISTS], ['error', ImportException::CODE_UNKNOWN_USER],
		], array_map(static fn (ImportResult $r): array => [$r->status, $r->errorCode], $results));
		$this->assertSame([], $this->recordedEvents);
		$this->assertSame($sideEffects, $this->countSideEffects());

		$aliceAttendee = $this->participant($room, $alice)->getAttendee();
		$this->assertSame(Participant::MODERATOR, $aliceAttendee->getParticipantType());
		$this->assertGreaterThanOrEqual($written[1]->id, $aliceAttendee->getLastReadMessage());
		$this->assertSame(0, Server::get(ChatManager::class)->getUnreadCount($this->freshRoom($room), $aliceAttendee->getLastReadMessage()));
		$this->assertSame(0, $aliceAttendee->getHistorySince());

		$bobParticipant = $this->participant($room, $bob);
		$bobAttendee = $bobParticipant->getAttendee();
		$this->assertSame(strtotime('2024-01-03T00:00:00Z'), $bobAttendee->getHistorySince());
		$this->assertSame('2024-01-03T00:00:00+00:00', $bobParticipant->getHistorySince()?->format(\DATE_ATOM));
		$this->assertTrue($bobAttendee->isArchived());
		$this->assertTrue($bobAttendee->isFavorite());
		$this->assertSame(Participant::NOTIFY_NEVER, $bobAttendee->getNotificationLevel());
		$this->assertSame($written[0]->id, $bobAttendee->getLastReadMessage());

		// The service account and alice are not in the list anymore; carol stays
		$this->importer->setParticipants(self::APP, $room, [
			new ImportParticipant($bob, Participant::USER, null, muted: false),
		]);
		$userIds = Server::get(\OCA\Talk\Service\ParticipantService::class)->getParticipantUserIds($this->freshRoom($room));
		sort($userIds);
		$expected = [$bob, $carol];
		sort($expected);
		$this->assertSame($expected, $userIds);
		$bobAttendee = $this->participant($room, $bob)->getAttendee();
		$this->assertSame(0, $bobAttendee->getHistorySince());
		$this->assertSame(Participant::NOTIFY_DEFAULT, $bobAttendee->getNotificationLevel());
		$this->assertTrue($bobAttendee->isArchived(), 'unset state keeps the current value');
	}

	public function testHiddenHistoryIsNeverUnread(): void {
		$service = $this->createUser('service');
		$bob = $this->createUser('bob');
		$room = $this->createGroupRoom($service);
		$written = $this->importer->importMessages(self::APP, $room, [
			$this->text('m1', $this->user($service), '2024-01-01T09:00:00Z', 'One'),
			$this->text('m2', $this->user($service), '2024-01-02T09:00:00Z', 'Two'),
			$this->text('m3', $this->user($service), '2024-01-05T09:00:00Z', 'Three'),
		]);

		$this->importer->setParticipants(self::APP, $room, [
			new ImportParticipant($bob, historySince: new \DateTimeImmutable('2024-01-03T00:00:00Z'), lastRead: new \DateTimeImmutable('2024-01-01T12:00:00Z')),
		]);

		$attendee = $this->participant($room, $bob)->getAttendee();
		$this->assertSame($written[1]->id, $attendee->getLastReadMessage());
		$this->assertSame(1, Server::get(ChatManager::class)->getUnreadCount($this->freshRoom($room), $attendee->getLastReadMessage()));
	}

	public function testFinishAdvancesReadMarkersOverLaterImports(): void {
		$service = $this->createUser('service');
		$alice = $this->createUser('alice');
		$bob = $this->createUser('bob');
		$room = $this->createGroupRoom($service);
		$first = $this->importer->importMessages(self::APP, $room, [
			$this->text('m1', $this->user($service), '2024-01-02T09:00:00Z', 'One'),
			$this->text('m2', $this->user($service), '2024-01-02T09:01:00Z', 'Two'),
		]);
		$this->importer->finish(self::APP, $room);
		$this->importer->setParticipants(self::APP, $room, [
			new ImportParticipant($alice),
			new ImportParticipant($bob, lastRead: new \DateTimeImmutable('2024-01-02T09:00:30Z')),
		]);
		$this->assertSame($first[0]->id, $this->participant($room, $bob)->getAttendee()->getLastReadMessage());

		$later = $this->importer->importMessages(self::APP, $room, [
			$this->text('m3', $this->user($service), '2024-01-03T09:00:00Z', 'Three'),
		]);
		$this->importer->finish(self::APP, $room);

		$this->assertSame($later[0]->id, $this->participant($room, $alice)->getAttendee()->getLastReadMessage(), 'had read everything imported');
		$this->assertSame($first[0]->id, $this->participant($room, $bob)->getAttendee()->getLastReadMessage(), 'keeps the unread messages of the source');
		$this->assertSame($later[0]->id, $this->freshRoom($room)->getLastMessageId());
	}
}
