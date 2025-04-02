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
		protected IURLGenerator $urlGenerator,
		protected Manager $roomManager,
		protected ParticipantService $participantService,
		protected ChatManager $chatManager,
		protected ProxyCacheMessageMapper $proxyCacheMessageMapper,
		protected AvatarService $avatarService,
		protected MessageParser $messageParser,
		protected IL10N $l,
		protected ?string $userId,
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
			} catch (RoomNotFoundException|ParticipantNotFoundException $e) {
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
		} catch (ParticipantNotFoundException $e) {
			$participant = null;
		}

		/**
		 * Default handling:
		 * Title is the conversation name
		 * Description the conversation description
		 */
		$roomName = $room->getDisplayName($this->userId);
		$title = $roomName;
		$description = '';
		$messageId = null;

		if ($participant instanceof Participant
			|| $this->roomManager->isRoomListableByUser($room, $this->userId)) {
			$description = $room->getDescription();
		}


		/**
		 * If linking to a comment and the user is already a participant
		 * Title is "Message of {user} in {conversation}"
		 * Description is the plain text chat message
		 */
		if ($participant && !empty($referenceMatch['message'])) {
			$messageId = (string)$referenceMatch['message'];
			if (!$room->isFederatedConversation()) {
				try {
					$comment = $this->chatManager->getComment($room, $messageId);
				} catch (NotFoundException) {
					throw new RoomNotFoundException();
				}
				$message = $this->messageParser->createMessage($room, $participant, $comment, $this->l);
				$this->messageParser->parseMessage($message, true);
			} else {
				try {
					$proxy = $this->proxyCacheMessageMapper->findById($room, (int)$messageId);
					if ($proxy->getLocalToken() !== $room->getToken()) {
						throw new RoomNotFoundException();
					}
				} catch (DoesNotExistException) {
					throw new RoomNotFoundException();
				}
				$message = $this->messageParser->createMessageFromProxyCache($room, $participant, $proxy, $this->l);
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
			}

			$title = str_replace(
				['{user}', '{conversation}'],
				[$displayName, $title],
				$titleLine
			);
		}

		$reference->setTitle($title);
		$reference->setDescription($description);
		$reference->setUrl($this->urlGenerator->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]));
		$reference->setImageUrl($this->avatarService->getAvatarUrl($room));

		$referenceData = [
			'id' => $room->getToken(),
			'name' => $roomName,
			'link' => $reference->getUrl(),
			'call-type' => $this->getRoomType($room),
		];

		if ($messageId) {
			$referenceData['message-id'] = $messageId;
		}

		$reference->setRichObject('call', $referenceData);
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
		switch ($room->getType()) {
			case Room::TYPE_ONE_TO_ONE:
			case Room::TYPE_ONE_TO_ONE_FORMER:
				return 'one2one';
			case Room::TYPE_GROUP:
				return 'group';
			case Room::TYPE_PUBLIC:
				return 'public';
			default:
				return 'unknown';
		}
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
		return ['talk-conversations'];
	}
}
