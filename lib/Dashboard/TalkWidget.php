<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Dashboard;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Config;
use OCA\Talk\Events\BeforeRoomsFetchEvent;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\Model\Message;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\ProxyCacheMessageService;
use OCA\Talk\Webinary;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IConditionalWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IOptionWidget;
use OCP\Dashboard\IReloadableWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetItems;
use OCP\Dashboard\Model\WidgetOptions;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;

class TalkWidget implements IAPIWidget, IIconWidget, IButtonWidget, IOptionWidget, IConditionalWidget, IReloadableWidget {

	public function __construct(
		protected IUserSession $userSession,
		protected Config $talkConfig,
		protected IURLGenerator $url,
		protected IL10N $l10n,
		protected Manager $manager,
		protected AvatarService $avatarService,
		protected ParticipantService $participantService,
		protected MessageParser $messageParser,
		protected ChatManager $chatManager,
		protected ProxyCacheMessageService $pcmService,
		protected IEventDispatcher $dispatcher,
		protected ITimeFactory $timeFactory,
	) {
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getId(): string {
		return 'spreed';
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getTitle(): string {
		return $this->l10n->t('Talk mentions');
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getIconClass(): string {
		return 'dashboard-talk-icon';
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function isEnabled(): bool {
		$user = $this->userSession->getUser();
		return !($user instanceof IUser && $this->talkConfig->isDisabledForUser($user));
	}

	#[\Override]
	public function getWidgetOptions(): WidgetOptions {
		return new WidgetOptions(true);
	}

	/**
	 * @return list<WidgetButton>
	 */
	#[\Override]
	public function getWidgetButtons(string $userId): array {
		$buttons = [];
		$buttons[] = new WidgetButton(
			WidgetButton::TYPE_MORE,
			$this->url->linkToRouteAbsolute('spreed.Page.index'),
			$this->l10n->t('More conversations')
		);
		return $buttons;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getIconUrl(): string {
		return $this->url->getAbsoluteURL($this->url->imagePath('spreed', 'app-dark.svg'));
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getUrl(): ?string {
		return $this->url->linkToRouteAbsolute('spreed.Page.index');
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function load(): void {
		Util::addStyle('spreed', 'talk-icons');
	}

	#[\Override]
	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		$event = new BeforeRoomsFetchEvent($userId);
		$this->dispatcher->dispatchTyped($event);

		$rooms = $this->manager->getRoomsForUser($userId, [], true);

		$rooms = array_filter($rooms, function (Room $room) use ($userId) {
			if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
				return false;
			}

			$participant = $this->participantService->getParticipant($room, $userId);
			$attendee = $participant->getAttendee();

			if ($room->getLobbyState() !== Webinary::LOBBY_NONE
				&& !($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE)) {
				return false;
			}

			if (!$attendee->isArchived() && $room->getCallFlag() !== Participant::FLAG_DISCONNECTED) {
				return true;
			}

			if (($room->isFederatedConversation() && $attendee->getLastMentionMessage())
				|| (!$room->isFederatedConversation() && $attendee->getLastMentionMessage() > $attendee->getLastReadMessage())) {
				return true;
			}

			return ($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER)
				&& $room->getLastMessageId() > $attendee->getLastReadMessage()
				&& $this->chatManager->getUnreadCount($room, $attendee->getLastReadMessage()) > 0;
		});

		uasort($rooms, [$this, 'sortRooms']);

		$rooms = array_slice($rooms, 0, $limit);

		$result = [];
		foreach ($rooms as $room) {
			$result[] = $this->prepareRoom($room, $userId);
		}

		return $result;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getItemsV2(string $userId, ?string $since = null, int $limit = 7): WidgetItems {
		$event = new BeforeRoomsFetchEvent($userId);
		$this->dispatcher->dispatchTyped($event);

		$allRooms = $this->manager->getRoomsForUser($userId, [], true);

		$rooms = [];
		$mentions = [];
		foreach ($allRooms as $room) {
			if ($room->getObjectType() === BreakoutRoom::PARENT_OBJECT_TYPE) {
				continue;
			}

			$participant = $this->participantService->getParticipant($room, $userId);
			$attendee = $participant->getAttendee();

			if ($room->getLobbyState() !== Webinary::LOBBY_NONE
				&& !($participant->getPermissions() & Attendee::PERMISSIONS_LOBBY_IGNORE)) {
				continue;
			}

			if (!$attendee->isArchived()) {
				$rooms[] = $room;

				if ($room->getCallFlag() !== Participant::FLAG_DISCONNECTED) {
					// Call in progress
					$mentions[] = $room;
					continue;
				}
			}

			if (($room->isFederatedConversation() && $attendee->getLastMentionMessage())
				|| (!$room->isFederatedConversation() && $attendee->getLastMentionMessage() > $attendee->getLastReadMessage())) {
				// Really mentioned
				$mentions[] = $room;
				continue;
			}

			if (($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER)
				&& $room->getLastMessageId() > $attendee->getLastReadMessage()) {
				// If there are "unread" messages in one-to-one or former one-to-one
				// we check if they are actual messages or system messages not
				// considered by the read-marker
				if ($this->chatManager->getUnreadCount($room, $attendee->getLastReadMessage()) > 0) {
					// Unread message in one-to-one are considered "mentions"
					$mentions[] = $room;
				}
			}
		}

		$roomsToReturn = $rooms;
		if (!empty($mentions)) {
			$roomsToReturn = $mentions;
		}

		uasort($roomsToReturn, [$this, 'sortRooms']);
		$roomsToReturn = array_slice($roomsToReturn, 0, $limit);

		$result = [];
		foreach ($roomsToReturn as $room) {
			$result[] = $this->prepareRoom($room, $userId);
		}

		return new WidgetItems(
			$result,
			empty($result) ? $this->l10n->t('Say hi to your friends and colleagues!') : '',
			empty($mentions) ? $this->l10n->t('No unread mentions') : '',
		);
	}

	protected function prepareRoom(Room $room, string $userId): WidgetItem {
		$participant = $this->participantService->getParticipant($room, $userId);
		$attendee = $participant->getAttendee();
		$subtitle = '';

		if ($attendee->isSensitive()) {
			// Don't leak sensitive last messages on dashboard
		} elseif ($room->getLastMessageId() && $room->isFederatedConversation()) {
			try {
				$cachedMessage = $this->pcmService->findByRemote(
					$room->getRemoteServer(),
					$room->getRemoteToken(),
					$room->getLastMessageId(),
				);
				$message = $this->messageParser->createMessageFromProxyCache($room, $participant, $cachedMessage, $this->l10n);
				$subtitle = $this->getSubtitleFromMessage($message);
			} catch (DoesNotExistException) {
				// Fallback to empty subtitle
			}
		} elseif ($room->getLastMessageId() && $room->getLastMessage() && !$room->isFederatedConversation()) {
			$message = $this->messageParser->createMessage($room, $participant, $room->getLastMessage(), $this->l10n);
			$this->messageParser->parseMessage($message, true);
			$subtitle = $this->getSubtitleFromMessage($message);
		}

		if ($room->getCallFlag() !== Participant::FLAG_DISCONNECTED) {
			$subtitle = $this->l10n->t('Call in progress');
		} elseif (($room->isFederatedConversation() && $attendee->getLastMentionMessage())
			|| (!$room->isFederatedConversation() && $attendee->getLastMentionMessage() > $attendee->getLastReadMessage())) {
			$subtitle = $this->l10n->t('You were mentioned');
		}

		return new WidgetItem(
			$room->getDisplayName($userId),
			$subtitle,
			$this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]),
			$this->avatarService->getAvatarUrl($room)
		);
	}

	protected function getSubtitleFromMessage(Message $message): string {
		$expireDate = $message->getExpirationDateTime();
		if ($expireDate instanceof \DateTimeInterface
			&& $expireDate <= $this->timeFactory->getDateTime()) {
			return '';
		}

		if (!$message->getVisibility()) {
			return '';
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

		return str_replace($placeholders, $replacements, $message->getMessage());
	}

	protected function sortRooms(Room $roomA, Room $roomB): int {
		if ($roomA->getCallFlag() !== $roomB->getCallFlag()) {
			return $roomA->getCallFlag() !== Participant::FLAG_DISCONNECTED ? -1 : 1;
		}

		return $roomA->getLastActivity() >= $roomB->getLastActivity() ? -1 : 1;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getReloadInterval(): int {
		return 30;
	}
}
