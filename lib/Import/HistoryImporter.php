<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

use OC\User\LazyUser;
use OCA\Files_Sharing\Event\UserShareAccessUpdatedEvent;
use OCA\Talk\AuditLog\Operation;
use OCA\Talk\CachePrefix;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\AttendeeMapper;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Model\Thread;
use OCA\Talk\Model\ThreadMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AttachmentService;
use OCA\Talk\Service\RoomService;
use OCA\Talk\Service\ThreadService;
use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\NotFoundException;
use OCP\DB\Exception as DBException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\ICacheFactory;
use OCP\IDBConnection;
use OCP\IEmojiHelper;
use OCP\IUserManager;
use OCP\Log\Audit\CriticalActionPerformedEvent;
use OCP\Share\Exceptions\ShareNotFound;

/**
 * Writes imported history straight into Talk's tables through the comments
 * manager and the mappers, without the services of live chat, so none of
 * their events (and none of the notifications, pushes, bots, webhooks,
 * signaling or federation requests behind them) happens.
 */
class HistoryImporter implements IHistoryImporter {
	use TTransactional;

	/** Teams allow 25,000 members per team */
	public const MAX_PARTICIPANTS = 25000;
	public const MAX_FILES_PER_MESSAGE = 100;
	public const MAX_LIST_LIMIT = 1000;

	private const APP_PATTERN = '/^[a-z][a-z0-9_]{0,31}$/';
	private const MAX_REF_LENGTH = 255;
	private const MAX_ACTOR_ID_LENGTH = 64;
	private const MAX_DISPLAY_NAME_LENGTH = 255;
	private const MAX_THREAD_TITLE_LENGTH = 200;
	/** Messages may not be dated later than this many seconds from now */
	private const MAX_FUTURE_SECONDS = 86400;

	/** @var array<int, ImportRef> room ID → ref, per app */
	private array $roomRefs = [];
	/** @var array<string, bool> */
	private array $knownUsers = [];
	/** @var array<string, string> placeholder ID → display name written in this request */
	private array $writtenActors = [];

