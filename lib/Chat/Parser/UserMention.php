<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat\Parser;

use OCA\Circles\CirclesManager;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Events\MessageParseEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\App\IAppManager;
use OCP\Comments\ICommentsManager;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Federation\ICloudIdManager;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Server;

/**
 * Helper class to get a rich message from a plain text message.
 * @template-implements IEventListener<Event>
 */
class UserMention implements IEventListener {
	/** @var array<string, string> */
	protected array $circleNames = [];
	/** @var array<string, string> */
	protected array $circleLinks = [];

	public function __construct(
		protected IAppManager $appManager,
		protected ICommentsManager $commentsManager,
		protected IUserManager $userManager,
		protected IGroupManager $groupManager,
		protected GuestManager $guestManager,
		protected AvatarService $avatarService,
		protected ICloudIdManager $cloudIdManager,
		protected ParticipantService $participantService,
		protected IL10N $l,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof MessageParseEvent) {
			return;
		}

		$message = $event->getMessage();
		if ($message->getMessageType() !== ChatManager::VERB_MESSAGE) {
			return;
		}

		$this->parseMessage($message);
	}

	/**
	 * Returns the equivalent rich message to the given comment.
	 *
	 * The mentions in the comment are replaced by "{mention-$type$index}" in
	 * the returned rich message; each "mention-$type$index" parameter contains
	 * the following attributes:
	 *   -type: the type of the mention ("user")
	 *   -id: the ID of the user
	 *   -name: the display name of the user, or an empty string if it could
	 *     not be resolved.
	 *
	 * @param Message $chatMessage
	 */
	protected function parseMessage(Message $chatMessage): void {
		$comment = $chatMessage->getComment();
		$message = $chatMessage->getMessage();
		$messageParameters = $chatMessage->getMessageParameters();

		$mentionTypeCount = [];

		// Set the current message as comment content, so that the message finds
		// mentions which are now part of the message, but were not on the original
		// comment, e.g. mentions at the beginning of captions
		$originalCommentMessage = $comment->getMessage();
		$comment->setMessage($message, ChatManager::MAX_CHAT_LENGTH + 10000);
		$mentions = $comment->getMentions();
		$comment->setMessage($originalCommentMessage, ChatManager::MAX_CHAT_LENGTH);

		// TODO This can be removed once getMentions() returns sorted results (Nextcloud 21+)
		usort($mentions, static function (array $m1, array $m2) {
			return mb_strlen($m2['id']) <=> mb_strlen($m1['id']);
		});

		$metadata = $comment->getMetaData() ?? [];
		foreach ($mentions as $mention) {
			if ($mention['type'] === 'user' && $mention['id'] === 'all') {
				if (!isset($metadata[Message::METADATA_CAN_MENTION_ALL])) {
					continue;
				}

				$mention['type'] = 'call';
			}

			if ($mention['type'] === 'user') {
				$userDisplayName = $this->userManager->getDisplayName($mention['id']);
				if ($userDisplayName === null) {
					continue;
				}
			}

			if (!array_key_exists($mention['type'], $mentionTypeCount)) {
				$mentionTypeCount[$mention['type']] = 0;
			}
			$mentionTypeCount[$mention['type']]++;

			$search = $mention['id'];
			if (
				$mention['type'] === 'email'
				|| $mention['type'] === 'group'
				// || $mention['type'] === 'federated_group'
				 || $mention['type'] === 'team'
				// || $mention['type'] === 'federated_team'
				|| $mention['type'] === 'federated_user') {
				$search = $mention['type'] . '/' . $mention['id'];
			}

			// To keep a limited character set in parameter IDs ([a-zA-Z0-9-])
			// the mention parameter ID does not include the mention ID (which
			// could contain characters like '@' for user IDs) but a one-based
			// index of the mentions of that type.
			$mentionParameterId = 'mention-' . str_replace('_', '-', $mention['type']) . $mentionTypeCount[$mention['type']];

			$message = str_replace('@"' . $search . '"', '{' . $mentionParameterId . '}', $message);
			if (!str_contains($search, ' ')
				&& !str_starts_with($search, 'guest/')
				&& !str_starts_with($search, 'email/')
				&& !str_starts_with($search, 'group/')
				// && !str_starts_with($search, 'federated_group/')
				 && !str_starts_with($search, 'team/')
				// && !str_starts_with($search, 'federated_team/')
				&& !str_starts_with($search, 'federated_user/')) {
				$message = str_replace('@' . $search, '{' . $mentionParameterId . '}', $message);
			}

			if ($mention['type'] === 'call') {
				$userId = '';
				if ($chatMessage->getParticipant()?->getAttendee()->getActorType() === Attendee::ACTOR_USERS) {
					$userId = $chatMessage->getParticipant()->getAttendee()->getActorId();
				}

				$messageParameters[$mentionParameterId] = [
					'type' => $mention['type'],
					'id' => $chatMessage->getRoom()->getToken(),
					'name' => $chatMessage->getRoom()->getDisplayName($userId, true),
					'call-type' => $this->getRoomType($chatMessage->getRoom()),
					'icon-url' => $this->avatarService->getAvatarUrl($chatMessage->getRoom()),
					'mention-id' => $search,
				];
			} elseif ($mention['type'] === 'guest') {
				try {
					$participant = $this->participantService->getParticipantByActor($chatMessage->getRoom(), Attendee::ACTOR_GUESTS, substr($mention['id'], strlen('guest/')));
					$displayName = $participant->getAttendee()->getDisplayName() ?: $this->l->t('Guest');
				} catch (ParticipantNotFoundException $e) {
					$displayName = $this->l->t('Guest');
				}

				$messageParameters[$mentionParameterId] = [
					'type' => $mention['type'],
					'id' => $mention['id'],
					'name' => $displayName,
					'mention-id' => $search,
				];
			} elseif ($mention['type'] === 'email') {
				try {
					$participant = $this->participantService->getParticipantByActor($chatMessage->getRoom(), Attendee::ACTOR_EMAILS, $mention['id']);
					$displayName = $participant->getAttendee()->getDisplayName() ?: $this->l->t('Guest');
				} catch (ParticipantNotFoundException) {
					$displayName = $this->l->t('Guest');
				}

				$messageParameters[$mentionParameterId] = [
					'type' => $mention['type'],
					'id' => $mention['id'],
					'name' => $displayName,
					'mention-id' => $search,
				];
			} elseif ($mention['type'] === 'federated_user') {
				try {
					$cloudId = $this->cloudIdManager->resolveCloudId($mention['id']);
				} catch (\Throwable) {
					continue;
				}

				try {
					$participant = $this->participantService->getParticipantByActor($chatMessage->getRoom(), Attendee::ACTOR_FEDERATED_USERS, $mention['id']);
					$displayName = $participant->getAttendee()->getDisplayName() ?: $cloudId->getDisplayId();
				} catch (ParticipantNotFoundException) {
					$displayName = $mention['id'];
				}

				$messageParameters[$mentionParameterId] = [
					'type' => 'user',
					'id' => $cloudId->getUser(),
					'name' => $displayName,
					'server' => $cloudId->getRemote(),
					'mention-id' => $search,
				];
			} elseif ($mention['type'] === 'group') {
				$group = $this->groupManager->get($mention['id']);
				if ($group instanceof IGroup) {
					$displayName = $group->getDisplayName();
				} else {
					$displayName = $mention['id'];
				}

				$messageParameters[$mentionParameterId] = [
					'type' => 'user-group',
					'id' => $mention['id'],
					'name' => $displayName,
					'mention-id' => $search,
				];
			} elseif ($mention['type'] === 'team') {
				$messageParameters[$mentionParameterId] = $this->getCircle($mention['id']);
			} else {
				try {
					$displayName = $this->commentsManager->resolveDisplayName($mention['type'], $mention['id']);
				} catch (\OutOfBoundsException $e) {
					// There is no registered display name resolver for the mention
					// type, so the client decides what to display.
					$displayName = '';
				}

				$messageParameters[$mentionParameterId] = [
					'type' => $mention['type'],
					'id' => $mention['id'],
					'name' => $displayName,
					'mention-id' => $search,
				];
			}
		}

		if (str_starts_with($message, '//')) {
			$message = substr($message, 1);
		}

		$chatMessage->setMessage($message, $messageParameters);
	}

	/**
	 * @param Room $room
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function getRoomType(Room $room): string {
		switch ($room->getType()) {
			case Room::TYPE_ONE_TO_ONE:
			case Room::TYPE_ONE_TO_ONE_FORMER:
			case Room::TYPE_NOTE_TO_SELF:
				return 'one2one';
			case Room::TYPE_GROUP:
				return 'group';
			case Room::TYPE_PUBLIC:
				return 'public';
			default:
				throw new \InvalidArgumentException('Unknown room type');
		}
	}

	protected function getCircle(string $circleId): array {
		if (!$this->appManager->isEnabledForUser('circles')) {
			return [
				'type' => 'highlight',
				'id' => $circleId,
				'name' => $circleId,
			];
		}

		if (!isset($this->circleNames[$circleId])) {
			$this->loadCircleDetails($circleId);
		}

		if (!isset($this->circleNames[$circleId])) {
			return [
				'type' => 'highlight',
				'id' => $circleId,
				'name' => $circleId,
			];
		}

		return [
			'type' => 'circle',
			'id' => $circleId,
			'name' => $this->circleNames[$circleId],
			'link' => $this->circleLinks[$circleId],
			'mention-id' => 'team/' . $circleId,
		];
	}

	protected function loadCircleDetails(string $circleId): void {
		try {
			$circlesManager = Server::get(CirclesManager::class);
			$circlesManager->startSuperSession();
			$circle = $circlesManager->getCircle($circleId);

			$this->circleNames[$circleId] = $circle->getDisplayName();
			$this->circleLinks[$circleId] = $circle->getUrl();
		} catch (\Exception) {
		} finally {
			$circlesManager?->stopSession();
		}
	}
}
