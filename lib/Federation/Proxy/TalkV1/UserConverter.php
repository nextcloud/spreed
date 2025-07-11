<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
 * @psalm-import-type TalkPollDraft from ResponseDefinitions
 * @psalm-import-type TalkReaction from ResponseDefinitions
 * @psalm-import-type TalkThreadInfo from ResponseDefinitions
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
			$id = $this->createCloudIdFromUserIdAndFullServerUrl($id, $room->getRemoteServer());
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
			$entry[$idField] = $this->createCloudIdFromUserIdAndFullServerUrl($entry[$idField], $room->getRemoteServer());
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
				if (!isset($parameter['mention-id'])) {
					$parameter['mention-id'] = $parameter['id'];
				}
			} elseif ($parameter['server']) {
				$localParticipants = $this->getLocalParticipants($room);
				$cloudId = $this->createCloudIdFromUserIdAndFullServerUrl($parameter['id'], $parameter['server']);
				if (!isset($parameter['mention-id'])) {
					$parameter['mention-id'] = 'federated_user/' . $parameter['id'] . '@' . $parameter['server'];
				}
				if (isset($localParticipants[$cloudId])) {
					unset($parameter['server']);
					$parameter['name'] = $localParticipants[$cloudId]['displayName'];
				}
			}
		} elseif ($parameter['type'] === 'call' && $parameter['id'] === $room->getRemoteToken()) {
			$parameter['id'] = $room->getToken();
			$parameter['icon-url'] = $this->avatarService->getAvatarUrl($room);
			if (!isset($parameter['mention-id'])) {
				$parameter['mention-id'] = 'all';
			}
		} elseif ($parameter['type'] === 'circle') {
			if (!isset($parameter['mention-id'])) {
				$parameter['mention-id'] = 'team/' . $parameter['id'];
			}
		} elseif ($parameter['type'] === 'user-group') {
			if (!isset($parameter['mention-id'])) {
				$parameter['mention-id'] = 'group/' . $parameter['id'];
			}
		} elseif ($parameter['type'] === 'email' || $parameter['type'] === 'guest') {
			if (!isset($parameter['mention-id'])) {
				$parameter['mention-id'] = $parameter['type'] . '/' . $parameter['id'];
			}
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
	 * @param TalkThreadInfo $threadInfo
	 * @return TalkThreadInfo
	 */
	public function convertThreadInfo(Room $room, array $threadInfo): array {
		$threadInfo['thread']['roomToken'] = $room->getToken();
		if (isset($threadInfo['first'])) {
			$threadInfo['first'] = $this->convertMessageParameters($room, $threadInfo['first']);
		}
		if (isset($threadInfo['last'])) {
			$threadInfo['last'] = $this->convertMessageParameters($room, $threadInfo['last']);
		}
		return $threadInfo;
	}

	/**
	 * @param Room $room
	 * @param list<TalkThreadInfo> $threadInfos
	 * @return list<TalkThreadInfo>
	 */
	public function convertThreadInfos(Room $room, array $threadInfos): array {
		return array_map(
			fn (array $threadInfo): array => $this->convertThreadInfo($room, $threadInfo),
			$threadInfos
		);
	}

	/**
	 * @template T of TalkPoll|TalkPollDraft
	 * @param Room $room
	 * @param TalkPoll|TalkPollDraft $poll
	 * @psalm-param T $poll
	 * @return TalkPoll|TalkPollDraft
	 * @psalm-return T
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

	protected function createCloudIdFromUserIdAndFullServerUrl(string $userId, string $serverUrl): string {
		if (str_starts_with($serverUrl, 'https://')) {
			$serverUrl = substr($serverUrl, strlen('https://'));
		}
		return $userId . '@' . $serverUrl;
	}
}
