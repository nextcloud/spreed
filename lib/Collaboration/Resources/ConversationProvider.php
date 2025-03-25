<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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

	public function __construct(
		protected Manager $manager,
		protected AvatarService $avatarService,
		protected ParticipantService $participantService,
		protected IUserSession $userSession,
		protected IURLGenerator $urlGenerator,
	) {
	}

	#[\Override]
	public function getResourceRichObject(IResource $resource): array {
		try {
			$user = $this->userSession->getUser();
			$userId = $user instanceof IUser ? $user->getUID() : '';
			$room = $this->manager->getRoomByToken($resource->getId(), $userId);

			$iconURL = $this->avatarService->getAvatarUrl($room);
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

	#[\Override]
	public function canAccessResource(IResource $resource, ?IUser $user = null): bool {
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

	#[\Override]
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
			case Room::TYPE_ONE_TO_ONE:
			case Room::TYPE_ONE_TO_ONE_FORMER:
				return 'one2one';
			case Room::TYPE_GROUP:
				return 'group';
			case Room::TYPE_PUBLIC:
				return 'public';
			default:
				throw new \InvalidArgumentException('Unknown room type');
		}
	}
}
