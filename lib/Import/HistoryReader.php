<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Import;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\CommentsManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\SelectHelper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\NotFoundException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\L10N\IFactory;

/**
 * {@see IHistoryReader} on the mappers and the comments table
 */
class HistoryReader implements IHistoryReader {
	/**
	 * Verbs of the comments a chat shows as messages; reactions are
	 * comments too, but count on the message they react to
	 */
	private const VERBS = [
		ChatManager::VERB_MESSAGE,
		ChatManager::VERB_SYSTEM,
		ChatManager::VERB_OBJECT_SHARED,
		ChatManager::VERB_COMMAND,
		ChatManager::VERB_MESSAGE_DELETED,
		ChatManager::VERB_VOICE_MESSAGE,
		ChatManager::VERB_RECORD_AUDIO,
		ChatManager::VERB_RECORD_VIDEO,
		ChatManager::VERB_PRIVATE_REPLY,
	];
	private const TYPES = [
		Room::TYPE_ONE_TO_ONE => self::TYPE_ONE_TO_ONE,
		Room::TYPE_ONE_TO_ONE_FORMER => self::TYPE_ONE_TO_ONE,
		Room::TYPE_GROUP => self::TYPE_GROUP,
		Room::TYPE_PUBLIC => self::TYPE_PUBLIC,
		Room::TYPE_CHANGELOG => self::TYPE_CHANGELOG,
		Room::TYPE_NOTE_TO_SELF => self::TYPE_NOTE_TO_SELF,
	];

	/** @var array<string, IL10N> by user ID */
	private array $l10n = [];

