<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Spreed\Collaboration\Resources;

use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IResource;
use OCP\Collaboration\Resources\ResourceException;
use OCP\IURLGenerator;
use OCP\IUser;

class ConversationProvider implements IProvider {

	/** @var Manager */
	protected $manager;
	/** @var IURLGenerator */
	protected $urlGenerator;

	public function __construct(Manager $manager, IURLGenerator $urlGenerator) {
		$this->manager = $manager;
		$this->urlGenerator = $urlGenerator;
	}

	public function getResourceRichObject(IResource $resource): array {
		try {
			$room = $this->manager->getRoomByToken($resource->getId());

			return [
				'type' => 'room',
				'id' => $resource->getId(),
				'name' => $room->getDisplayName(''),
				'call-type' => $this->getRoomType($room),
				'iconUrl' => $this->urlGenerator->getAbsoluteURL($this->urlGenerator->imagePath('spreed', 'app-dark.svg')),
				'link' => $this->urlGenerator->linkToRouteAbsolute('spreed.pagecontroller.showCall', ['token' => $room->getToken()])
			];
		} catch (RoomNotFoundException $e) {
			throw new ResourceException('Conversation not found');
		}
	}

	public function canAccessResource(IResource $resource, IUser $user = null): bool {
		try {
			$room = $this->manager->getRoomForParticipantByToken(
				$resource->getId(),
				$user instanceof IUser ? $user->getUID() : null
			);
			return $user instanceof IUser || $room->getType() === Room::PUBLIC_CALL;
		} catch (RoomNotFoundException $e) {
			throw new ResourceException('Conversation not found');
		}
	}

	public function getType(): string {
		return 'room';
	}

	/**
	 * @param Room $room
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function getRoomType(Room $room): string {
		switch ($room->getType()) {
			case Room::ONE_TO_ONE_CALL:
				return 'one2one';
			case Room::GROUP_CALL:
				return 'group';
			case Room::PUBLIC_CALL:
				return 'public';
			default:
				throw new \InvalidArgumentException('Unknown room type');
		}
	}
}
