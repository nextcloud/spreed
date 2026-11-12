<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Collaboration\Reference;

use OCA\Talk\AppInfo\Application;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\ProxyCacheMessageMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Collaboration\Reference\ADiscoverableReferenceProvider;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\ISearchableReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\Comments\NotFoundException;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * @psalm-type ReferenceMatch = array{token: string, message: int|null}
 */
class TalkReferenceProvider extends ADiscoverableReferenceProvider implements ISearchableReferenceProvider {

	public function __construct(
		private readonly IURLGenerator $urlGenerator,
		private readonly Manager $roomManager,
		private readonly ParticipantService $participantService,
		private readonly ChatManager $chatManager,
		private readonly ProxyCacheMessageMapper $proxyCacheMessageMapper,
		private readonly AvatarService $avatarService,
		private readonly MessageParser $messageParser,
		private readonly IL10N $l,
		private readonly ?string $userId,
	) {
	}

	#[\Override]
	public function matchReference(string $referenceText): bool {
		return $this->getTalkAppLinkToken($referenceText) !== null;
	}

	/**
	 * @param string $referenceText
	 * @return array|null
	 * @psalm-return ReferenceMatch|null
	 */
	protected function getTalkAppLinkToken(string $referenceText): ?array {
		$indexPhpUrl = $this->urlGenerator->getAbsoluteURL('/index.php/call/');
		$rewriteUrl = $this->urlGenerator->getAbsoluteURL('/call/');

		if (str_starts_with($referenceText, $indexPhpUrl)) {
			$urlOfInterest = substr($referenceText, strlen($indexPhpUrl));
		} elseif (str_starts_with($referenceText, $rewriteUrl)) {
			$urlOfInterest = substr($referenceText, strlen($rewriteUrl));
		} else {
			return null;
		}

		$hashPosition = strpos($urlOfInterest, '#');
		$queryPosition = strpos($urlOfInterest, '?');

		if ($hashPosition === false && $queryPosition === false) {
			return [
				'token' => $urlOfInterest,
				'message' => null,
			];
		}

		if ($hashPosition !== false && $queryPosition !== false) {
			$cutPosition = min($hashPosition, $queryPosition);
		} elseif ($hashPosition !== false) {
			$cutPosition = $hashPosition;
		} else {
			$cutPosition = $queryPosition;
		}

		$token = substr($urlOfInterest, 0, $cutPosition);
		$messageId = null;
		if ($hashPosition !== false) {
			$afterHash = substr($urlOfInterest, $hashPosition + 1);
			if (preg_match('/^message_(\d+)$/', $afterHash, $matches)) {
				$messageId = (int)$matches[1];
			}
		}

		return [
			'token' => $token,
			'message' => $messageId,
		];
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function resolveReference(string $referenceText): ?IReference {
		if ($this->matchReference($referenceText)) {
			$reference = new Reference($referenceText);
			try {
				$this->fetchReference($reference);
			} catch (RoomNotFoundException|ParticipantNotFoundException) {
				$reference->setRichObject('call', null);
				$reference->setAccessible(false);
			}
			return $reference;
		}

		return null;
	}

	/**
	 * @throws RoomNotFoundException
	 */
	protected function fetchReference(Reference $reference): void {
		if ($this->userId === null) {
			throw new RoomNotFoundException();
		}

		$referenceMatch = $this->getTalkAppLinkToken($reference->getId());
		if ($referenceMatch === null) {
			throw new RoomNotFoundException();
		}

		$room = $this->roomManager->getRoomForUserByToken($referenceMatch['token'], $this->userId);
		try {
			$participant = $this->participantService->getParticipant($room, $this->userId);
		} catch (ParticipantNotFoundException) {
			$participant = null;
		}

		$isBlockedByLobby = $participant instanceof Participant
			&& $room->getLobbyState() !== Webinary::LOBBY_NONE
			&& !($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE);

		/**
		 * Default handling:
		 * Title is the conversation name
		 * Description the conversation description
		 */
		$roomName = $room->isClassified() ? $this->l->t('Private conversation') : $room->getDisplayName($this->userId);
		$title = $roomName;
		$description = '';
		$messageId = null;
		$messageData = [];

		// Same condition as the avatar endpoint, e.g. public conversations are found by token but not listed.
		// Classified conversations never expose their description or avatar.
		$canSeeDetails = !$room->isClassified()
			&& ($participant instanceof Participant
				|| $this->roomManager->isRoomListableByUser($room, $this->userId));

		/**
		 * If linking to a comment and the user is already a participant
		 * Title is "Message of {user} in {conversation}"
		 * Description is the plain text chat message
		 */
		if ($participant && !$isBlockedByLobby && !empty($referenceMatch['message'])) {
			$messageId = (string)$referenceMatch['message'];
			if (!$room->isFederatedConversation()) {
				try {
					$comment = $this->chatManager->getComment($room, $messageId);
				} catch (NotFoundException) {
					throw new RoomNotFoundException();
				}
			} else {
				try {
					$proxy = $this->proxyCacheMessageMapper->findById($room, (int)$messageId);
					if ($proxy->getLocalToken() !== $room->getToken()) {
						throw new RoomNotFoundException();
					}
				} catch (DoesNotExistException) {
					throw new RoomNotFoundException();
				}
			}
		}

		if (!empty($referenceMatch['message']) && ($messageId === null || $room->isClassified())) {
			// The message can not be seen, or must not leave the classified conversation.
			// Still render it as a message, so it is not mistaken for a conversation link.
			$title = $roomName = $this->l->t('Message in private conversation');
			$canSeeDetails = false;
			if ($messageId !== null) {
				$messageData = ['message-id' => $messageId];
			}
		} elseif ($messageId !== null) {
			if (isset($comment)) {
				$message = $this->messageParser->createMessage($room, $participant, $comment, $this->l);
				$this->messageParser->parseMessage($message, true);
				$timestamp = $comment->getCreationDateTime()->getTimestamp();
			} else {
				$message = $this->messageParser->createMessageFromProxyCache($room, $participant, $proxy, $this->l);
				$timestamp = $proxy->getCreationDatetime()?->getTimestamp() ?? 0;
			}

			$placeholders = $replacements = [];
			foreach ($message->getMessageParameters() as $placeholder => $parameter) {
				$placeholders[] = '{' . $placeholder . '}';
				if ($parameter['type'] === 'user' || $parameter['type'] === 'guest') {
					$replacements[] = '@' . $parameter['name'];
				} else {
					$replacements[] = $parameter['name'];
				}
			}
			$description = str_replace($placeholders, $replacements, $message->getMessage());

			$titleLine = $this->l->t('Message of {user} in {conversation}');
			if ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER) {
				$titleLine = $this->l->t('Message of {user}');
			}

			$displayName = $message->getActorDisplayName();
			if (in_array($message->getActorType(), [Attendee::ACTOR_GUESTS, Attendee::ACTOR_EMAILS], true)) {
				if ($displayName === '') {
					$displayName = $this->l->t('Guest');
				} else {
					$displayName = $this->l->t('%s (guest)', $displayName);
				}
			} elseif ($displayName === '') {
				$titleLine = $this->l->t('Message of a deleted user in {conversation}');
			} elseif ($message->getActorType() === Attendee::ACTOR_FEDERATED_USERS) {
				// Same as the chat: show the server of the cloud ID behind the display name
				$server = substr($message->getActorId(), strrpos($message->getActorId(), '@') + 1);
				$displayName .= ' (' . $server . ')';
			}

			$title = str_replace(
				['{user}', '{conversation}'],
				[$displayName, $title],
				$titleLine
			);

			$messageData = [
				'message-id' => $messageId,
				'actor-type' => $message->getActorType(),
				'actor-id' => $message->getActorId(),
				'actor-display-name' => $displayName,
				'timestamp' => (string)$timestamp,
			];
		} elseif ($canSeeDetails) {
			$description = $room->getDescription();
		}

		$reference->setTitle($title);
		$reference->setDescription($description);
		$reference->setUrl($this->urlGenerator->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]));

		$referenceData = [
			'id' => $room->getToken(),
			'name' => $roomName,
			'link' => $reference->getUrl(),
			'call-type' => $this->getRoomType($room),
			'description' => $description,
		];

		if ($canSeeDetails) {
			$reference->setImageUrl($this->avatarService->getAvatarUrl($room));
			$referenceData['avatar-version'] = $this->avatarService->getAvatarVersion($room);

			if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
				$referenceData['user-id'] = $room->getSecondParticipant($this->userId);
			}
		}

		$reference->setRichObject('call', array_merge($referenceData, $messageData));
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getCachePrefix(string $referenceId): string {
		$referenceMatch = $this->getTalkAppLinkToken($referenceId);
		if ($referenceMatch === null) {
			return '';
		}

		return $referenceMatch['token'];
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getCacheKey(string $referenceId): ?string {
		$referenceMatch = $this->getTalkAppLinkToken($referenceId);
		if ($referenceMatch === null) {
			return '';
		}

		return ($this->userId ?? '') . '#' . ($referenceMatch['message'] ?? 0);
	}

	protected function getRoomType(Room $room): string {
		return match ($room->getType()) {
			Room::TYPE_ONE_TO_ONE,
			Room::TYPE_ONE_TO_ONE_FORMER => 'one2one',
			Room::TYPE_GROUP => 'group',
			Room::TYPE_PUBLIC => 'public',
			default => 'unknown',
		};
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getId(): string {
		return Application::APP_ID;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getTitle(): string {
		return $this->l->t('Talk conversations');
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getOrder(): int {
		return 0;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getIconUrl(): string {
		return $this->urlGenerator->imagePath(Application::APP_ID, 'app-dark.svg');
	}

	#[\Override]
	public function getSupportedSearchProviderIds(): array {
		if ($this->userId === null) {
			return [];
		}
		return ['talk-conversations'];
	}
}
