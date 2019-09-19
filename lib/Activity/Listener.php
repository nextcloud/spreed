<?php
declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Activity;

use OCA\Spreed\Chat\ChatManager;
use OCA\Spreed\Room;
use OCP\Activity\IManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ILogger;
use OCP\IUser;
use OCP\IUserSession;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	/** @var IManager */
	protected $activityManager;

	/** @var IUserSession */
	protected $userSession;

	/** @var ChatManager */
	protected $chatManager;

	/** @var ILogger */
	protected $logger;

	/** @var ITimeFactory */
	protected $timeFactory;

	public function __construct(IManager $activityManager,
								IUserSession $userSession,
								ChatManager $chatManager,
								ILogger $logger,
								ITimeFactory $timeFactory) {
		$this->activityManager = $activityManager;
		$this->userSession = $userSession;
		$this->chatManager = $chatManager;
		$this->logger = $logger;
		$this->timeFactory = $timeFactory;
	}

	public static function register(EventDispatcherInterface $dispatcher): void {
		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->setActive($room);
		};
		$dispatcher->addListener(Room::class . '::postSessionJoinCall', $listener);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->generateCallActivity($room);
		};
		$dispatcher->addListener(Room::class . '::postRemoveBySession', $listener);
		$dispatcher->addListener(Room::class . '::postRemoveUser', $listener);
		$dispatcher->addListener(Room::class . '::postSessionLeaveCall', $listener, -100);

		$listener = function(GenericEvent $event) {
			/** @var Room $room */
			$room = $event->getSubject();

			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->generateInvitationActivity($room, $event->getArgument('users'));
		};
		$dispatcher->addListener(Room::class . '::postAddUsers', $listener);
	}

	public function setActive(Room $room): void {
		$room->setActiveSince($this->timeFactory->getDateTime(), !$this->userSession->isLoggedIn());
	}

	/**
	 * Call activity: "You attended a call with {user1} and {user2}"
	 *
	 * @param Room $room
	 * @return bool True if activity was generated, false otherwise
	 */
	public function generateCallActivity(Room $room): bool {
		$activeSince = $room->getActiveSince();
		if (!$activeSince instanceof \DateTime || $room->hasSessionsInCall()) {
			return false;
		}

		$duration = $this->timeFactory->getTime() - $activeSince->getTimestamp();
		$userIds = $room->getParticipantUserIds($activeSince);

		if ((\count($userIds) + $room->getActiveGuests()) === 1) {
			// Single user pinged or guests only => no summary/activity
			$room->resetActiveSince();
			return false;
		}

		$numGuests = $room->getActiveGuests();

		if (!$room->resetActiveSince()) {
			// Race-condition, the room was already reset.
			return false;
		}

		$actorId = $userIds[0] ?? 'guests-only';
		$actorType = $actorId !== 'guests-only' ? 'users' : 'guests';
		$this->chatManager->addSystemMessage($room, $actorType, $actorId, json_encode([
			'message' => 'call_ended',
			'parameters' => [
				'users' => $userIds,
				'guests' => $numGuests,
				'duration' => $duration,
			],
		]), $this->timeFactory->getDateTime(), false);

		if (empty($userIds)) {
			return false;
		}

		$event = $this->activityManager->generateEvent();
		try {
			$event->setApp('spreed')
				->setType('spreed')
				->setAuthor('')
				->setObject('room', $room->getId())
				->setTimestamp($this->timeFactory->getTime())
				->setSubject('call', [
					'room' => $room->getId(),
					'users' => $userIds,
					'guests' => $numGuests,
					'duration' => $duration,
				]);
		} catch (\InvalidArgumentException $e) {
			$this->logger->logException($e, ['app' => 'spreed']);
			return false;
		}

		foreach ($userIds as $userId) {
			try {
				$event->setAffectedUser($userId);
				$this->activityManager->publish($event);
			} catch (\BadMethodCallException $e) {
				$this->logger->logException($e, ['app' => 'spreed']);
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'spreed']);
			}
		}

		return true;
	}

	/**
	 * Invitation activity: "{actor} invited you to {call}"
	 *
	 * @param Room $room
	 * @param array[] $participants
	 */
	public function generateInvitationActivity(Room $room, array $participants): void {
		$actor = $this->userSession->getUser();
		if (!$actor instanceof IUser) {
			return;
		}
		$actorId = $actor->getUID();

		$event = $this->activityManager->generateEvent();
		try {
			$event->setApp('spreed')
				->setType('spreed')
				->setAuthor($actorId)
				->setObject('room', $room->getId())
				->setTimestamp($this->timeFactory->getTime())
				->setSubject('invitation', [
					'user' => $actor->getUID(),
					'room' => $room->getId(),
				]);
		} catch (\InvalidArgumentException $e) {
			$this->logger->logException($e, ['app' => 'spreed']);
			return;
		}

		foreach ($participants as $participant) {
			if ($actorId === $participant['userId']) {
				// No activity for self-joining and the creator
				continue;
			}

			try {
				$roomName = $room->getDisplayName($participant['userId']);
				$event
					->setObject('room', $room->getId(), $roomName)
					->setSubject('invitation', [
						'user' => $actor->getUID(),
						'room' => $room->getId(),
						'name' => $roomName,
					])
					->setAffectedUser($participant['userId']);
				$this->activityManager->publish($event);
			} catch (\InvalidArgumentException $e) {
				$this->logger->logException($e, ['app' => 'spreed']);
			} catch (\BadMethodCallException $e) {
				$this->logger->logException($e, ['app' => 'spreed']);
			}
		}
	}
}
