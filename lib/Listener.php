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

namespace OCA\Talk;

use OCP\EventDispatcher\IEventDispatcher;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Util;

class Listener {

	/** @var Manager */
	protected $manager;
	/** @var IUserSession */
	protected $userSession;
	/** @var TalkSession */
	protected $talkSession;

	public function __construct(Manager $manager,
								IUserSession $userSession,
								TalkSession $talkSession) {
		$this->manager = $manager;
		$this->userSession = $userSession;
		$this->talkSession = $talkSession;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		Util::connectHook('OC_User', 'logout', self::class, 'logoutUserStatic');
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
				$room->changeInCall($participant, Participant::FLAG_DISCONNECTED);
			}
			$room->leaveRoom($user->getUID(), $sessionId);
		}
	}
}