	public function __construct(
		private readonly IDBConnection $db,
		private readonly ImportRefMapper $refMapper,
		private readonly ImportedActorDirectory $actorDirectory,
		private readonly CommentsManager $commentsManager,
		private readonly Manager $manager,
		private readonly RoomService $roomService,
		private readonly AttendeeMapper $attendeeMapper,
		private readonly SessionMapper $sessionMapper,
		private readonly ThreadMapper $threadMapper,
		private readonly ThreadService $threadService,
		private readonly AttachmentService $attachmentService,
		private readonly RoomShareProvider $shareProvider,
		private readonly Config $talkConfig,
		private readonly IUserManager $userManager,
		private readonly IRootFolder $rootFolder,
		private readonly IEmojiHelper $emojiHelper,
		private readonly IEventDispatcher $dispatcher,
		private readonly ICacheFactory $cacheFactory,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	#[\Override]
	public function getFeatures(): array {
		return [
			self::FEATURE_HISTORY_FROM,
			self::FEATURE_PLACEHOLDERS,
			self::FEATURE_THREADS,
			self::FEATURE_IMPORT_STATE,
			self::FEATURE_OBJECT_LINK,
			self::FEATURE_READ_ONLY,
		];
	}

	#[\Override]
	public function ensureConversation(string $app, string $importRef, int $type, string $name, string $description,
		\DateTimeImmutable $created, bool $readOnly, string $objectType, string $objectId, string $ownerUid): Room {
		$room = $this->findConversation($app, $importRef);
		if ($room !== null) {
			return $room;
		}

		if ($type !== Room::TYPE_GROUP && $type !== Room::TYPE_ONE_TO_ONE) {
			throw new ImportException(ImportException::CODE_INVALID, 'type must be a group or one-to-one conversation');
		}
		if (!$this->userExists($ownerUid)) {
			throw new ImportException(ImportException::CODE_UNKNOWN_USER, 'owner is not an account');
		}

		if ($type === Room::TYPE_ONE_TO_ONE) {
			$name = $this->validateOneToOneName($name);
		} else {
			$name = trim($name);
			if ($name === '' || mb_strlen($name) > 255) {
				throw new ImportException(ImportException::CODE_INVALID, 'name must have 1 to 255 characters');
			}
		}
		if (mb_strlen($description) > Room::DESCRIPTION_MAXIMUM_LENGTH) {
			throw new ImportException(ImportException::CODE_INVALID, 'description is too long');
		}
		$this->validateObject($objectType, $objectId);

		try {
			$room = $this->atomic(function () use ($app, $importRef, $type, $name, $description, $created, $readOnly, $objectType, $objectId, $ownerUid): Room {
				$room = $this->manager->createRoom(
					$type,
					$name,
					$objectType,
					$objectId,
					'',
					$readOnly ? Room::READ_ONLY : Room::READ_WRITE,
					Room::LISTABLE_NONE,
					description: $description,
					dispatchEvents: false,
				);
				$this->roomService->setLastActivity($room, \DateTime::createFromImmutable($created));

				$ref = new ImportRef();
				$ref->setApp($app);
				$ref->setKind(ImportRef::KIND_ROOM);
				$ref->setRef($importRef);
				$ref->setRefHash(ImportRef::hashRef(ImportRef::KIND_ROOM, 0, $importRef));
				$ref->setRoomId($room->getId());
				$ref->setObjectId($room->getId());
				$ref->setOwnerId($ownerUid);
				$this->refMapper->insert($ref);
				$this->roomRefs[$room->getId()] = $ref;

				if ($type === Room::TYPE_GROUP) {
					$this->addAttendee($app, $room, new ImportParticipant($ownerUid, Participant::OWNER), 0);
				}
				return $room;
			}, $this->db);
		} catch (DBException $e) {
			if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			// Created by a parallel request with the same ref
			$room = $this->findConversation($app, $importRef);
			if ($room === null) {
				throw $e;
			}
			return $room;
		}

		$this->audit(Operation::HISTORY_IMPORT_CONVERSATION_CREATED, 'Conversation "{token}" was created by the history import of {app}', [
			'token' => $room->getToken(),
			'app' => $app,
		]);

		return $this->manager->getRoomById($room->getId());
	}

	#[\Override]
	public function findConversation(string $app, string $importRef): ?Room {
		$this->validateApp($app);
		$this->validateRef($importRef, 'conversation ref');

		try {
			$ref = $this->refMapper->findByRef($app, ImportRef::KIND_ROOM, 0, $importRef);
		} catch (DoesNotExistException) {
			return null;
		}

		try {
			$room = $this->manager->getRoomById($ref->getObjectId());
		} catch (RoomNotFoundException) {
			throw new ImportException(ImportException::CODE_CHANGED, 'the conversation was deleted in Talk');
		}
		$this->roomRefs[$room->getId()] = $ref;
		return $room;
	}

	#[\Override]
	public function getConversation(string $app, string $token): Room {
		$this->validateApp($app);
		try {
			$room = $this->manager->getRoomByToken($token);
		} catch (RoomNotFoundException) {
			throw new ImportException(ImportException::CODE_NOT_IMPORTED, 'no conversation with this token');
		}
		$this->getRoomRef($app, $room);
		return $room;
	}

	#[\Override]
	public function setParticipants(string $app, Room $room, array $participants): array {
		$this->validateApp($app);
		$this->getRoomRef($app, $room);
		if (count($participants) > self::MAX_PARTICIPANTS) {
			throw new ImportException(ImportException::CODE_INVALID, 'too many participants');
		}

		$validated = [];
		$seen = [];
		foreach ($participants as $i => $participant) {
			if (!$participant instanceof ImportParticipant) {
				throw new ImportException(ImportException::CODE_INVALID, 'participant ' . $i . ' is not an ImportParticipant');
			}
			if ($participant->userId === '' || in_array($participant->userId, $seen, true)) {
				throw new ImportException(ImportException::CODE_INVALID, 'participant ' . $i . ' is empty or listed twice');
			}
			$seen[] = $participant->userId;
			if (!in_array($participant->participantType, [Participant::OWNER, Participant::MODERATOR, Participant::USER], true)) {
				throw new ImportException(ImportException::CODE_INVALID, 'participant ' . $i . ' has an unknown participant type');
			}
			$validated[] = $participant;
		}

		if ($room->getType() === Room::TYPE_ONE_TO_ONE && $validated !== []) {
			$users = json_decode($room->getName(), true);
			$listed = $seen;
			sort($listed);
			if ($listed !== $users) {
				throw new ImportException(ImportException::CODE_INVALID, 'the participants of a one-to-one conversation are the two users of its name');
			}
		}

		$existing = [];
		foreach ($this->attendeeMapper->getActorsByType($room->getId(), Attendee::ACTOR_USERS) as $attendee) {
			$existing[$attendee->getActorId()] = $attendee;
		}
		$managed = [];
		foreach ($this->refMapper->findAllInRoom($app, ImportRef::KIND_PARTICIPANT, $room->getId()) as $ref) {
			$managed[(string)$ref->getRef()] = $ref;
		}

		$results = [];
		$changes = ['added' => 0, 'updated' => 0, 'removed' => 0];
		$accessChanged = [];
		$this->atomic(function () use ($app, $room, $validated, $existing, $managed, &$results, &$changes, &$accessChanged): void {
			$newestMessageId = null;
			foreach ($validated as $participant) {
				$userId = $participant->userId;
				if (!$this->userExists($userId)) {
					$results[] = ImportResult::fromException($userId, new ImportException(ImportException::CODE_UNKNOWN_USER, 'not an account'));
					continue;
				}

				$attendee = $existing[$userId] ?? null;
				if ($attendee !== null && !isset($managed[$userId])) {
					$results[] = ImportResult::conflict($userId, ImportException::CODE_EXISTS, 'the participant was added outside the import', $attendee->getId());
					continue;
				}

				$lastRead = null;
				if ($participant->lastRead !== null) {
					$lastRead = $this->getLastMessageIdUntil($room, $participant->lastRead);
				} elseif ($attendee === null) {
					$newestMessageId ??= $this->getNewestMessageId($room);
					$lastRead = $newestMessageId;
				}
				if ($participant->historySince !== null) {
					// Messages the person may not see never count as unread
					$beforeHistory = $this->getLastMessageIdUntil($room, $participant->historySince->modify('-1 second'));
					$lastRead = max($lastRead ?? $attendee?->getLastReadMessage() ?? 0, $beforeHistory);
				}

				if ($attendee === null) {
					$attendee = $this->addAttendee($app, $room, $participant, (int)$lastRead);
					$changes['added']++;
					$accessChanged[$userId] = true;
				} else {
					if ($this->updateAttendee($room, $attendee, $participant, $lastRead)) {
						$changes['updated']++;
						$accessChanged[$userId] = true;
					}
				}
				$results[] = ImportResult::ok($userId, $attendee->getId());
			}

			foreach ($managed as $userId => $ref) {
				$userId = (string)$userId;
				if (isset($existing[$userId]) && !$this->isListed($validated, $userId)) {
					$this->removeAttendee($existing[$userId]);
					$this->refMapper->delete($ref);
					$changes['removed']++;
					$accessChanged[$userId] = true;
				} elseif (!isset($existing[$userId])) {
					// Removed in Talk since the import added them
					$this->refMapper->delete($ref);
				}
			}
		}, $this->db);

		foreach (array_keys($accessChanged) as $userId) {
			$this->dispatcher->dispatchTyped(new UserShareAccessUpdatedEvent(new LazyUser((string)$userId, $this->userManager)));
		}

		if ($changes['added'] + $changes['updated'] + $changes['removed'] > 0) {
			$this->audit(Operation::HISTORY_IMPORT_PARTICIPANTS_SET, 'The history import of {app} added {added}, changed {updated} and removed {removed} participants of conversation "{token}"', [
				'app' => $app,
				'token' => $room->getToken(),
				'added' => $changes['added'],
				'updated' => $changes['updated'],
				'removed' => $changes['removed'],
			]);
		}

		return $results;
	}

	#[\Override]
	public function importMessages(string $app, Room $room, array $messages): array {
		$this->validateApp($app);
		$roomRef = $this->getRoomRef($app, $room);
		if (count($messages) > self::MAX_ENTRIES) {
			throw new ImportException(ImportException::CODE_INVALID, 'too many messages');
		}

		/** @var array<int, ImportException> $errors */
		$errors = [];
		/** @var array<int, array{0: Node, 1: string}> $nodes */
		$nodes = [];
		$placeholders = [];
		$refsInBatch = [];
		foreach ($messages as $i => $message) {
			if (!$message instanceof ImportMessage) {
				throw new ImportException(ImportException::CODE_INVALID, 'message ' . $i . ' is not an ImportMessage');
			}
			try {
				$this->validateMessage($message);
				if (isset($refsInBatch[$message->ref])) {
					throw new ImportException(ImportException::CODE_INVALID, 'ref is listed twice');
				}
				foreach ($message->fileIds as $fileId) {
					$nodes[$fileId] ??= $this->resolveFile($fileId, $roomRef);
				}
				foreach ($this->getActors($message) as $actor) {
					if ($actor->type === Attendee::ACTOR_IMPORTED) {
						$placeholders[$actor->id] = $actor;
					}
				}
				$refsInBatch[$message->ref] = $i;
			} catch (ImportException $e) {
				$errors[$i] = $e;
			}
		}

		// Placeholders first and outside the transaction: a parallel request
		// may write the same one, which fails a transaction on PostgreSQL.
		foreach ($placeholders as $actor) {
			$this->ensurePlaceholder($app, $actor->id, $actor->displayName);
		}

		$knownRefs = [];
		foreach ($messages as $message) {
			$knownRefs[$message->ref] = true;
			if ($message->parentRef !== '') {
				$knownRefs[$message->parentRef] = true;
			}
		}
		$refs = $this->refMapper->findByRefs($app, ImportRef::KIND_MESSAGE, $room->getId(), array_map(strval(...), array_keys($knownRefs)));

		$results = [];
		$this->atomic(function () use ($app, $room, $messages, $errors, $nodes, &$refs, &$results): void {
			$batch = new ImportBatch();
			foreach ($messages as $i => $message) {
				if (isset($errors[$i])) {
					$results[$i] = ImportResult::fromException($message->ref, $errors[$i]);
					continue;
				}
				$results[$i] = $this->importMessage($app, $room, $message, $nodes, $refs, $batch);
			}

			$this->refMapper->insertMany($batch->newRefs);
			$this->commentsManager->updateChildrenInformationOf(array_keys($batch->parents));
			$this->commentsManager->updateReactionSummary(array_keys($batch->reactionParents));
			$this->threadService->recountThreads($room, array_keys($batch->threads));
		}, $this->db);

		ksort($results);
		return array_values($results);
	}

	#[\Override]
	public function statMessages(string $app, Room $room, array $refs, bool $hash): array {
		$this->validateApp($app);
		$this->getRoomRef($app, $room);
		if (count($refs) > self::MAX_ENTRIES) {
			throw new ImportException(ImportException::CODE_INVALID, 'too many refs');
		}
		$refs = array_map(strval(...), $refs);

		$found = $this->refMapper->findByRefs($app, ImportRef::KIND_MESSAGE, $room->getId(), $refs);
		$active = array_values(array_filter($found, static fn (ImportRef $ref): bool => $ref->getState() === ImportRef::STATE_ACTIVE));
		$stored = $hash ? $this->computeStoredHashes($room, $active) : [];
		$comments = $hash ? [] : $this->commentsManager->getCommentsById(array_map(static fn (ImportRef $ref): string => (string)$ref->getObjectId(), $active));

		$stats = [];
		foreach ($refs as $ref) {
			$row = $found[$ref] ?? null;
			if ($row === null || $row->getState() !== ImportRef::STATE_ACTIVE) {
				$stats[] = new ImportMessageStat($ref, false);
				continue;
			}
			if ($hash) {
				$storedHash = $stored[$ref] ?? '';
				$stats[] = new ImportMessageStat($ref, true, $row->getObjectId(), $storedHash, $storedHash !== $row->getSha256());
			} else {
				$comment = $comments[$row->getObjectId()] ?? null;
				$changed = $comment === null || $comment->getVerb() === ChatManager::VERB_MESSAGE_DELETED;
				$stats[] = new ImportMessageStat($ref, true, $row->getObjectId(), '', $changed);
			}
		}
		return $stats;
	}

	#[\Override]
	public function listMessages(string $app, Room $room, string $cursor, int $limit): array {
		$this->validateApp($app);
		$this->getRoomRef($app, $room);
		if ($cursor !== '' && !ctype_digit($cursor)) {
			throw new ImportException(ImportException::CODE_INVALID, 'cursor');
		}
		$limit = min(self::MAX_LIST_LIMIT, max(1, $limit));

		$rows = $this->refMapper->findMessagesAfter($app, $room->getId(), (int)$cursor, $limit);
		$entries = array_map(static fn (ImportRef $ref): array => [
			'ref' => (string)$ref->getRef(),
			'message_id' => $ref->getObjectId(),
		], $rows);

		$next = '';
		$last = end($rows);
		if (count($rows) === $limit && $last !== false) {
			$next = (string)$last->getObjectId();
		}
		return ['entries' => $entries, 'next_cursor' => $next];
	}

	#[\Override]
	public function removeMessages(string $app, Room $room, array $refs): array {
		$this->validateApp($app);
		$this->getRoomRef($app, $room);
		if (count($refs) > self::MAX_ENTRIES) {
			throw new ImportException(ImportException::CODE_INVALID, 'too many refs');
		}
		$refs = array_map(strval(...), $refs);

		$found = $this->refMapper->findByRefs($app, ImportRef::KIND_MESSAGE, $room->getId(), $refs);
		$active = array_values(array_filter($found, static fn (ImportRef $ref): bool => $ref->getState() === ImportRef::STATE_ACTIVE));
		$stored = $this->computeStoredHashes($room, $active);

		$results = [];
		$removed = 0;
		$this->atomic(function () use ($app, $room, $refs, $found, $stored, &$results, &$removed): void {
			$now = $this->timeFactory->getTime();
			foreach ($refs as $ref) {
				$row = $found[$ref] ?? null;
				if ($row === null || $row->getState() !== ImportRef::STATE_ACTIVE) {
					$results[] = ImportResult::ok($ref, $row?->getObjectId() ?? 0);
					continue;
				}
				if (($stored[$ref] ?? '') !== $row->getSha256()) {
					$results[] = ImportResult::conflict($ref, ImportException::CODE_CHANGED, 'the message was changed in Talk', $row->getObjectId());
					continue;
				}

				$this->deleteMessageComments($app, $room, $row, $now);
				$row->setState(ImportRef::STATE_REMOVED);
				$this->refMapper->update($row);
				$results[] = ImportResult::ok($ref, $row->getObjectId());
				$removed++;
			}
		}, $this->db);

		if ($removed > 0) {
			$this->clearChatCaches($room);
			$this->audit(Operation::HISTORY_IMPORT_MESSAGES_REMOVED, 'The history import of {app} removed {count} imported messages of conversation "{token}"', [
				'app' => $app,
				'token' => $room->getToken(),
				'count' => $removed,
			]);
		}
		return $results;
	}

	#[\Override]
	public function finish(string $app, Room $room): void {
		$this->validateApp($app);
		$roomRef = $this->getRoomRef($app, $room);

		$query = $this->db->getQueryBuilder();
		$query->select('*')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter((string)$room->getId())))
			->andWhere($query->expr()->notIn('verb', $query->createNamedParameter([ChatManager::VERB_REACTION, ChatManager::VERB_REACTION_DELETED], IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('creation_timestamp', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults(1);
		$result = $query->executeQuery();
		$row = $result->fetchAssociative();
		$result->closeCursor();

		$this->atomic(function () use ($app, $room, $roomRef, $row): void {
			if ($row !== false && (int)$row['id'] !== $room->getLastMessageId()) {
				$this->roomService->setLastMessage($room, $this->commentsManager->getCommentFromData($row));
			}

			$newestImported = $this->getNewestImportedMessageId($app, $room);
			$previousNewest = $roomRef->getParentId();
			if ($newestImported > $previousNewest) {
				$this->advanceReadMarkers($app, $room, $previousNewest, $newestImported);
				$roomRef->setParentId($newestImported);
				$this->refMapper->update($roomRef);
			}
		}, $this->db);

		$this->clearChatCaches($room);
	}

	/**
	 * Writes one message of a batch, or answers it from what was written before
	 *
	 * @param array<int, array{0: Node, 1: string}> $nodes
	 * @param array<string, ImportRef> $refs refs of the batch and of its parents, updated with what is written
	 */
	private function importMessage(string $app, Room $room, ImportMessage $message, array $nodes, array &$refs, ImportBatch $batch): ImportResult {
		$hash = CanonicalForm::hashMessage($message);
		$existing = $refs[$message->ref] ?? null;

		if ($existing !== null && $existing->getState() === ImportRef::STATE_ACTIVE) {
			if ($existing->getSha256() === $hash) {
				return ImportResult::ok($message->ref, $existing->getObjectId(), $hash);
			}
			if (!$message->newVersion) {
				return ImportResult::conflict($message->ref, ImportException::CODE_EXISTS, 'the ref was imported with other content', $existing->getObjectId(), (string)$existing->getSha256());
			}
			$stored = $this->computeStoredHashes($room, [$existing]);
			if (($stored[$message->ref] ?? '') !== $existing->getSha256()) {
				return ImportResult::conflict($message->ref, ImportException::CODE_CHANGED, 'the message was changed in Talk', $existing->getObjectId(), (string)$existing->getSha256());
			}
			try {
				$this->updateMessage($room, $existing, $message, $hash, $batch);
			} catch (ImportException $e) {
				return ImportResult::conflict($message->ref, $e->getImportCode(), $e->getMessage(), $existing->getObjectId(), (string)$existing->getSha256());
			}
			return ImportResult::ok($message->ref, $existing->getObjectId(), $hash);
		}

		$parent = null;
		if ($message->parentRef !== '') {
			$parentRef = $refs[$message->parentRef] ?? null;
			if ($parentRef === null || $parentRef->getState() !== ImportRef::STATE_ACTIVE) {
				return ImportResult::fromException($message->ref, new ImportException(ImportException::CODE_PARENT_MISSING, 'the parent was not imported'));
			}
			$parent = $batch->comments[$parentRef->getObjectId()] ?? null;
			if ($parent === null) {
				try {
					$parent = $this->commentsManager->get((string)$parentRef->getObjectId());
				} catch (NotFoundException) {
					return ImportResult::fromException($message->ref, new ImportException(ImportException::CODE_PARENT_MISSING, 'the parent was deleted'));
				}
			}
		}

		[$main, $parts, $reactions] = $this->writeMessage($app, $room, $message, $parent, $nodes, $batch);

		$ref = $existing ?? new ImportRef();
		$ref->setApp($app);
		$ref->setKind(ImportRef::KIND_MESSAGE);
		$ref->setRef($message->ref);
		$ref->setRefHash(ImportRef::hashRef(ImportRef::KIND_MESSAGE, $room->getId(), $message->ref));
		$ref->setRoomId($room->getId());
		$ref->setObjectId((int)$main->getId());
		$ref->setSha256($hash);
		$ref->setState(ImportRef::STATE_ACTIVE);
		$ref->setEncodedDetails($parts, $reactions);
		if ($existing === null) {
			$batch->newRefs[] = $ref;
		} else {
			$this->refMapper->update($ref);
		}
		$refs[$message->ref] = $ref;

		return ImportResult::ok($message->ref, (int)$main->getId(), $hash);
	}

	/**
	 * @param array<int, array{0: Node, 1: string}> $nodes file node and sharer by file ID
	 * @return array{0: IComment, 1: list<int>, 2: list<array{type: string, id: string, emoji: string, comment: int}>}
	 */
	private function writeMessage(string $app, Room $room, ImportMessage $message, ?IComment $parent, array $nodes, ImportBatch $batch): array {
		$created = \DateTime::createFromImmutable($message->created)->setTimezone(new \DateTimeZone('UTC'));

		$parentId = 0;
		$topmostId = 0;
		$threadId = Thread::THREAD_NONE;
		if ($parent !== null) {
			$parentId = (int)$parent->getId();
			$topmostId = (int)$parent->getTopmostParentId() ?: $parentId;
			if (isset($batch->threadRoots[$topmostId]) || $this->threadService->validateThread($room->getId(), $topmostId)) {
				$threadId = $topmostId;
				$batch->threads[$threadId] = true;
			}
			$batch->parents[$parentId] = true;
		}

		$metaData = [Message::METADATA_CAN_MENTION_ALL => true];
		if ($threadId !== Thread::THREAD_NONE) {
			$metaData[Message::METADATA_THREAD_ID] = $threadId;
		}

		$shares = [];
		foreach ($message->fileIds as $fileId) {
			$shares[$fileId] = $this->ensureShare($app, $room, $fileId, $nodes[$fileId][0], $nodes[$fileId][1], $created->getTimestamp(), $batch);
		}

		$bodies = $this->getBodies($message, $nodes, $shares);
		$last = count($bodies) - 1;
		$parts = [];
		$main = null;
		foreach ($bodies as $index => [$verb, $body, $parameters, $fileId]) {
			$isMain = $index === $last;
			$comment = $this->commentsManager->create($message->actor->type, $message->actor->id, 'chat', (string)$room->getId());
			$comment->setVerb($verb);
			$comment->setMessage($body, ChatManager::MAX_CHAT_LENGTH);
			$comment->setCreationDateTime($created);
			if ($isMain && $parentId !== 0) {
				$comment->setParentId((string)$parentId);
				$comment->setTopmostParentId((string)$topmostId);
			} elseif ($threadId !== Thread::THREAD_NONE) {
				$comment->setParentId((string)$threadId);
				$comment->setTopmostParentId((string)$threadId);
				$batch->parents[$threadId] = true;
			}
			$commentMetaData = $metaData;
			if ($isMain && $message->edited !== null) {
				$commentMetaData[Message::METADATA_LAST_EDITED_BY_TYPE] = $message->actor->type;
				$commentMetaData[Message::METADATA_LAST_EDITED_BY_ID] = $message->actor->id;
				$commentMetaData[Message::METADATA_LAST_EDITED_TIME] = $message->edited->getTimestamp();
			}
			$comment->setMetaData($commentMetaData);
			$this->commentsManager->insertImported($comment);

			if ($fileId !== 0) {
				$this->attachmentService->createAttachmentEntry($room, $comment, 'file_shared', $parameters);
				$this->recordShareUse($shares[$fileId], (int)$comment->getId(), $created->getTimestamp());
			}

			if ($isMain) {
				$main = $comment;
			} else {
				$parts[] = (int)$comment->getId();
			}
		}
		/** @var IComment $main */

		if ($message->threadTitle !== '') {
			$this->startThread($room, $main, $message->threadTitle, $created, $batch);
		}

		$batch->comments[(int)$main->getId()] = $main;
		$reactions = $this->writeReactions($room, $main, $message->reactions, $batch);

		return [$main, $parts, $reactions];
	}

	/**
	 * The comments a message consists of, the main one last: a text
	 * message, one file share per file (the text as caption of the last),
	 * or one system message per member
	 *
	 * @param array<int, array{0: Node, 1: string}> $nodes
	 * @param array<int, ImportRef> $shares share refs by file ID
	 * @return non-empty-list<array{0: string, 1: string, 2: array, 3: int}> verb, message, parameters, file ID
	 */
	private function getBodies(ImportMessage $message, array $nodes, array $shares): array {
		if ($message->systemKind !== '') {
			$bodies = [];
			foreach ($message->members as $member) {
				$parameters = ['user' => $member->id];
				if ($member->type === Attendee::ACTOR_IMPORTED) {
					$parameters['actorType'] = Attendee::ACTOR_IMPORTED;
				}
				$bodies[] = [ChatManager::VERB_SYSTEM, json_encode(['message' => $message->systemKind, 'parameters' => $parameters], JSON_THROW_ON_ERROR), $parameters, 0];
			}
			/** @var non-empty-list<array{0: string, 1: string, 2: array, 3: int}> $bodies */
			return $bodies;
		}

		if ($message->fileIds === []) {
			return [[ChatManager::VERB_MESSAGE, $message->text, [], 0]];
		}

		$bodies = [];
		$last = count($message->fileIds) - 1;
		foreach ($message->fileIds as $index => $fileId) {
			$metaData = ['mimeType' => $nodes[$fileId][0]->getMimetype()];
			if ($index === $last && $message->text !== '') {
				$metaData['caption'] = $message->text;
			}
			$parameters = ['share' => (string)$shares[$fileId]->getObjectId(), 'metaData' => $metaData];
			$bodies[] = [ChatManager::VERB_OBJECT_SHARED, json_encode(['message' => 'file_shared', 'parameters' => $parameters], JSON_THROW_ON_ERROR), $parameters, $fileId];
		}
		/** @var non-empty-list<array{0: string, 1: string, 2: array, 3: int}> $bodies */
		return $bodies;
	}

	private function startThread(Room $room, IComment $root, string $title, \DateTime $created, ImportBatch $batch): void {
		$rootId = (int)$root->getId();
		$title = trim($title);
		if (mb_strlen($title) > self::MAX_THREAD_TITLE_LENGTH + 3) {
			$title = mb_substr($title, 0, self::MAX_THREAD_TITLE_LENGTH) . '…';
		}

		$metaData = $root->getMetaData() ?? [];
		$metaData[Message::METADATA_THREAD_ID] = $rootId;
		$metaData[Message::METADATA_THREAD_TITLE] = $title;
		$root->setMetaData($metaData);
		$this->commentsManager->updateImported($root);

		$thread = new Thread();
		$thread->setId($rootId);
		$thread->setRoomId($room->getId());
		$thread->setName($title);
		$thread->setLastActivity($created);
		$this->threadMapper->insert($thread);
		$batch->threadRoots[$rootId] = true;
	}

	/**
	 * @param list<ImportReaction> $reactions
	 * @return list<array{type: string, id: string, emoji: string, comment: int}>
	 */
	private function writeReactions(Room $room, IComment $message, array $reactions, ImportBatch $batch): array {
		$written = [];
		foreach ($reactions as $reaction) {
			$comment = $this->commentsManager->create($reaction->actor->type, $reaction->actor->id, 'chat', (string)$room->getId());
			$comment->setVerb(ChatManager::VERB_REACTION);
			$comment->setMessage($reaction->emoji);
			$comment->setCreationDateTime(\DateTime::createFromImmutable($reaction->created)->setTimezone(new \DateTimeZone('UTC')));
			$comment->setParentId($message->getId());
			$comment->setTopmostParentId($message->getTopmostParentId() !== '0' ? $message->getTopmostParentId() : $message->getId());
			$this->commentsManager->insertImported($comment);
			$this->commentsManager->insertImportedReactionRow($comment);
			$written[] = [
				'type' => $reaction->actor->type,
				'id' => $reaction->actor->id,
				'emoji' => $reaction->emoji,
				'comment' => (int)$comment->getId(),
			];
		}
		if ($written !== []) {
			$batch->reactionParents[(int)$message->getId()] = true;
			$batch->parents[(int)$message->getId()] = true;
		}
		return $written;
	}

	/**
	 * Replaces the text, edit time, author, time and reactions of a message
	 * nobody changed in Talk; its parent, files and system members stay.
	 *
	 * @throws ImportException When more than these changed
	 */
	private function updateMessage(Room $room, ImportRef $ref, ImportMessage $message, string $hash, ImportBatch $batch): void {
		$stored = $this->readStoredMessages($room, [$ref])[0] ?? null;
		if ($stored === null
			|| $stored['parentRef'] !== $message->parentRef
			|| $stored['systemKind'] !== $message->systemKind
			|| $stored['fileIds'] !== $this->sortedFileIds($message->fileIds)
			|| $stored['members'] !== $this->sortedPairs(array_map(static fn (ImportActor $m): array => [$m->type, $m->id], $message->members))) {
			throw new ImportException(ImportException::CODE_EXISTS, 'only the text, edit time, author, time and reactions of an imported message can be replaced');
		}

		$details = $ref->getDecodedDetails();
		$created = \DateTime::createFromImmutable($message->created)->setTimezone(new \DateTimeZone('UTC'));
		$comments = $this->commentsManager->getCommentsById(array_map(strval(...), [...$details['parts'], $ref->getObjectId()]));
		foreach ($comments as $id => $comment) {
			$comment->setActor($message->actor->type, $message->actor->id);
			$comment->setCreationDateTime($created);
			if ($id === $ref->getObjectId()) {
				if ($comment->getVerb() === ChatManager::VERB_OBJECT_SHARED) {
					$data = json_decode($comment->getMessage(), true, flags: JSON_THROW_ON_ERROR);
					if ($message->text === '') {
						unset($data['parameters']['metaData']['caption']);
					} else {
						$data['parameters']['metaData']['caption'] = $message->text;
					}
					$comment->setMessage(json_encode($data, JSON_THROW_ON_ERROR), ChatManager::MAX_CHAT_LENGTH);
				} elseif ($comment->getVerb() === ChatManager::VERB_MESSAGE) {
					$comment->setMessage($message->text, ChatManager::MAX_CHAT_LENGTH);
				}
				$metaData = $comment->getMetaData() ?? [];
				unset($metaData[Message::METADATA_LAST_EDITED_BY_TYPE], $metaData[Message::METADATA_LAST_EDITED_BY_ID], $metaData[Message::METADATA_LAST_EDITED_TIME]);
				if ($message->edited !== null) {
					$metaData[Message::METADATA_LAST_EDITED_BY_TYPE] = $message->actor->type;
					$metaData[Message::METADATA_LAST_EDITED_BY_ID] = $message->actor->id;
					$metaData[Message::METADATA_LAST_EDITED_TIME] = $message->edited->getTimestamp();
				}
				$comment->setMetaData($metaData);
			}
			$this->commentsManager->updateImported($comment);
		}

		$wanted = [];
		foreach ($message->reactions as $reaction) {
			$wanted[$reaction->actor->type . "\n" . $reaction->actor->id . "\n" . $reaction->emoji] = $reaction;
		}
		$kept = [];
		$obsolete = [];
		foreach ($details['reactions'] as $reaction) {
			$key = $reaction['type'] . "\n" . $reaction['id'] . "\n" . $reaction['emoji'];
			if (isset($wanted[$key])) {
				$kept[] = $reaction;
				unset($wanted[$key]);
			} else {
				$obsolete[] = $reaction['comment'];
			}
		}
		$this->commentsManager->deleteImported($obsolete);

		$main = $comments[$ref->getObjectId()];
		$added = $this->writeReactions($room, $main, array_values($wanted), $batch);
		$batch->reactionParents[$ref->getObjectId()] = true;
		$batch->parents[$ref->getObjectId()] = true;

		$ref->setSha256($hash);
		$ref->setEncodedDetails($details['parts'], [...$kept, ...$added]);
		$this->refMapper->update($ref);
	}

	/**
	 * Marks the comments of a message deleted like Talk does when its
	 * author deletes it, and gives up its file shares
	 */
	private function deleteMessageComments(string $app, Room $room, ImportRef $ref, int $now): void {
		$details = $ref->getDecodedDetails();
		$comments = $this->commentsManager->getCommentsById(array_map(strval(...), [...$details['parts'], $ref->getObjectId()]));
		foreach ($comments as $id => $comment) {
			if ($comment->getVerb() === ChatManager::VERB_OBJECT_SHARED) {
				$data = json_decode($comment->getMessage(), true);
				$shareId = (int)($data['parameters']['share'] ?? 0);
				if ($shareId !== 0) {
					$this->releaseShareUse($app, $room, $shareId, $id);
				}
			}
			$this->attachmentService->deleteAttachmentByMessageId($id);

			$comment->setMessage(json_encode([
				'deleted_by_type' => $comment->getActorType(),
				'deleted_by_id' => $comment->getActorId(),
				'deleted_on' => $now,
			], JSON_THROW_ON_ERROR));
			$comment->setVerb(ChatManager::VERB_MESSAGE_DELETED);
			$metaData = $comment->getMetaData() ?? [];
			unset($metaData[Message::METADATA_LAST_EDITED_BY_TYPE], $metaData[Message::METADATA_LAST_EDITED_BY_ID], $metaData[Message::METADATA_LAST_EDITED_TIME]);
			$comment->setMetaData($metaData);
			$this->commentsManager->updateImported($comment);
		}
	}

	/**
	 * The share of a file in the conversation the import uses: the one it
	 * created before, a room share of the same file somebody else created
	 * (adopted, never changed), or a new room share of the file's owner
	 */
	private function ensureShare(string $app, Room $room, int $fileId, Node $node, string $sharer, int $time, ImportBatch $batch): ImportRef {
		if (isset($batch->shares[$fileId])) {
			return $batch->shares[$fileId];
		}

		try {
			$ref = $this->refMapper->findByRef($app, ImportRef::KIND_SHARE, $room->getId(), (string)$fileId);
			try {
				$this->shareProvider->getShareById((string)$ref->getObjectId());
				return $batch->shares[$fileId] = $ref;
			} catch (ShareNotFound) {
				// Deleted in Talk, share the file again
			}
		} catch (DoesNotExistException) {
			$ref = new ImportRef();
			$ref->setApp($app);
			$ref->setKind(ImportRef::KIND_SHARE);
			$ref->setRef((string)$fileId);
			$ref->setRefHash(ImportRef::hashRef(ImportRef::KIND_SHARE, $room->getId(), (string)$fileId));
			$ref->setRoomId($room->getId());
		}

		$adopted = false;
		$shareId = 0;
		foreach ($this->shareProvider->getSharesByPath($node) as $share) {
			if ($share->getSharedWith() === $room->getToken()) {
				$shareId = (int)$share->getId();
				$adopted = true;
				break;
			}
		}
		if (!$adopted) {
			$shareId = (int)$this->shareProvider->createImportedShare($room, $node, $sharer, $time)->getId();
		}

		$ref->setObjectId($shareId);
		$ref->setDetails(json_encode(['adopted' => $adopted, 'uses' => new \stdClass()], JSON_THROW_ON_ERROR));
		if ($ref->getId() === null) {
			$this->refMapper->insert($ref);
		} else {
			$this->refMapper->update($ref);
		}
		return $batch->shares[$fileId] = $ref;
	}

	/**
	 * Notes that a message comment uses the share; the share time follows the
	 * newest message using it, so everybody who can see one of them can open
	 * the file
	 */
	private function recordShareUse(ImportRef $ref, int $commentId, int $time): void {
		$details = $this->decodeShareDetails($ref);
		$details['uses'][(string)$commentId] = $time;
		$this->saveShareDetails($ref, $details);
	}

	private function releaseShareUse(string $app, Room $room, int $shareId, int $commentId): void {
		$refs = array_filter(
			$this->refMapper->findAllInRoom($app, ImportRef::KIND_SHARE, $room->getId()),
			static fn (ImportRef $ref): bool => $ref->getObjectId() === $shareId,
		);
		foreach ($refs as $ref) {
			$details = $this->decodeShareDetails($ref);
			unset($details['uses'][(string)$commentId]);
			if ($details['uses'] === [] && !$details['adopted']) {
				try {
					$this->shareProvider->delete($this->shareProvider->getShareById((string)$shareId));
				} catch (ShareNotFound) {
				}
				$this->refMapper->delete($ref);
				continue;
			}
			$this->saveShareDetails($ref, $details);
		}
	}

	/**
	 * @return array{adopted: bool, uses: array<string, int>}
	 */
	private function decodeShareDetails(ImportRef $ref): array {
		$decoded = json_decode((string)$ref->getDetails(), true);
		$uses = [];
		if (is_array($decoded) && is_array($decoded['uses'] ?? null)) {
			foreach ($decoded['uses'] as $commentId => $time) {
				$uses[(string)$commentId] = (int)$time;
			}
		}
		return ['adopted' => is_array($decoded) && !empty($decoded['adopted']), 'uses' => $uses];
	}

	/**
	 * @param array{adopted: bool, uses: array<string, int>} $details
	 */
	private function saveShareDetails(ImportRef $ref, array $details): void {
		$ref->setDetails(json_encode(['adopted' => $details['adopted'], 'uses' => (object)$details['uses']], JSON_THROW_ON_ERROR));
		$this->refMapper->update($ref);
		if (!$details['adopted'] && $details['uses'] !== []) {
			$this->shareProvider->setImportedShareTime($ref->getObjectId(), max($details['uses']));
		}
	}

	/**
	 * The canonical hash of what Talk holds now for each ref, '' when the
	 * message is gone
	 *
	 * @param list<ImportRef> $refs
	 * @return array<string, string> keyed by ref
	 */
	private function computeStoredHashes(Room $room, array $refs): array {
		$hashes = [];
		foreach ($this->readStoredMessages($room, $refs) as $stored) {
			$hashes[$stored['ref']] = CanonicalForm::hash(
				$stored['ref'],
				$stored['parentRef'],
				$stored['actorType'],
				$stored['actorId'],
				$stored['created'],
				$stored['text'],
				$stored['fileIds'],
				$stored['reactions'],
				$stored['systemKind'],
				$stored['members'],
			);
		}
		foreach ($refs as $ref) {
			$hashes[(string)$ref->getRef()] ??= '';
		}
		return $hashes;
	}

	/**
	 * The fields of the canonical form as Talk stores them now
	 *
	 * @param list<ImportRef> $refs
	 * @return list<array{ref: string, parentRef: string, actorType: string, actorId: string, created: \DateTime, text: string, fileIds: list<int>, reactions: list<array{0: string, 1: string, 2: string}>, systemKind: string, members: list<array{0: string, 1: string}>}>
	 */
	private function readStoredMessages(Room $room, array $refs): array {
		$commentIds = [];
		$details = [];
		foreach ($refs as $ref) {
			$details[(string)$ref->getRef()] = $ref->getDecodedDetails();
			$commentIds[] = $ref->getObjectId();
			foreach ($details[(string)$ref->getRef()]['parts'] as $id) {
				$commentIds[] = $id;
			}
			foreach ($details[(string)$ref->getRef()]['reactions'] as $reaction) {
				$commentIds[] = $reaction['comment'];
			}
		}

		$comments = [];
		foreach (array_chunk(array_values(array_unique($commentIds)), IQueryBuilder::MAX_IN_PARAMETERS) as $chunk) {
			$comments += $this->commentsManager->getCommentsById(array_map(strval(...), $chunk));
		}

		$shareIds = [];
		$parentIds = [];
		foreach ($comments as $comment) {
			if ($comment->getVerb() === ChatManager::VERB_OBJECT_SHARED) {
				$data = json_decode($comment->getMessage(), true);
				$shareIds[] = (int)($data['parameters']['share'] ?? 0);
			}
			if ($comment->getParentId() !== '0') {
				$parentIds[] = (int)$comment->getParentId();
			}
		}
		$fileIdsByShare = $this->shareProvider->getFileIdsOfShares($shareIds);
		$parentRefs = $this->refMapper->findMessagesByIds($room->getId(), $parentIds);

		$stored = [];
		foreach ($refs as $ref) {
			$key = (string)$ref->getRef();
			$main = $comments[$ref->getObjectId()] ?? null;
			if ($main === null) {
				continue;
			}

			$text = '';
			$systemKind = '';
			$fileIds = [];
			$members = [];
			$ids = [...$details[$key]['parts'], $ref->getObjectId()];
			foreach ($ids as $id) {
				$comment = $comments[$id] ?? null;
				if ($comment === null) {
					continue;
				}
				$verb = $comment->getVerb();
				if ($verb === ChatManager::VERB_MESSAGE && $id === $ref->getObjectId()) {
					$text = $comment->getMessage();
				} elseif ($verb === ChatManager::VERB_OBJECT_SHARED) {
					$data = json_decode($comment->getMessage(), true);
					$fileId = $fileIdsByShare[(int)($data['parameters']['share'] ?? 0)] ?? null;
					if ($fileId !== null) {
						$fileIds[] = $fileId;
					}
					if ($id === $ref->getObjectId()) {
						$text = (string)($data['parameters']['metaData']['caption'] ?? '');
					}
				} elseif ($verb === ChatManager::VERB_SYSTEM) {
					$data = json_decode($comment->getMessage(), true);
					$systemKind = (string)($data['message'] ?? '');
					$members[] = [(string)($data['parameters']['actorType'] ?? Attendee::ACTOR_USERS), (string)($data['parameters']['user'] ?? '')];
				}
			}

			$reactions = [];
			foreach ($details[$key]['reactions'] as $reaction) {
				$comment = $comments[$reaction['comment']] ?? null;
				if ($comment !== null && $comment->getVerb() === ChatManager::VERB_REACTION) {
					$reactions[] = [$comment->getActorType(), $comment->getActorId(), $comment->getMessage()];
				}
			}

			$parentId = (int)$main->getParentId();
			$stored[] = [
				'ref' => $key,
				'parentRef' => $parentId !== 0 ? (string)($parentRefs[$parentId]?->getRef() ?? '') : '',
				'actorType' => $main->getActorType(),
				'actorId' => $main->getActorId(),
				'created' => $main->getCreationDateTime(),
				'text' => $text,
				'fileIds' => $this->sortedFileIds($fileIds),
				'reactions' => $reactions,
				'systemKind' => $systemKind,
				'members' => $this->sortedPairs($members),
			];
		}
		return $stored;
	}

	/**
	 * @param list<int> $fileIds
	 * @return list<int>
	 */
	private function sortedFileIds(array $fileIds): array {
		sort($fileIds, SORT_NUMERIC);
		return $fileIds;
	}

	/**
	 * @param list<array{0: string, 1: string}> $pairs
	 * @return list<array{0: string, 1: string}>
	 */
	private function sortedPairs(array $pairs): array {
		usort($pairs, static fn (array $a, array $b): int => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));
		return $pairs;
	}

	private function getRoomRef(string $app, Room $room): ImportRef {
		$ref = $this->roomRefs[$room->getId()] ?? null;
		if ($ref !== null && $ref->getApp() === $app) {
			return $ref;
		}
		try {
			$ref = $this->refMapper->findRoom($app, $room->getId());
		} catch (DoesNotExistException) {
			throw new ImportException(ImportException::CODE_NOT_IMPORTED, 'the conversation was not created by this import');
		}
		$this->roomRefs[$room->getId()] = $ref;
		return $ref;
	}

	private function validateApp(string $app): void {
		if (!preg_match(self::APP_PATTERN, $app)) {
			throw new ImportException(ImportException::CODE_INVALID, 'app must be a lowercase app ID');
		}
	}

	private function validateRef(string $ref, string $field): void {
		if ($ref === '' || strlen($ref) > self::MAX_REF_LENGTH || !mb_check_encoding($ref, 'UTF-8')) {
			throw new ImportException(ImportException::CODE_INVALID, $field . ' must be 1 to ' . self::MAX_REF_LENGTH . ' bytes of UTF-8');
		}
	}

	private function validateObject(string $objectType, string $objectId): void {
		if ($objectType === '' && $objectId === '') {
			return;
		}
		if ($objectId === '' || strlen($objectId) > 64) {
			throw new ImportException(ImportException::CODE_INVALID, 'object ID must have 1 to 64 characters');
		}
		if ($objectType === Room::OBJECT_TYPE_EVENT) {
			// Event conversations with "start#end" object IDs are deleted
			// after the event by the retention job (ExpireObjectRooms)
			if (str_contains($objectId, '#')) {
				throw new ImportException(ImportException::CODE_INVALID, 'object ID of an event is its UID without "#"');
			}
			return;
		}
		if ($objectType === Room::OBJECT_TYPE_FILE) {
			if (!ctype_digit($objectId)) {
				throw new ImportException(ImportException::CODE_INVALID, 'object ID of a file is its file ID');
			}
			return;
		}
		throw new ImportException(ImportException::CODE_INVALID, 'object type must be empty, event or file');
	}

	private function validateOneToOneName(string $name): string {
		try {
			$users = json_decode($name, true, 2, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			$users = null;
		}
		if (!is_array($users) || count($users) !== 2 || !array_is_list($users)
			|| !is_string($users[0]) || !is_string($users[1]) || $users[0] === $users[1]) {
			throw new ImportException(ImportException::CODE_INVALID, 'name of a one-to-one conversation is the JSON list of its two user IDs');
		}
		foreach ($users as $userId) {
			if (!$this->userExists($userId)) {
				throw new ImportException(ImportException::CODE_UNKNOWN_USER, 'a user of the one-to-one conversation is not an account');
			}
		}
		sort($users);

		try {
			$existing = $this->manager->getOne2OneRoom($users[0], $users[1]);
			throw new ImportException(ImportException::CODE_EXISTS, 'a one-to-one conversation of the two users exists: ' . $existing->getToken());
		} catch (RoomNotFoundException) {
		}
		return json_encode($users, JSON_THROW_ON_ERROR);
	}

	/**
	 * @throws ImportException
	 */
	private function validateMessage(ImportMessage $message): void {
		$this->validateRef($message->ref, 'ref');
		if ($message->parentRef !== '') {
			$this->validateRef($message->parentRef, 'parent ref');
			if ($message->parentRef === $message->ref) {
				throw new ImportException(ImportException::CODE_INVALID, 'a message can not reply to itself');
			}
		}
		$this->validateActor($message->actor, 'actor');

		$now = $this->timeFactory->getTime();
		if ($message->created->getTimestamp() <= 0 || $message->created->getTimestamp() > $now + self::MAX_FUTURE_SECONDS) {
			throw new ImportException(ImportException::CODE_INVALID, 'creation time is out of range');
		}
		if ($message->edited !== null && $message->edited->getTimestamp() <= 0) {
			throw new ImportException(ImportException::CODE_INVALID, 'edit time is out of range');
		}

		if ($message->threadTitle !== '' && ($message->parentRef !== '' || $message->systemKind !== '')) {
			throw new ImportException(ImportException::CODE_INVALID, 'only a post without parent can start a thread');
		}
		if (mb_strlen($message->threadTitle) > 255) {
			throw new ImportException(ImportException::CODE_INVALID, 'thread title is too long');
		}

		if ($message->systemKind !== '') {
			if (!in_array($message->systemKind, [ImportMessage::SYSTEM_USER_ADDED, ImportMessage::SYSTEM_USER_REMOVED, ImportMessage::SYSTEM_HISTORY_SHARED], true)) {
				throw new ImportException(ImportException::CODE_INVALID, 'unknown system kind');
			}
			if ($message->text !== '' || $message->fileIds !== [] || $message->members === []) {
				throw new ImportException(ImportException::CODE_INVALID, 'a system message has members and neither text nor files');
			}
			$seen = [];
			foreach ($message->members as $i => $member) {
				if (!$member instanceof ImportActor) {
					throw new ImportException(ImportException::CODE_INVALID, 'member ' . $i . ' is not an ImportActor');
				}
				$this->validateActor($member, 'member ' . $i);
				$key = $member->type . "\n" . $member->id;
				if (isset($seen[$key])) {
					throw new ImportException(ImportException::CODE_INVALID, 'member ' . $i . ' is listed twice');
				}
				$seen[$key] = true;
			}
		} elseif ($message->members !== []) {
			throw new ImportException(ImportException::CODE_INVALID, 'only system messages have members');
		} elseif ($message->text === '' && $message->fileIds === []) {
			throw new ImportException(ImportException::CODE_INVALID, 'a message has text or files');
		}

		if (mb_strlen($message->text) > ChatManager::MAX_CHAT_LENGTH) {
			throw new ImportException(ImportException::CODE_TOO_LONG, 'text is longer than ' . ChatManager::MAX_CHAT_LENGTH . ' characters');
		}

		if (count($message->fileIds) > self::MAX_FILES_PER_MESSAGE) {
			throw new ImportException(ImportException::CODE_INVALID, 'too many files');
		}
		$files = [];
		foreach ($message->fileIds as $i => $fileId) {
			if (!is_int($fileId) || $fileId <= 0 || isset($files[$fileId])) {
				throw new ImportException(ImportException::CODE_INVALID, 'file ' . $i . ' is not a file ID or listed twice');
			}
			$files[$fileId] = true;
		}

		$reactions = [];
		foreach ($message->reactions as $i => $reaction) {
			if (!$reaction instanceof ImportReaction) {
				throw new ImportException(ImportException::CODE_INVALID, 'reaction ' . $i . ' is not an ImportReaction');
			}
			$this->validateActor($reaction->actor, 'reactor ' . $i);
			if (!$this->emojiHelper->isValidSingleEmoji($reaction->emoji)) {
				throw new ImportException(ImportException::CODE_INVALID_REACTION, 'reaction ' . $i . ' is not a single emoji');
			}
			$key = $reaction->actor->type . "\n" . $reaction->actor->id . "\n" . $reaction->emoji;
			if (isset($reactions[$key])) {
				throw new ImportException(ImportException::CODE_INVALID, 'reaction ' . $i . ' is listed twice');
			}
			$reactions[$key] = true;
		}
	}

	private function validateActor(ImportActor $actor, string $field): void {
		if ($actor->id === '' || strlen($actor->id) > self::MAX_ACTOR_ID_LENGTH || !mb_check_encoding($actor->id, 'UTF-8')) {
			throw new ImportException(ImportException::CODE_INVALID, $field . ' ID must be 1 to ' . self::MAX_ACTOR_ID_LENGTH . ' bytes of UTF-8');
		}
		if ($actor->type === Attendee::ACTOR_USERS) {
			if (!$this->userExists($actor->id)) {
				throw new ImportException(ImportException::CODE_UNKNOWN_USER, $field . ' is not an account');
			}
			return;
		}
		if ($actor->type === Attendee::ACTOR_IMPORTED) {
			$name = trim($actor->displayName);
			if ($name === '' || mb_strlen($name) > self::MAX_DISPLAY_NAME_LENGTH) {
				throw new ImportException(ImportException::CODE_INVALID, $field . ' needs a display name of 1 to ' . self::MAX_DISPLAY_NAME_LENGTH . ' characters');
			}
			return;
		}
		throw new ImportException(ImportException::CODE_INVALID, $field . ' type must be users or imported');
	}

	/**
	 * @return list<ImportActor>
	 */
	private function getActors(ImportMessage $message): array {
		return [
			$message->actor,
			...$message->members,
			...array_map(static fn (ImportReaction $reaction): ImportActor => $reaction->actor, $message->reactions),
		];
	}

	/**
	 * The node of a file and the account that shares it: its owner, or the
	 * account the import acts as for files without an owner (team folders)
	 *
	 * @return array{0: Node, 1: string}
	 * @throws ImportException
	 */
	private function resolveFile(int $fileId, ImportRef $roomRef): array {
		$node = $this->rootFolder->getFirstNodeById($fileId);
		$sharer = $node?->getOwner()?->getUID() ?? (string)$roomRef->getOwnerId();
		if ($node === null || $sharer === '') {
			throw new ImportException(ImportException::CODE_FILE_NOT_FOUND, 'file ' . $fileId . ' does not exist');
		}

		$userNode = $this->rootFolder->getUserFolder($sharer)->getFirstNodeById($fileId);
		if ($userNode === null || $userNode->getType() !== FileInfo::TYPE_FILE) {
			throw new ImportException(ImportException::CODE_FILE_NOT_FOUND, 'file ' . $fileId . ' is not a file its owner can share');
		}
		return [$userNode, $sharer];
	}

	private function ensurePlaceholder(string $app, string $actorId, string $displayName): void {
		$displayName = trim($displayName);
		if (($this->writtenActors[$actorId] ?? null) === $displayName) {
			return;
		}

		try {
			$ref = $this->refMapper->findByRef($app, ImportRef::KIND_ACTOR, 0, $actorId);
			if ($ref->getDisplayName() !== $displayName) {
				$ref->setDisplayName($displayName);
				$this->refMapper->update($ref);
			}
		} catch (DoesNotExistException) {
			$ref = new ImportRef();
			$ref->setApp($app);
			$ref->setKind(ImportRef::KIND_ACTOR);
			$ref->setRef($actorId);
			$ref->setRefHash(ImportRef::hashRef(ImportRef::KIND_ACTOR, 0, $actorId));
			$ref->setDisplayName($displayName);
			try {
				$this->refMapper->insert($ref);
			} catch (DBException $e) {
				if ($e->getReason() !== DBException::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
					throw $e;
				}
			}
		}
		$this->writtenActors[$actorId] = $displayName;
		$this->actorDirectory->setDisplayName($actorId, $displayName);
	}

	private function addAttendee(string $app, Room $room, ImportParticipant $participant, int $lastReadMessage): Attendee {
		$attendee = new Attendee();
		$attendee->setRoomId($room->getId());
		$attendee->setActorType(Attendee::ACTOR_USERS);
		$attendee->setActorId($participant->userId);
		$attendee->setDisplayName($this->userManager->getDisplayName($participant->userId) ?? $participant->userId);
		$attendee->setParticipantType($room->getType() === Room::TYPE_ONE_TO_ONE ? Participant::OWNER : $participant->participantType);
		$attendee->setPermissions(Attendee::PERMISSIONS_DEFAULT);
		$attendee->setLastReadMessage($lastReadMessage);
		$attendee->setReadPrivacy($this->talkConfig->getUserReadPrivacy($participant->userId));
		$attendee->setSensitive($room->isClassified());
		$attendee->setNotificationLevel($participant->muted === true
			? Participant::NOTIFY_NEVER
			: ($room->isAnnouncement() ? Participant::NOTIFY_ALWAYS : Participant::NOTIFY_DEFAULT));
		$attendee->setArchived($participant->archived ?? false);
		$attendee->setFavorite($participant->favorite ?? false);
		$attendee->setHistorySince($participant->historySince?->getTimestamp() ?? 0);
		$this->attendeeMapper->insert($attendee);

		$ref = new ImportRef();
		$ref->setApp($app);
		$ref->setKind(ImportRef::KIND_PARTICIPANT);
		$ref->setRef($participant->userId);
		$ref->setRefHash(ImportRef::hashRef(ImportRef::KIND_PARTICIPANT, $room->getId(), $participant->userId));
		$ref->setRoomId($room->getId());
		$ref->setObjectId($attendee->getId());
		$this->refMapper->insert($ref);

		return $attendee;
	}

	/**
	 * @return bool whether something changed
	 */
	/**
	 * @param ?int $lastReadMessage the new read marker, null to keep it
	 */
	private function updateAttendee(Room $room, Attendee $attendee, ImportParticipant $participant, ?int $lastReadMessage): bool {
		$participantType = $room->getType() === Room::TYPE_ONE_TO_ONE ? Participant::OWNER : $participant->participantType;
		$attendee->setParticipantType($participantType);
		$attendee->setHistorySince($participant->historySince?->getTimestamp() ?? 0);
		if ($participant->archived !== null) {
			$attendee->setArchived($participant->archived);
		}
		if ($participant->favorite !== null) {
			$attendee->setFavorite($participant->favorite);
		}
		if ($participant->muted === true) {
			$attendee->setNotificationLevel(Participant::NOTIFY_NEVER);
		} elseif ($participant->muted === false && $attendee->getNotificationLevel() === Participant::NOTIFY_NEVER) {
			$attendee->setNotificationLevel(Participant::NOTIFY_DEFAULT);
		}
		if ($lastReadMessage !== null) {
			$attendee->setLastReadMessage($lastReadMessage);
		}

		if ($attendee->getUpdatedFields() === []) {
			return false;
		}
		$this->attendeeMapper->update($attendee);
		return true;
	}

	private function removeAttendee(Attendee $attendee): void {
		$this->sessionMapper->deleteByAttendeeId($attendee->getId());
		$this->threadService->removeThreadAttendeesByAttendeeIds([$attendee->getId()]);
		$this->attendeeMapper->delete($attendee);
	}

	/**
	 * @param list<ImportParticipant> $participants
	 */
	private function isListed(array $participants, string $userId): bool {
		foreach ($participants as $participant) {
			if ($participant->userId === $userId) {
				return true;
			}
		}
		return false;
	}

	/**
	 * ID of the newest message (of any kind) in the conversation, 0 for none
	 */
	private function getNewestMessageId(Room $room): int {
		$query = $this->db->getQueryBuilder();
		$query->select($query->func()->max('id'))
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter((string)$room->getId())));
		$result = $query->executeQuery();
		$id = (int)$result->fetchOne();
		$result->closeCursor();
		return $id;
	}

	/**
	 * ID of the newest message created up to the time, 0 for none
	 */
	private function getLastMessageIdUntil(Room $room, \DateTimeImmutable $until): int {
		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter((string)$room->getId())))
			->andWhere($query->expr()->lte('creation_timestamp', $query->createNamedParameter(\DateTime::createFromImmutable($until), IQueryBuilder::PARAM_DATETIME_MUTABLE), IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->orderBy('creation_timestamp', 'DESC')
			->addOrderBy('id', 'DESC')
			->setMaxResults(1);
		$result = $query->executeQuery();
		$id = (int)$result->fetchOne();
		$result->closeCursor();
		return $id;
	}

	private function getNewestImportedMessageId(string $app, Room $room): int {
		$query = $this->db->getQueryBuilder();
		$query->select($query->func()->max('object_id'))
			->from('talk_import_refs')
			->where($query->expr()->eq('room_id', $query->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($query->expr()->eq('kind', $query->createNamedParameter(ImportRef::KIND_MESSAGE)))
			->andWhere($query->expr()->eq('app', $query->createNamedParameter($app)));
		$result = $query->executeQuery();
		$id = (int)$result->fetchOne();
		$result->closeCursor();
		return $id;
	}

	/**
	 * Messages imported after a person's read marker was set count as read:
	 * managed participants who had read everything imported until the last
	 * finish() move on to the newest imported message
	 */
	private function advanceReadMarkers(string $app, Room $room, int $previousNewest, int $newest): void {
		$attendeeIds = array_map(
			static fn (ImportRef $ref): int => $ref->getObjectId(),
			$this->refMapper->findAllInRoom($app, ImportRef::KIND_PARTICIPANT, $room->getId()),
		);
		if ($attendeeIds === []) {
			return;
		}

		$update = $this->db->getQueryBuilder();
		$update->update('talk_attendees')
			->set('last_read_message', $update->createNamedParameter($newest, IQueryBuilder::PARAM_INT))
			->where($update->expr()->in('id', $update->createParameter('ids')))
			->andWhere($update->expr()->gte('last_read_message', $update->createNamedParameter($previousNewest, IQueryBuilder::PARAM_INT)))
			->andWhere($update->expr()->lt('last_read_message', $update->createNamedParameter($newest, IQueryBuilder::PARAM_INT)));
		foreach (array_chunk($attendeeIds, IQueryBuilder::MAX_IN_PARAMETERS) as $chunk) {
			$update->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
			$update->executeStatement();
		}
	}

	private function clearChatCaches(Room $room): void {
		$this->cacheFactory->createDistributed(CachePrefix::CHAT_LAST_MESSAGE_ID)->remove($room->getToken());
		$this->cacheFactory->createDistributed(CachePrefix::CHAT_UNREAD_COUNT)->clear($room->getId() . '-');
	}

	private function audit(Operation $operation, string $message, array $parameters): void {
		$this->dispatcher->dispatchTyped(new CriticalActionPerformedEvent($message, $parameters, false, $operation->value));
	}

	private function userExists(string $userId): bool {
		return $this->knownUsers[$userId] ??= $this->userManager->userExists($userId);
	}
}
