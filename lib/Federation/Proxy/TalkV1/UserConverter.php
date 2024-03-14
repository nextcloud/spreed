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
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\AvatarService;
use OCA\Talk\Service\ParticipantService;

/**
 * @psalm-import-type TalkChatMessageWithParent from ResponseDefinitions
 * @psalm-import-type TalkPoll from ResponseDefinitions
 * @psalm-import-type TalkReaction from ResponseDefinitions
 */
class UserConverter {
	/**
	 * @var array<string, array<string, array{userId: string, displayName: string}>>
	 */
	protected array $participantsPerRoom = [];

	public function __construct(
		protected ParticipantService $participantService,
		protected AvatarService $avatarService,
	) {
	}

	/**
	 * @return array{type: string, id: string}
	 */
	public function convertTypeAndId(Room $room, string $type, string $id): array {
		if ($type === Attendee::ACTOR_USERS) {
			$type = Attendee::ACTOR_FEDERATED_USERS;
			$id .= '@' . $room->getRemoteServer();
		} elseif ($type === Attendee::ACTOR_FEDERATED_USERS) {
			$localParticipants = $this->getLocalParticipants($room);
			if (isset($localParticipants[$id])) {
				$local = $localParticipants[$id];

				$type = Attendee::ACTOR_USERS;
				$id = $local['userId'];
			}
		}

		return ['type' => $type, 'id' => $id];
	}

	public function convertAttendee(Room $room, array $entry, string $typeField, string $idField, string $displayNameField): array {
		if (!isset($entry[$typeField])) {
			return $entry;
		}

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
	}

	public function convertAttendees(Room $room, array $entries, string $typeField, string $idField, string $displayNameField): array {
		return array_map(
			fn (array $entry): array => $this->convertAttendee($room, $entry, $typeField, $idField, $displayNameField),
			$entries
		);
	}

	protected function convertMessageParameter(Room $room, array $parameter): array {
		if ($parameter['type'] === 'user') { // RichObjectDefinition, not Attendee::ACTOR_USERS
			if (!isset($parameter['server'])) {
				$parameter['server'] = $room->getRemoteServer();
			} elseif ($parameter['server']) {
				$localParticipants = $this->getLocalParticipants($room);
				$cloudId = $parameter['id'] . '@' . $parameter['server'];
				if (isset($localParticipants[$cloudId])) {
					unset($parameter['server']);
					$parameter['name'] = $localParticipants[$cloudId]['displayName'];
				}
			}
		} elseif ($parameter['type'] === 'call' && $parameter['id'] === $room->getRemoteToken()) {
			$parameter['id'] = $room->getToken();
			$parameter['icon-url'] = $this->avatarService->getAvatarUrl($room);
		}
		return $parameter;
	}

	public function convertMessageParameters(Room $room, array $message): array {
		$message['messageParameters'] = array_map(
			fn (array $message): array => $this->convertMessageParameter($room, $message),
			$message['messageParameters']
		);
		return $message;
	}

	/**
	 * @param Room $room
	 * @param TalkChatMessageWithParent $message
	 * @return TalkChatMessageWithParent
	 */
	public function convertMessage(Room $room, array $message): array {
		$message['token'] = $room->getToken();
		$message = $this->convertAttendee($room, $message, 'actorType', 'actorId', 'actorDisplayName');
		$message = $this->convertAttendee($room, $message, 'lastEditActorType', 'lastEditActorId', 'lastEditActorDisplayName');
		$message = $this->convertMessageParameters($room, $message);

		if (isset($message['parent'])) {
			$message['parent'] = $this->convertMessage($room, $message['parent']);
		}
		return $message;
	}

	/**
	 * @param Room $room
	 * @param TalkChatMessageWithParent[] $messages
	 * @return TalkChatMessageWithParent[]
	 */
	public function convertMessages(Room $room, array $messages): array {
		return array_map(
			fn (array $message): array => $this->convertMessage($room, $message),
			$messages
		);
	}

	/**
	 * @param Room $room
	 * @param TalkPoll $poll
	 * @return TalkPoll
	 */
	public function convertPoll(Room $room, array $poll): array {
		$poll = $this->convertAttendee($room, $poll, 'actorType', 'actorId', 'actorDisplayName');
		if (isset($poll['details'])) {
			$poll['details'] = array_map(
				fn (array $vote): array => $this->convertAttendee($room, $vote, 'actorType', 'actorId', 'actorDisplayName'),
				$poll['details']
			);
		}
		return $poll;
	}

	/**
	 * @param Room $room
	 * @param TalkReaction[] $reactions
	 * @return TalkReaction[]
	 */
	protected function convertReactions(Room $room, array $reactions): array {
		return array_map(
			fn (array $reaction): array => $this->convertAttendee($room, $reaction, 'actorType', 'actorId', 'actorDisplayName'),
			$reactions
		);
	}

	/**
	 * @param Room $room
	 * @param array<string, TalkReaction[]> $reactionsList
	 * @return array<string, TalkReaction[]>
	 */
	public function convertReactionsList(Room $room, array $reactionsList): array {
		return array_map(
			fn (array $reactions): array => $this->convertReactions($room, $reactions),
			$reactionsList
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
