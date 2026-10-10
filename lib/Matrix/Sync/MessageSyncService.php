<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Sync;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\ReactionAlreadyExistsException;
use OCA\Talk\Matrix\Client\Html\HtmlToMarkdown;
use OCA\Talk\Matrix\Client\Model\Event;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\EventMap;
use OCA\Talk\Matrix\Model\EventMapMapper;
use OCA\Talk\Matrix\Model\MatrixMember;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\ICommentsManager;
use OCP\Comments\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;
use Psr\Log\LoggerInterface;

/**
 * Mirrors the timeline events of a Matrix room into the chat: messages,
 * replies, edits, reactions and redactions
 */
class MessageSyncService {
	public function __construct(
		private readonly ChatManager $chatManager,
		private readonly ReactionManager $reactionManager,
		private readonly ICommentsManager $commentsManager,
		private readonly ParticipantService $participantService,
		private readonly EventMapMapper $eventMapMapper,
		private readonly ITimeFactory $timeFactory,
		private readonly IURLGenerator $urlGenerator,
		private readonly IL10N $l,
		private readonly LoggerInterface $logger,
	) {
	}

	/**
	 * @param list<Event> $timeline
	 * @param array<string, MatrixMember> $members indexed by Matrix user id
	 * @param array<string, Account> $accounts Linked accounts of the members, indexed by Matrix user id
	 * @param bool $silent Whether the messages are history that should not notify anyone
	 * @return int Number of new messages
	 */
	public function apply(Room $room, MatrixRoom $matrixRoom, array $timeline, array $members, array $accounts, bool $silent): int {
		$messages = 0;
		foreach ($timeline as $event) {
			if (!in_array($event->type, ['m.room.message', 'm.sticker', 'm.room.encrypted', 'm.reaction', 'm.room.redaction'], true)) {
				continue;
			}

			$eventMap = new EventMap();
			$eventMap->setMatrixRoomId((string)$matrixRoom->getId());
			$eventMap->setEventId($event->eventId);
			$eventMap->setEventType($event->type);
			$eventMap->setSender($event->sender);
			if (!$this->eventMapMapper->insertIfNew($eventMap)) {
				continue;
			}

			[$actorType, $actorId] = isset($accounts[$event->sender])
				? [Attendee::ACTOR_USERS, $accounts[$event->sender]->getUserId()]
				: [Attendee::ACTOR_MATRIX, $event->sender];
			try {
				$comment = match (true) {
					$event->type === 'm.reaction' => $this->applyReaction($room, $matrixRoom, $event, $actorType, $actorId),
					$event->type === 'm.room.redaction' => $this->applyRedaction($room, $matrixRoom, $event, $actorType, $actorId),
					$event->getRelationType() === 'm.replace' => $this->applyEdit($room, $matrixRoom, $event, $actorType, $actorId),
					default => $this->postMessage($room, $matrixRoom, $eventMap, $event, $members, $accounts, $actorType, $actorId, $silent),
				};
			} catch (\Throwable $e) {
				$this->logger->warning('Matrix event ' . $event->eventId . ' could not be mirrored', ['exception' => $e]);
				continue;
			}

			if ($comment !== null) {
				$eventMap->setCommentId((int)$comment->getId());
				$this->eventMapMapper->update($eventMap);
				if (in_array($comment->getVerb(), [ChatManager::VERB_MESSAGE, ChatManager::VERB_OBJECT_SHARED], true)) {
					$messages++;
				}
			}
		}
		return $messages;
	}

	/**
	 * @param array<string, MatrixMember> $members
	 * @param array<string, Account> $accounts
	 */
	protected function postMessage(Room $room, MatrixRoom $matrixRoom, EventMap $eventMap, Event $event, array $members, array $accounts, string $actorType, string $actorId, bool $silent): ?IComment {
		$replyTo = null;
		$parentEventId = $event->getInReplyTo();
		if ($parentEventId !== null) {
			$replyTo = $this->getComment($this->getEventMap($matrixRoom, $parentEventId));
		}

		$object = $this->getSharedObject($eventMap, $event);
		if ($object !== null) {
			return $this->chatManager->addSystemMessage(
				$room,
				null,
				$actorType,
				$actorId,
				json_encode(['message' => 'object_shared', 'parameters' => ['objectType' => $object['type'], 'objectId' => $object['id'], 'metaData' => $object]], JSON_THROW_ON_ERROR),
				$this->getDateTime($event),
				!$silent,
				replyTo: $replyTo,
				silent: $silent,
			);
		}

		if ($event->type === 'm.room.encrypted') {
			$message = $this->l->t('Encrypted message, end-to-end encrypted Matrix rooms are not supported yet');
		} else {
			$message = $this->convertMessage($event, $members, $accounts);
		}
		if (trim($message) === '') {
			return null;
		}
		if (mb_strlen($message) > ChatManager::MAX_CHAT_LENGTH) {
			$message = mb_substr($message, 0, ChatManager::MAX_CHAT_LENGTH - 1) . '…';
		}

		return $this->chatManager->sendMessage(
			$room,
			null,
			$actorType,
			$actorId,
			$message,
			$this->getDateTime($event),
			$replyTo,
			silent: $silent,
			rateLimitGuestMentions: false,
		);
	}

