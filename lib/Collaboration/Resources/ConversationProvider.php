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

namespace OCA\Talk\Collaboration\Resources;

use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\Collaboration\Resources\IProvider;
use OCP\Collaboration\Resources\IResource;
use OCP\Collaboration\Resources\ResourceException;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;

class ConversationProvider implements IProvider {
	protected Manager $manager;
	protected ParticipantService $participantService;
	protected AvatarService $avatarService;
	protected IUserSession $userSession;
	protected IURLGenerator $urlGenerator;

	public function __construct(
		Manager $manager,
		AvatarService $avatarService,
		ParticipantService $participantService,
		IUserSession $userSession,
		IURLGenerator $urlGenerator,
	) {
		$this->manager = $manager;
		$this->avatarService = $avatarService;
		$this->participantService = $participantService;
		$this->userSession = $userSession;
		$this->urlGenerator = $urlGenerator;
	}

	public function getResourceRichObject(IResource $resource): array {
		try {
			$user = $this->userSession->getUser();
			$userId = $user instanceof IUser ? $user->getUID() : '';
			$room = $this->manager->getRoomByToken($resource->getId(), $userId);

			$iconURL = $this->avatarService->getAvatarUrl($room, $userId);
			/**
			 * Disabled for now, because it would show a square avatar
			 * if ($room->getType() === Room::TYPE_ONE_TO_ONE) {
			 * $iconURL = $this->urlGenerator->linkToRouteAbsolute('core.avatar.getAvatar', ['userId' => 'admin', 'size' => 32]);
			 * }
			 */

			return [
				'type' => 'room',
				'id' => $resource->getId(),
				'name' => $room->getDisplayName($userId),
				'call-type' => $this->getRoomType($room),
				'iconUrl' => $iconURL,
				'link' => $this->urlGenerator->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()])
			];
		} catch (RoomNotFoundException $e) {
			throw new ResourceException('Conversation not found');
		}
	}

	public function canAccessResource(IResource $resource, IUser $user = null): bool {
		$userId = $user instanceof IUser ? $user->getUID() : null;
		if ($userId === null) {
			throw new ResourceException('Guests are not supported at the moment');
		}

		try {
			$room = $this->manager->getRoomForUserByToken(
				$resource->getId(),
				$userId
			);

			// Logged in users need to have a regular participant,
			// before they can do anything with the room.
			$participant = $this->participantService->getParticipant($room, $userId, false);
			return $participant->getAttendee()->getParticipantType() !== Participant::USER_SELF_JOINED;
		} catch (RoomNotFoundException $e) {
			throw new ResourceException('Conversation not found');
		} catch (ParticipantNotFoundException $e) {
			throw new ResourceException('Participant not found');
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
		return match ($room->getType()) {
			Room::TYPE_ONE_TO_ONE, Room::TYPE_ONE_TO_ONE_FORMER => 'one2one',
			Room::TYPE_GROUP => 'group',
			Room::TYPE_PUBLIC => 'public',
			default => throw new \InvalidArgumentException('Unknown room type'),
		};
	}
}
