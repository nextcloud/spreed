<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Julius Härtl <jus@bitgrid.net>
 *
 * @author Julius Härtl <jus@bitgrid.net>
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
 *
 */

namespace OCA\Talk\Dashboard;

use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Config;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\IButtonWidget;
use OCP\Dashboard\IConditionalWidget;
use OCP\Dashboard\IIconWidget;
use OCP\Dashboard\IOptionWidget;
use OCP\Dashboard\Model\WidgetButton;
use OCP\Dashboard\Model\WidgetItem;
use OCP\Dashboard\Model\WidgetOptions;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Util;

class TalkWidget implements IAPIWidget, IIconWidget, IButtonWidget, IOptionWidget, IConditionalWidget {
	protected IUserSession $userSession;
	protected Config $talkConfig;
	protected IURLGenerator $url;
	protected IL10N $l10n;
	protected Manager $manager;
	protected ParticipantService $participantService;
	protected MessageParser $messageParser;
	protected ITimeFactory $timeFactory;

	public function __construct(
		IUserSession $userSession,
		Config $talkConfig,
		IURLGenerator $url,
		IL10N $l10n,
		Manager $manager,
		ParticipantService $participantService,
		MessageParser $messageParser,
		ITimeFactory $timeFactory
	) {
		$this->userSession = $userSession;
		$this->talkConfig = $talkConfig;
		$this->url = $url;
		$this->l10n = $l10n;
		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->messageParser = $messageParser;
		$this->timeFactory = $timeFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'spreed';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string {
		return $this->l10n->t('Talk mentions');
	}

	/**
	 * @inheritDoc
	 */
	public function getOrder(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconClass(): string {
		return 'dashboard-talk-icon';
	}

	/**
	 * @inheritDoc
	 */
	public function isEnabled(): bool {
		$user = $this->userSession->getUser();
		return !($user instanceof IUser && $this->talkConfig->isDisabledForUser($user));
	}

	public function getWidgetOptions(): WidgetOptions {
		return new WidgetOptions(true);
	}

	/**
	 * @return \OCP\Dashboard\Model\WidgetButton[]
	 */
	public function getWidgetButtons(string $userId): array {
		$buttons = [];
		$buttons[] = new WidgetButton(
			WidgetButton::TYPE_MORE,
			$this->url->linkToRouteAbsolute('spreed.Page.index'),
			$this->l10n->t('More unread mentions')
		);
		return $buttons;
	}

	/**
	 * @inheritDoc
	 */
	public function getIconUrl(): string {
		return $this->url->getAbsoluteURL($this->url->imagePath('spreed', 'app-dark.svg'));
	}

	/**
	 * @inheritDoc
	 */
	public function getUrl(): ?string {
		return $this->url->linkToRouteAbsolute('spreed.Page.index');
	}

	/**
	 * @inheritDoc
	 */
	public function load(): void {
		Util::addStyle('spreed', 'icons');
		Util::addScript('spreed', 'talk-dashboard');
	}

	public function getItems(string $userId, ?string $since = null, int $limit = 7): array {
		$rooms = $this->manager->getRoomsForUser($userId, [], true);

		$rooms = array_filter($rooms, function (Room $room) use ($userId) {
			$participant = $this->participantService->getParticipant($room, $userId);
			$attendee = $participant->getAttendee();
			return $room->getCallFlag() !== Participant::FLAG_DISCONNECTED
				|| $attendee->getLastMentionMessage() > $attendee->getLastReadMessage()
				|| (
					($room->getType() === Room::TYPE_ONE_TO_ONE || $room->getType() === Room::TYPE_ONE_TO_ONE_FORMER)
					&& $room->getLastMessage()
					&& $room->getLastMessage()->getId() > $attendee->getLastReadMessage()
				);
		});

		uasort($rooms, [$this, 'sortRooms']);

		$rooms = array_slice($rooms, 0, $limit);

		$result = [];
		foreach ($rooms as $room) {
			$result[] = $this->prepareRoom($room, $userId);
		}

		return $result;
	}

	protected function prepareRoom(Room $room, string $userId): WidgetItem {
		$participant = $this->participantService->getParticipant($room, $userId);
		$subtitle = '';

		$lastMessage = $room->getLastMessage();
		if ($lastMessage instanceof IComment) {
			$message = $this->messageParser->createMessage($room, $participant, $room->getLastMessage(), $this->l10n);
			$this->messageParser->parseMessage($message);

			$now = $this->timeFactory->getDateTime();
			$expireDate = $message->getComment()->getExpireDate();
			if ((!$expireDate instanceof \DateTime || $expireDate >= $now)
				&& $message->getVisibility()) {
				$placeholders = $replacements = [];

				foreach ($message->getMessageParameters() as $placeholder => $parameter) {
					$placeholders[] = '{' . $placeholder . '}';
					if ($parameter['type'] === 'user' || $parameter['type'] === 'guest') {
						$replacements[] = '@' . $parameter['name'];
					} else {
						$replacements[] = $parameter['name'];
					}
				}

				$subtitle = str_replace($placeholders, $replacements, $message->getMessage());
			}
		}

		if ($room->getCallFlag() !== Participant::FLAG_DISCONNECTED) {
			$subtitle = $this->l10n->t('Call in progress');
		} elseif ($participant->getAttendee()->getLastMentionMessage() > $participant->getAttendee()->getLastReadMessage()) {
			$subtitle = $this->l10n->t('You were mentioned');
		}

		return new WidgetItem(
			$room->getDisplayName($userId),
			$subtitle,
			$this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]),
			$this->getRoomIconUrl($room, $userId)
		);
	}

	protected function getRoomIconUrl(Room $room, string $userId): string {
		if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			$participants = json_decode($room->getName(), true);

			foreach ($participants as $p) {
				if ($p !== $userId) {
					return $this->url->linkToRouteAbsolute(
						'core.avatar.getAvatar',
						[
							'userId' => $p,
							'size' => 64,
						]
					);
				}
			}
		} elseif ($room->getObjectType() === 'file') {
			return $this->url->getAbsoluteURL($this->url->imagePath('core', 'filetypes/file.svg'));
		} elseif ($room->getObjectType() === 'share:password') {
			return $this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/password.svg'));
		} elseif ($room->getObjectType() === 'emails') {
			return $this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/mail.svg'));
		} elseif ($room->getType() === Room::TYPE_PUBLIC) {
			return $this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/public.svg'));
		}

		return $this->url->getAbsoluteURL($this->url->imagePath('core', 'actions/group.svg'));
	}

	protected function sortRooms(Room $roomA, Room $roomB): int {
		if ($roomA->getCallFlag() !== $roomB->getCallFlag()) {
			return $roomA->getCallFlag() !== Participant::FLAG_DISCONNECTED ? -1 : 1;
		}

		return $roomA->getLastActivity() >= $roomB->getLastActivity() ? -1 : 1;
	}
}