	/**
	 * Attachments become a link to download them through the homeserver of
	 * the viewer (images also a thumbnail), locations a location object
	 *
	 * @return array{type: string, id: string, name: string, link?: string, latitude?: string, longitude?: string, mxc?: string, thumb?: string}|null
	 */
	protected function getSharedObject(EventMap $eventMap, Event $event): ?array {
		$msgtype = $event->type === 'm.sticker' ? 'm.image' : ($event->content['msgtype'] ?? null);
		$name = trim($event->getBody());

		if ($msgtype === 'm.location') {
			$geoUri = is_string($event->content['geo_uri'] ?? null) ? $event->content['geo_uri'] : '';
			if (!preg_match(ChatManager::GEO_LOCATION_VALIDATOR, $geoUri) || !preg_match('/^geo:(-?[\d.]+),(-?[\d.]+)/i', $geoUri, $matches)) {
				return null;
			}
			return [
				'type' => 'geo-location',
				'id' => $geoUri,
				'name' => $name !== '' ? mb_substr($name, 0, 255) : $this->l->t('Location'),
				'latitude' => $matches[1],
				'longitude' => $matches[2],
			];
		}

		$mxc = is_string($event->content['url'] ?? null) ? $event->content['url'] : '';
		if (!in_array($msgtype, ['m.image', 'm.file', 'm.video', 'm.audio'], true) || !str_starts_with($mxc, 'mxc://')) {
			return null;
		}
		$object = [
			'type' => 'highlight',
			'id' => 'matrix-media/' . $eventMap->getId(),
			'name' => $name !== '' ? mb_substr($name, 0, 255) : $this->l->t('Attachment'),
			'link' => $this->urlGenerator->linkToRouteAbsolute('spreed.MatrixMedia.download', ['id' => (string)$eventMap->getId()]),
			'mxc' => $mxc,
		];
		if ($msgtype === 'm.image') {
			$object['thumb'] = $this->urlGenerator->linkToRouteAbsolute('spreed.MatrixMedia.preview', ['id' => (string)$eventMap->getId()]);
		}
		return $object;
	}

	protected function applyEdit(Room $room, MatrixRoom $matrixRoom, Event $event, string $actorType, string $actorId): ?IComment {
		$target = $this->getEventMap($matrixRoom, $event->getRelatedEventId());
		$newContent = is_array($event->content['m.new_content'] ?? null) ? $event->content['m.new_content'] : null;
		if ($target === null || $newContent === null || $target->getSender() !== $event->sender) {
			// Only the sender can edit their messages
			return null;
		}

		$comment = $this->getComment($target);
		$participant = $this->getParticipant($room, $actorType, $actorId);
		if ($comment === null || $participant === null || $comment->getVerb() !== ChatManager::VERB_MESSAGE) {
			return null;
		}

		$replacement = new Event($event->eventId, 'm.room.message', $event->sender, $newContent);
		$message = $this->convertMessage($replacement, [], []);
		if (trim($message) === '') {
			return null;
		}
		return $this->chatManager->editMessage($room, $comment, $participant, $this->getDateTime($event), mb_substr($message, 0, ChatManager::MAX_CHAT_LENGTH));
	}

	protected function applyReaction(Room $room, MatrixRoom $matrixRoom, Event $event, string $actorType, string $actorId): ?IComment {
		$relation = $event->getRelation();
		$reaction = is_string($relation['key'] ?? null) ? $relation['key'] : '';
		$target = $this->getEventMap($matrixRoom, $event->getRelatedEventId());
		if ($reaction === '' || ($relation['rel_type'] ?? null) !== 'm.annotation' || $target?->getCommentId() === null) {
			return null;
		}

		try {
			return $this->reactionManager->addReactionMessage($room, $actorType, $actorId, $this->getDisplayName($room, $actorType, $actorId), $target->getCommentId(), $reaction);
		} catch (ReactionAlreadyExistsException) {
			return null;
		}
	}

