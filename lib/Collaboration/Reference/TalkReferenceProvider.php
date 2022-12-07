<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace OCA\Talk\Collaboration\Reference;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\Collaboration\Reference\IReference;
use OCP\Collaboration\Reference\IReferenceProvider;
use OCP\Collaboration\Reference\Reference;
use OCP\IL10N;
use OCP\IURLGenerator;

/**
 * @psalm-type ReferenceMatch = array{token: string, message: int|null}
 */
class TalkReferenceProvider implements IReferenceProvider {
	protected IURLGenerator $urlGenerator;
	protected Manager $roomManager;
	protected ParticipantService $participantService;
	protected ChatManager $chatManager;
	protected AvatarService $avatarService;
	protected MessageParser $messageParser;
	protected IL10N $l;
	protected ?string $userId;

	public function __construct(IURLGenerator $urlGenerator,
								Manager $manager,
								ParticipantService $participantService,
								ChatManager $chatManager,
								AvatarService $avatarService,
								MessageParser $messageParser,
								IL10N $l,
								?string $userId) {
		$this->urlGenerator = $urlGenerator;
		$this->roomManager = $manager;
		$this->participantService = $participantService;
		$this->chatManager = $chatManager;
		$this->avatarService = $avatarService;
		$this->messageParser = $messageParser;
		$this->l = $l;
		$this->userId = $userId;
	}


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
				$messageId = (int) $matches[1];
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
			$comment = $this->chatManager->getComment($room, (string) $referenceMatch['message']);
			$message = $this->messageParser->createMessage($room, $participant, $comment, $this->l);
			$this->messageParser->parseMessage($message);

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
			if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
				$titleLine = $this->l->t('Message of {user}');
			}

			$displayName = $message->getActorDisplayName();
			if ($message->getActorType() === Attendee::ACTOR_GUESTS) {
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

		$reference->setRichObject('call', [
			'id' => $room->getToken(),
			'name' => $roomName,
			'link' => $reference->getUrl(),
			'call-type' => $this->getRoomType($room),
		]);
	}

	/**
	 * @inheritDoc
	 */
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
				return 'one2one';
			case Room::TYPE_GROUP:
				return 'group';
			case Room::TYPE_PUBLIC:
				return 'public';
			default:
				return 'unknown';
		}
	}
}
