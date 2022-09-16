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
use OCA\Talk\Manager;
use OCA\Talk\Room;
use OCP\Comments\IComment;
use OCP\Dashboard\IAPIWidget;
use OCP\Dashboard\Model\WidgetItem;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Util;

class TalkWidget implements IAPIWidget {
	private IURLGenerator $url;
	private IL10N $l10n;
	private Manager $manager;
	private MessageParser $messageParser;

	public function __construct(
		IURLGenerator $url,
		IL10N $l10n,
		Manager $manager,
		MessageParser $messageParser
	) {
		$this->url = $url;
		$this->l10n = $l10n;
		$this->manager = $manager;
		$this->messageParser = $messageParser;
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

		$rooms = array_filter($rooms, static function (Room $room) use ($userId) {
			$participant = $room->getParticipant($userId);
			$attendee = $participant->getAttendee();
			return $room->getLastMessage() && $room->getLastMessage()->getId() > $attendee->getLastReadMessage();
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
		$participant = $room->getParticipant($userId);
		$subtitle = '';

		$lastMessage = $room->getLastMessage();
		if ($lastMessage instanceof IComment) {
			$message = $this->messageParser->createMessage($room, $participant, $room->getLastMessage(), $this->l10n);
			$this->messageParser->parseMessage($message);
			if ($message->getVisibility()) {
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

		return new WidgetItem(
			$room->getDisplayName($userId),
			$subtitle,
			$this->url->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]),
			$this->getIconUrl($room, $userId)
		);
	}

	protected function getIconUrl(Room $room, string $userId): string {
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
		return $roomA->getLastActivity() < $roomB->getLastActivity() ? -1 : 1;
	}
}