	protected function applyRedaction(Room $room, MatrixRoom $matrixRoom, Event $event, string $actorType, string $actorId): ?IComment {
		$target = $this->getEventMap($matrixRoom, $event->redacts);
		if ($target === null) {
			return null;
		}
		if ($target->getSender() !== $event->sender && !$matrixRoom->getPowerLevelsModel()->canRedact($event->sender)) {
			// The homeserver also delivers redactions of other users' events that clients must not apply without the redact power level
			return null;
		}
		$comment = $this->getComment($target);
		if ($comment === null) {
			return null;
		}

		if ($comment->getVerb() === ChatManager::VERB_REACTION) {
			$this->reactionManager->deleteReactionMessage(
				$room,
				$comment->getActorType(),
				$comment->getActorId(),
				$this->getDisplayName($room, $comment->getActorType(), $comment->getActorId()),
				(int)$comment->getParentId(),
				$comment->getMessage(),
			);
			return null;
		}

		$participant = $this->getParticipant($room, $actorType, $actorId);
		if ($participant === null || !in_array($comment->getVerb(), [ChatManager::VERB_MESSAGE, ChatManager::VERB_OBJECT_SHARED], true)) {
			return null;
		}
		return $this->chatManager->deleteMessage($room, $comment, $participant, $this->getDateTime($event));
	}

	/**
	 * Matrix text to Markdown, pills of linked users become mentions
	 *
	 * @param array<string, MatrixMember> $members
	 * @param array<string, Account> $accounts
	 */
	protected function convertMessage(Event $event, array $members, array $accounts): string {
		$html = $event->getFormattedBody();
		$message = '';
		if ($html !== null) {
			$converter = new HtmlToMarkdown();
			$converter->setPillResolver(static fn (string $mxid): ?string => isset($accounts[$mxid]) ? '@"' . $accounts[$mxid]->getUserId() . '"' : null);
			$message = $converter->convert($html);
		}
		if ($message === '') {
			$message = self::stripReplyFallback($event->getBody());
		}

		if (($event->content['msgtype'] ?? null) === 'm.emote') {
			$name = trim((string)($members[$event->sender] ?? null)?->getDisplayName());
			$message = '* ' . ($name !== '' ? $name : $event->sender) . ' ' . $message;
		}

		if (($event->content['m.mentions']['room'] ?? false) === true) {
			$message = str_replace('@room', '@all', $message);
		}
		return $message;
	}

	/**
	 * Remove the quote of the replied message that older clients add to the body
	 */
	protected static function stripReplyFallback(string $body): string {
		if (!str_starts_with($body, '> ')) {
			return $body;
		}
		$lines = explode("\n", $body);
		$quoted = 0;
		while ($quoted < count($lines) && str_starts_with($lines[$quoted], '> ')) {
			$quoted++;
		}
		if ($quoted < count($lines) && trim($lines[$quoted]) === '') {
			return implode("\n", array_slice($lines, $quoted + 1));
		}
		return $body;
	}

	protected function getEventMap(MatrixRoom $matrixRoom, ?string $eventId): ?EventMap {
		return $eventId !== null ? $this->eventMapMapper->findByEventId((string)$matrixRoom->getId(), $eventId) : null;
	}

	protected function getComment(?EventMap $eventMap): ?IComment {
		$commentId = $eventMap?->getCommentId();
		if ($commentId === null) {
			return null;
		}
		try {
			return $this->commentsManager->get((string)$commentId);
		} catch (NotFoundException) {
			return null;
		}
	}

	protected function getParticipant(Room $room, string $actorType, string $actorId): ?Participant {
		try {
			return $this->participantService->getParticipantByActor($room, $actorType, $actorId);
		} catch (ParticipantNotFoundException) {
			return null;
		}
	}

	protected function getDisplayName(Room $room, string $actorType, string $actorId): string {
		$participant = $this->getParticipant($room, $actorType, $actorId);
		return $participant !== null && $participant->getAttendee()->getDisplayName() !== '' ? $participant->getAttendee()->getDisplayName() : $actorId;
	}

	protected function getDateTime(Event $event): \DateTime {
		return $this->timeFactory->getDateTime('@' . intdiv(max(0, $event->originServerTs), 1000));
	}
}
