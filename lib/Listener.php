<?php
declare(strict_types=1);
/**
 * @author Joachim Bauch <mail@joachim-bauch.de>
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

namespace OCA\Spreed;

use OCP\Collaboration\AutoComplete\AutoCompleteEvent;
use OCP\Collaboration\AutoComplete\IManager;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Listener {

	/** @var Manager */
	protected $manager;
	/** @var IUserManager */
	protected $userManager;
	/** @var IUserSession */
	protected $userSession;
	/** @var TalkSession */
	protected $talkSession;
	/** @var Config */
	protected $config;
	/** @var string[] */
	protected $allowedGroupIds = [];

	public function __construct(Manager $manager,
								IUserManager $userManager,
								IUserSession $userSession,
								TalkSession $talkSession,
								Config $config) {
		$this->manager = $manager;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->talkSession = $talkSession;
		$this->config = $config;
	}

	public static function register(EventDispatcherInterface $dispatcher): void {
		\OC::$server->getUserManager()->listen('\OC\User', 'postDelete', function ($user) {
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);
			$listener->deleteUser($user);
		});

		Util::connectHook('OC_User', 'logout', self::class, 'logoutUserStatic');

		$dispatcher->addListener(IManager::class . '::filterResults', function(AutoCompleteEvent $event) {
			/** @var self $listener */
			$listener = \OC::$server->query(self::class);

			if ($event->getItemType() !== 'call') {
				return;
			}

			$event->setResults($listener->filterAutoCompletionResults($event->getResults()));
		});
	}

	/**
	 * @param IUser $user
	 */
	public function deleteUser(IUser $user): void {
		$rooms = $this->manager->getRoomsForParticipant($user->getUID());

		foreach ($rooms as $room) {
			if ($room->getNumberOfParticipants() === 1) {
				$room->deleteRoom();
			} else {
				$room->removeUser($user, Room::PARTICIPANT_REMOVED);
			}
		}
	}

	public static function logoutUserStatic(): void {
		/** @var self $listener */
		$listener = \OC::$server->query(self::class);
		$listener->logoutUser();
	}

	public function logoutUser(): void {
		/** @var IUser $user */
		$user = $this->userSession->getUser();

		$sessionIds = $this->talkSession->getAllActiveSessions();
		foreach ($sessionIds as $sessionId) {
			$room = $this->manager->getRoomForSession($user->getUID(), $sessionId);
			$participant = $room->getParticipant($user->getUID());
			if ($participant->getInCallFlags() !== Participant::FLAG_DISCONNECTED) {
				$room->changeInCall($sessionId, Participant::FLAG_DISCONNECTED);
			}
			$room->leaveRoom($user->getUID(), $sessionId);
		}
	}

	public function filterAutoCompletionResults(array $results): array {
		$this->allowedGroupIds = $this->config->getAllowedGroupIds();
		if (empty($this->allowedGroupIds)) {
			return $results;
		}

		if (!empty($results['groups'])) {
			$results['groups'] = array_filter($results['groups'], [$this, 'filterGroupResult']);
		}
		if (!empty($results['exact']['groups'])) {
			$results['exact']['groups'] = array_filter($results['exact']['groups'], [$this, 'filterGroupResult']);
		}

		if (!empty($results['users'])) {
			$results['users'] = array_filter($results['users'], [$this, 'filterUserResult']);
		}
		if (!empty($results['exact']['users'])) {
			$results['exact']['users'] = array_filter($results['exact']['users'], [$this, 'filterUserResult']);
		}

		return $results;
	}

	public function filterUserResult(array $result): bool {
		$user = $this->userManager->get($result['value']['shareWith']);
		return $user instanceof IUser && !$this->config->isDisabledForUser($user);
	}

	public function filterGroupResult(array $result): bool {
		return \in_array($result['value']['shareWith'], $this->allowedGroupIds, true);
	}
}
