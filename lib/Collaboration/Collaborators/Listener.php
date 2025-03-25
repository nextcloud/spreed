<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Collaboration\Collaborators;

use OCA\Talk\Config;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\MatterbridgeManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\TalkSession;
use OCP\Collaboration\AutoComplete\AutoCompleteFilterEvent;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\IUser;
use OCP\IUserManager;

/**
 * @template-implements IEventListener<Event>
 */
class Listener implements IEventListener {
	/** @var string[] */
	protected array $allowedGroupIds = [];
	protected string $roomToken;
	protected ?Room $room = null;

	public function __construct(
		protected Manager $manager,
		protected IUserManager $userManager,
		protected ParticipantService $participantService,
		protected Config $config,
		protected TalkSession $talkSession,
		protected ?string $userId,
	) {
	}

	#[\Override]
	public function handle(Event $event): void {
		if (!$event instanceof AutoCompleteFilterEvent) {
			return;
		}

		if ($event->getItemType() !== 'call') {
			return;
		}

		$event->setResults($this->filterUsersAndGroupsWithoutTalk($event->getResults()));

		$event->setResults($this->filterBridgeBot($event->getResults()));
		if ($event->getItemId() !== 'new') {
			$event->setResults($this->filterExistingParticipants($event->getItemId(), $event->getResults()));
		}
	}

	protected function filterUsersAndGroupsWithoutTalk(array $results): array {
		$this->allowedGroupIds = $this->config->getAllowedTalkGroupIds();
		if (empty($this->allowedGroupIds)) {
			return $results;
		}

		if (!empty($results['groups'])) {
			$results['groups'] = array_filter($results['groups'], [$this, 'filterBlockedGroupResult']);
		}
		if (!empty($results['exact']['groups'])) {
			$results['exact']['groups'] = array_filter($results['exact']['groups'], [$this, 'filterBlockedGroupResult']);
		}

		if (!empty($results['users'])) {
			$results['users'] = array_filter($results['users'], [$this, 'filterBlockedUserResult']);
		}
		if (!empty($results['exact']['users'])) {
			$results['exact']['users'] = array_filter($results['exact']['users'], [$this, 'filterBlockedUserResult']);
		}

		return $results;
	}

	protected function filterBlockedUserResult(array $result): bool {
		$user = $this->userManager->get($result['value']['shareWith']);
		return $user instanceof IUser && !$this->config->isDisabledForUser($user);
	}

	protected function filterBlockedGroupResult(array $result): bool {
		return \in_array($result['value']['shareWith'], $this->allowedGroupIds, true);
	}

	protected function filterBridgeBot(array $results): array {
		if (!empty($results['users'])) {
			$results['users'] = array_filter($results['users'], [$this, 'filterBridgeBotUserResult']);
		}
		if (!empty($results['exact']['users'])) {
			$results['exact']['users'] = array_filter($results['exact']['users'], [$this, 'filterBridgeBotUserResult']);
		}

		return $results;
	}

	protected function filterExistingParticipants(string $token, array $results): array {
		$sessionId = $this->talkSession->getSessionForRoom($token);
		try {
			$this->room = $this->manager->getRoomForUserByToken($token, $this->userId);
			if ($this->userId !== null) {
				$this->participantService->getParticipant($this->room, $this->userId, $sessionId);
			} else {
				$this->participantService->getParticipantBySession($this->room, $sessionId);
			}
		} catch (RoomNotFoundException|ParticipantNotFoundException) {
			return $results;
		}

		if ($this->room->isFederatedConversation()) {
			return $results;
		}

		if (!empty($results['groups'])) {
			$results['groups'] = array_filter($results['groups'], [$this, 'filterParticipantGroupResult']);
		}
		if (!empty($results['exact']['groups'])) {
			$results['exact']['groups'] = array_filter($results['exact']['groups'], [$this, 'filterParticipantGroupResult']);
		}

		if (!empty($results['users'])) {
			$results['users'] = array_filter($results['users'], [$this, 'filterParticipantUserResult']);
		}
		if (!empty($results['exact']['users'])) {
			$results['exact']['users'] = array_filter($results['exact']['users'], [$this, 'filterParticipantUserResult']);
		}

		if (!empty($results['circles'])) {
			$results['circles'] = array_filter($results['circles'], [$this, 'filterParticipantTeamResult']);
		}
		if (!empty($results['exact']['circles'])) {
			$results['exact']['circles'] = array_filter($results['exact']['circles'], [$this, 'filterParticipantTeamResult']);
		}

		return $results;
	}

	protected function filterBridgeBotUserResult(array $result): bool {
		return $result['value']['shareWith'] !== MatterbridgeManager::BRIDGE_BOT_USERID;
	}

	protected function filterParticipantUserResult(array $result): bool {
		$userId = $result['value']['shareWith'];

		try {
			$participant = $this->participantService->getParticipant($this->room, $userId, false);
			if ($participant->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
				// do list self-joined users so they can be added as permanent participants by moderators
				return true;
			}
			return false;
		} catch (ParticipantNotFoundException $e) {
			return true;
		}
	}

	protected function filterParticipantGroupResult(array $result): bool {
		$groupId = $result['value']['shareWith'];

		try {
			$this->participantService->getParticipantByActor($this->room, Attendee::ACTOR_GROUPS, $groupId);
			return false;
		} catch (ParticipantNotFoundException $e) {
			return true;
		}
	}

	protected function filterParticipantTeamResult(array $result): bool {
		$circleId = $result['value']['shareWith'];

		try {
			$this->participantService->getParticipantByActor($this->room, Attendee::ACTOR_CIRCLES, $circleId);
			return false;
		} catch (ParticipantNotFoundException $e) {
			return true;
		}
	}
}
