<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Thread;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Webinary;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Comments\NotFoundException;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\IUser;

/**
 * Files posted in a team conversation are stored in the attachment folder of the
 * team folder (see ConversationFolderService). The chat about such a file in
 * the Files sidebar is a thread on its message in the team conversation,
 * instead of a separate conversation for the file.
 *
 * Only for participants of the team conversation (the direct members of the
 * team): everyone else with access to the file keeps the conversation of the
 * file.
 *
 * Not used by the Files sidebar yet: clients first need to know that the
 * answer can be a thread of the team conversation (capability), otherwise
 * they would open the whole team conversation instead of the file.
 */
class TeamFileThreadService {
	public function __construct(
		protected readonly CirclesAdapter $circlesAdapter,
		protected readonly Manager $manager,
		protected readonly ParticipantService $participantService,
		protected readonly ConversationFolderService $conversationFolderService,
		protected readonly ChatManager $chatManager,
		protected readonly ThreadService $threadService,
		protected readonly ITimeFactory $timeFactory,
		protected readonly IDBConnection $db,
	) {
	}

	/**
	 * @return array{room: Room, threadId: int}|null Null when the file was not posted in a team conversation of the user
	 */
	public function getThreadForFile(IUser $user, Node $node): ?array {
		// Cheap checks first, this runs for every file opened in the Files
		// sidebar: no team conversations without Circles, and attachments of
		// team conversations are always in their attachment folder
		if (!$this->circlesAdapter->isAvailable()
			|| !str_contains($node->getPath(), '/' . ConversationFolderService::TEAM_ATTACHMENT_FOLDER . '/')) {
			return null;
		}

		$room = $this->getTeamRoomOfAttachment($user, $node);
		if (is_null($room)) {
			return null;
		}

		$comment = $this->findMessageOfFile($room, $node->getId(), $node);
		if (is_null($comment)) {
			// In the folder, but not posted (e.g. uploaded in Files directly)
			return null;
		}

		$topmostParentId = (int)$comment->getTopmostParentId();
		if ($topmostParentId !== 0) {
			// Posted in a thread already: the chat about the file is that thread
			return ['room' => $room, 'threadId' => $topmostParentId];
		}

		$threadId = (int)$comment->getId();
		try {
			$this->threadService->findByThreadId($room->getId(), $threadId);
		} catch (DoesNotExistException) {
			if ($room->getReadOnly() === Room::READ_ONLY) {
				// Locked (e.g. the team was deleted): nothing is started there anymore
				return null;
			}
			try {
				$participant = $this->participantService->getParticipant($room, $user->getUID(), false);
			} catch (ParticipantNotFoundException) {
				return null;
			}
			if (!$this->canPostInChat($room, $participant)) {
				// Same rules as posting in the chat: no thread is started on their behalf
				return null;
			}
			$this->startThread($room, $participant, $comment, $node->getName());
		}

		return ['room' => $room, 'threadId' => $threadId];
	}

	/**
	 * The team conversation of the user whose team folder contains the file
	 * in its attachment folder
	 */
	protected function getTeamRoomOfAttachment(IUser $user, Node $node): ?Room {
		$path = $node->getPath();
		foreach ($this->manager->getRoomsForUser($user->getUID()) as $room) {
			if (!$room->isTeamManaged()) {
				continue;
			}

			$teamFolder = $this->conversationFolderService->getTeamFolder($user->getUID(), $room->getObjectId());
			if (!is_null($teamFolder)
				&& str_starts_with($path, $teamFolder->getPath() . '/' . ConversationFolderService::TEAM_ATTACHMENT_FOLDER . '/')) {
				return $room;
			}
		}
		return null;
	}

	/**
	 * The message that posted the file (attachment endpoint with 'fileId' and 'teamFolder')
	 */
	protected function findMessageOfFile(Room $room, int $fileId, ?Node $node = null): ?IComment {
		// Remembered when posting: no search in the chat
		$messageId = !is_null($node) ? $this->conversationFolderService->getTeamAttachmentMessageId($node) : null;
		if (!is_null($messageId)) {
			try {
				$comment = $this->chatManager->getComment($room, (string)$messageId);
				$data = json_decode($comment->getMessage(), true);
				if ($comment->getVerb() === ChatManager::VERB_OBJECT_SHARED
					&& is_array($data)
					&& (string)($data['parameters']['fileId'] ?? '') === (string)$fileId) {
					return $comment;
				}
			} catch (NotFoundException) {
				// Deleted message, or another conversation: search below
			}
		}

		// Posted before the message was remembered
		$query = $this->db->getQueryBuilder();
		$query->select('id')
			->from('comments')
			->where($query->expr()->eq('object_type', $query->createNamedParameter('chat')))
			->andWhere($query->expr()->eq('object_id', $query->createNamedParameter((string)$room->getId())))
			->andWhere($query->expr()->eq('verb', $query->createNamedParameter(ChatManager::VERB_OBJECT_SHARED)))
			->andWhere($query->expr()->like('message', $query->createNamedParameter('%' . $this->db->escapeLikeParameter('"fileId":"' . $fileId . '"') . '%')))
			->andWhere($query->expr()->like('message', $query->createNamedParameter('%' . $this->db->escapeLikeParameter('"teamFolder":true') . '%')))
			->orderBy('id', 'ASC')
			->setMaxResults(1);

		$result = $query->executeQuery();
		$messageId = $result->fetchOne();
		$result->closeCursor();
		if ($messageId === false) {
			return null;
		}

		try {
			return $this->chatManager->getComment($room, (string)$messageId);
		} catch (NotFoundException) {
			return null;
		}
	}

	/**
	 * Chat permission, and not waiting in the lobby
	 */
	protected function canPostInChat(Room $room, Participant $participant): bool {
		if (($participant->getPermissions() & Attendee::PERMISSIONS_CHAT) === 0) {
			return false;
		}
		return $room->getLobbyState() === Webinary::LOBBY_NONE
			|| $participant->hasModeratorPermissions();
	}

	/**
	 * Same as starting a thread on a message in the chat, with the file name as title
	 */
	protected function startThread(Room $room, Participant $participant, IComment $comment, string $fileName): Thread {
		$thread = $this->threadService->createThread($room, (int)$comment->getId(), $fileName);

		$this->threadService->setNotificationLevel($participant->getAttendee(), $thread->getId(), Participant::NOTIFY_DEFAULT);

		$this->chatManager->addSystemMessage(
			$room,
			$participant,
			$participant->getAttendee()->getActorType(),
			$participant->getAttendee()->getActorId(),
			json_encode(['message' => 'thread_created', 'parameters' => ['thread' => (int)$comment->getId(), 'title' => $thread->getName()]], JSON_THROW_ON_ERROR),
			$this->timeFactory->getDateTime(),
			false,
			null,
			$comment,
			true,
			true,
		);

		return $thread;
	}
}