	public function __construct(
		private readonly IDBConnection $db,
		private readonly Manager $manager,
		private readonly ParticipantService $participantService,
		private readonly CommentsManager $commentsManager,
		private readonly MessageParser $messageParser,
		private readonly IUserManager $userManager,
		private readonly IFactory $l10nFactory,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	#[\Override]
	public function conversations(string $userId, string $cursor, int $limit): array {
		self::checkLimit($limit);
		if ($cursor !== '' && preg_match('/^[1-9][0-9]{0,18}$/D', $cursor) !== 1) {
			throw new ImportException(ImportException::CODE_INVALID, 'cursor is not a cursor of this listing');
		}

		$query = $this->db->getQueryBuilder();
		$helper = new SelectHelper();
		$helper->selectRoomsTable($query);
		$helper->selectAttendeesTable($query);
		$query->from('talk_rooms', 'r')
			->innerJoin('r', 'talk_attendees', 'a', $query->expr()->andX(
				$query->expr()->eq('a.room_id', 'r.id'),
				$query->expr()->eq('a.actor_type', $query->createNamedParameter(Attendee::ACTOR_USERS)),
				$query->expr()->eq('a.actor_id', $query->createNamedParameter($userId)),
			))
			->where($query->expr()->orX(
				$query->expr()->isNull('r.remote_server'),
				$query->expr()->emptyString('r.remote_server'),
			))
			->orderBy('r.id', 'ASC')
			->setMaxResults($limit + 1);
		if ($cursor !== '') {
			$query->andWhere($query->expr()->gt('r.id', $query->createNamedParameter((int)$cursor, IQueryBuilder::PARAM_INT)));
		}

		$result = $query->executeQuery();
		$rows = [];
		while ($row = $result->fetchAssociative()) {
			$rows[] = $row;
		}
		$result->closeCursor();

		$next = '';
		if (count($rows) > $limit) {
			array_pop($rows);
			$next = (string)$rows[$limit - 1]['r_id'];
		}

		$conversations = [];
		foreach ($rows as $row) {
			$room = $this->manager->createRoomObject($row);
			$participant = $this->manager->createParticipantObject($room, $row);
			$room->setParticipant($userId, $participant);
			$since = $participant->getHistorySince();
			$lastActivity = $room->getLastActivity();
			$conversations[] = new ReadConversation(
				$room->getToken(),
				$room->getDisplayName($userId),
				self::TYPES[$room->getType()] ?? self::TYPE_GROUP,
				$this->participantService->getNumberOfActors($room),
				$this->countMessages($room, $since),
				$lastActivity === null ? null : \DateTimeImmutable::createFromMutable($lastActivity),
				$since,
			);
		}

		return ['conversations' => $conversations, 'next_cursor' => $next];
	}

	#[\Override]
	public function messages(string $userId, string $token, int $afterId, int $limit, bool $wholeHistory = false): array {
		self::checkLimit($limit);
		if ($afterId < 0) {
			throw new ImportException(ImportException::CODE_INVALID, 'afterId is negative');
		}
		try {
			$room = $this->manager->getRoomForUserByToken($token, $userId);
			$participant = $this->participantService->getParticipant($room, $userId, false);
		} catch (RoomNotFoundException|ParticipantNotFoundException) {
			throw new ImportException(ImportException::CODE_NOT_FOUND, 'The person is not in this conversation');
		}
		if ($room->isFederatedConversation()) {
			throw new ImportException(ImportException::CODE_NOT_FOUND, 'The conversation is hosted on another server');
		}

		$notBefore = $wholeHistory ? null : $participant->getHistorySince();
		$reader = $participant;
		if ($wholeHistory && $participant->getHistorySince() !== null) {
			// The message parser hides what the participant may not see;
			// a whole-history read parses as the same person without it.
			$attendee = clone $participant->getAttendee();
			$attendee->setHistorySince(0);
			$reader = new Participant($room, $attendee, null);
		}
		$l = $this->l10nFor($userId);

		$messages = [];
		$batch = $limit;
		while (count($messages) < $limit) {
			$comments = $this->comments($room, $afterId, $notBefore, $batch);
			foreach ($comments as $comment) {
				$afterId = (int)$comment->getId();
				$message = $this->read($room, $reader, $comment, $l, $notBefore);
				if ($message !== null) {
					$messages[] = $message;
					if (count($messages) === $limit) {
						break;
					}
				}
			}
			if (count($comments) < $batch) {
				break;
			}
		}
		return $messages;
	}

	/**
	 * The message as the participant sees it, null when they don't see it
	 */
	private function read(Room $room, Participant $participant, IComment $comment, IL10N $l, ?\DateTimeImmutable $notBefore): ?ReadMessage {
		$message = $this->messageParser->createMessage($room, $participant, $comment, $l);
		$this->messageParser->parseMessage($message, true);
		if (!$message->getVisibility()) {
			return null;
		}

		$deleted = $message->getMessageType() === ChatManager::VERB_MESSAGE_DELETED;
		$system = $message->getMessageType() === ChatManager::VERB_SYSTEM;
		$metaData = $comment->getMetaData() ?? [];
		$edited = null;
		if (!$deleted && isset($metaData['last_edited_time']) && is_numeric($metaData['last_edited_time'])) {
			$edited = new \DateTimeImmutable('@' . (int)$metaData['last_edited_time']);
		}

		return new ReadMessage(
			(int)$comment->getId(),
			$message->getActorType(),
			$message->getActorId(),
			$message->getActorDisplayName(),
			\DateTimeImmutable::createFromInterface($comment->getCreationDateTime()),
			$deleted ? '' : $message->getMessage(),
			$deleted ? [] : self::parameters($message->getMessageParameters()),
			$deleted || $system ? 0 : $this->visibleParent($room, $comment, $notBefore),
			$deleted ? [] : self::reactions($comment->getReactions()),
			$system ? $message->getMessageRaw() : '',
			$edited,
			$deleted,
		);
	}

	/**
	 * The ID of the message a reply answers, when it is from $notBefore on
	 */
	private function visibleParent(Room $room, IComment $comment, ?\DateTimeImmutable $notBefore): int {
		$parentId = (int)$comment->getParentId();
		if ($parentId <= 0) {
			return 0;
		}
		try {
			$parent = $this->commentsManager->get((string)$parentId);
		} catch (NotFoundException) {
			return 0;
		}
		if ($parent->getObjectType() !== 'chat' || $parent->getObjectId() !== (string)$room->getId()) {
			return 0;
		}
		if ($notBefore !== null && $parent->getCreationDateTime()->getTimestamp() < $notBefore->getTimestamp()) {
			return 0;
		}
		return $parentId;
	}

	/**
	 * Comments shown as messages after $afterId by ID, not expired, from
	 * $notBefore on
	 *
	 * @return list<IComment>
	 */
	private function comments(Room $room, int $afterId, ?\DateTimeImmutable $notBefore, int $limit): array {
		$query = $this->messagesQuery($room, $notBefore);
		$query->select('*')
			->andWhere($query->expr()->gt('id', $query->createNamedParameter($afterId, IQueryBuilder::PARAM_INT)))
			->orderBy('id', 'ASC')
			->setMaxResults($limit);
		$result = $query->executeQuery();
		$comments = [];
		while ($row = $result->fetchAssociative()) {
			$comments[] = $this->commentsManager->getCommentFromData($row);
		}
		$result->closeCursor();
		return $comments;
	}

	private function countMessages(Room $room, ?\DateTimeImmutable $notBefore): int {
		$query = $this->messagesQuery($room, $notBefore);
		$query->select($query->func()->count('id'));
		$result = $query->executeQuery();
		$count = (int)$result->fetchOne();
		$result->closeCursor();
		return $count;
	}

	private function messagesQuery(Room $room, ?\DateTimeImmutable $notBefore): IQueryBuilder {
		$query = $this->db->getQueryBuilder();
		$query->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter((string)$room->getId())))
			->andWhere($query->expr()->in('verb', $query->createNamedParameter(self::VERBS, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($query->expr()->orX(
				$query->expr()->isNull('expire_date'),
				$query->expr()->gt('expire_date', $query->createNamedParameter($this->timeFactory->getDateTime(), IQueryBuilder::PARAM_DATETIME_MUTABLE), IQueryBuilder::PARAM_DATETIME_MUTABLE),
			));
		if ($notBefore !== null) {
			$query->andWhere($query->expr()->gte(
				'creation_timestamp',
				$query->createNamedParameter(\DateTime::createFromImmutable($notBefore), IQueryBuilder::PARAM_DATETIME_MUTABLE),
				IQueryBuilder::PARAM_DATETIME_MUTABLE,
			));
		}
		return $query;
	}

	/**
	 * Talk's texts in the person's own language, as they see them
	 */
	private function l10nFor(string $userId): IL10N {
		if (!isset($this->l10n[$userId])) {
			$user = $this->userManager->get($userId);
			$this->l10n[$userId] = $this->l10nFactory->get('spreed', $user === null ? null : $this->l10nFactory->getUserLanguage($user));
		}
		return $this->l10n[$userId];
	}

	/**
	 * Rich object parameters with their scalar values as strings
	 *
	 * @param array<array-key, mixed> $parameters
	 * @return array<string, array<string, string>>
	 */
	private static function parameters(array $parameters): array {
		$out = [];
		foreach ($parameters as $key => $parameter) {
			if (!is_array($parameter)) {
				continue;
			}
			$values = [];
			foreach ($parameter as $name => $value) {
				if (is_string($value) || is_int($value) || is_float($value)) {
					$values[(string)$name] = (string)$value;
				} elseif (is_bool($value)) {
					$values[(string)$name] = $value ? 'yes' : 'no';
				}
			}
			$out[(string)$key] = $values;
		}
		return $out;
	}

	/**
	 * @param array<array-key, mixed> $reactions
	 * @return array<string, int>
	 */
	private static function reactions(array $reactions): array {
		$out = [];
		foreach ($reactions as $emoji => $count) {
			if (is_numeric($count) && (int)$count > 0) {
				$out[(string)$emoji] = (int)$count;
			}
		}
		return $out;
	}

	/**
	 * @throws ImportException
	 */
	private static function checkLimit(int $limit): void {
		if ($limit < 1 || $limit > self::MAX_ENTRIES) {
			throw new ImportException(ImportException::CODE_INVALID, 'limit must be 1 to ' . self::MAX_ENTRIES);
		}
	}
}
