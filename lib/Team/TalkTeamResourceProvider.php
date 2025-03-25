<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Team;

use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Teams\ITeamResourceProvider;
use OCP\Teams\TeamResource;

class TalkTeamResourceProvider implements ITeamResourceProvider {
	public function __construct(
		private ParticipantService $participantService,
		private Manager $manager,
		private AvatarService $avatarService,
		private IL10N $l10n,
		private IURLGenerator $urlGenerator,
	) {
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getId(): string {
		return 'talk';
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getName(): string {
		return $this->l10n->t('Talk conversations');
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getIconSvg(): string {
		return '<svg width="16" height="16" version="1.1" viewBox="0 0 16 16" xmlns="http://www.w3.org/2000/svg"><path d="m7.9992 0.999a6.9993 6.9994 0 0 0-6.9992 6.9996 6.9993 6.9994 0 0 0 6.9992 6.9994 6.9993 6.9994 0 0 0 3.6308-1.024c0.86024 0.34184 2.7871 1.356 3.2457 0.91794 0.47922-0.45765-0.56261-2.6116-0.81238-3.412a6.9993 6.9994 0 0 0 0.935-3.4814 6.9993 6.9994 0 0 0-6.9991-6.9993zm8e-4 2.6611a4.34 4.3401 0 0 1 4.34 4.3401 4.34 4.3401 0 0 1-4.34 4.3398 4.34 4.3401 0 0 1-4.34-4.3398 4.34 4.3401 0 0 1 4.34-4.3401z" stroke-width=".14"/></svg>';
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getSharedWith(string $teamId): array {
		$rooms = $this->manager->getRoomsForActor(Attendee::ACTOR_CIRCLES, $teamId);
		return array_map(function (Room $room) {
			return new TeamResource(
				$this,
				$room->getToken(),
				$room->getName(),
				$this->urlGenerator->linkToRouteAbsolute('spreed.Page.showCall', ['token' => $room->getToken()]),
				iconURL: $this->avatarService->getAvatarUrl($room),
			);
		}, $rooms);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function isSharedWithTeam(string $teamId, string $resourceId): bool {
		try {
			$this->manager->getRoomByActor($resourceId, Attendee::ACTOR_CIRCLES, $teamId);
			return true;
		} catch (RoomNotFoundException) {
		}

		return false;
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function getTeamsForResource(string $resourceId): array {
		try {
			$room = $this->manager->getRoomByToken($resourceId);
			$participants = $this->participantService->getParticipantsByActorType($room, Attendee::ACTOR_CIRCLES);
			return array_map(function (Participant $participant) {
				return $participant->getAttendee()->getActorId();
			}, $participants);
		} catch (RoomNotFoundException) {
		}

		return [];
	}
}
