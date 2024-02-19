<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Federation\Proxy\TalkV1;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;

class UserConverter {
	/**
	 * @var array<string, array<string, array{userId: string, displayName: string}>>
	 */
	protected array $participantsPerRoom = [];

	public function __construct(
		protected ParticipantService $participantService,
	) {
	}

	public function convertAttendees(Room $room, array $entries, string $typeField, string $idField, string $displayNameField): array {
		return array_map(
			function (array $entry) use ($room, $typeField, $idField, $displayNameField): array {
				if ($entry[$typeField] === Attendee::ACTOR_USERS) {
					$entry[$typeField] = Attendee::ACTOR_FEDERATED_USERS;
					$entry[$idField] .= '@' . $room->getRemoteServer();
				} elseif ($entry[$typeField] === Attendee::ACTOR_FEDERATED_USERS) {
					$localParticipants = $this->getLocalParticipants($room);
					if (isset($localParticipants[$entry[$idField]])) {
						$local = $localParticipants[$entry[$idField]];

						$entry[$typeField] = Attendee::ACTOR_USERS;
						$entry[$idField] = $local['userId'];
						$entry[$displayNameField] = $local['displayName'];
					}
				}
				return $entry;
			},
			$entries
		);
	}

	/**
	 * @return array<string, array{userId: string, displayName: string}>
	 */
	protected function getLocalParticipants(Room $room): array {
		if (array_key_exists($room->getToken(), $this->participantsPerRoom)) {
			return $this->participantsPerRoom[$room->getToken()];
		}

		$this->participantsPerRoom[$room->getToken()] = [];
		$localParticipants = $this->participantService->getActorsByType($room, Attendee::ACTOR_USERS);
		foreach ($localParticipants as $participant) {
			$this->participantsPerRoom[$room->getToken()][$participant->getInvitedCloudId()] = [
				'userId' => $participant->getActorId(),
				'displayName' => $participant->getDisplayName(),
			];
		}

		return $this->participantsPerRoom[$room->getToken()];
	}
}
