<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2020 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Service;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Participant;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;

class SessionService {

	public function __construct(
		protected SessionMapper $sessionMapper,
		protected IDBConnection $connection,
		protected ISecureRandom $secureRandom,
	) {
	}

	/**
	 * Update last ping for multiple sessions
	 *
	 * Since this function is called by the HPB with potentially hundreds of
	 * sessions, we do not use the SessionMapper to get the entities first, as
	 * that would just not scale good enough.
	 *
	 * @param string[] $sessionIds
	 * @param int $lastPing
	 */
	public function updateMultipleLastPings(array $sessionIds, int $lastPing): void {
		$update = $this->connection->getQueryBuilder();
		$update->update('talk_sessions')
			->set('last_ping', $update->createNamedParameter($lastPing, IQueryBuilder::PARAM_INT))
			->where($update->expr()->in('session_id', $update->createNamedParameter($sessionIds, IQueryBuilder::PARAM_STR_ARRAY)));

		$update->executeStatement();
	}

	public function updateLastPing(Session $session, int $lastPing): void {
		$session->setLastPing($lastPing);
		$this->sessionMapper->update($session);
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function updateSessionState(Session $session, int $state): void {
		if (!in_array($state, [Session::STATE_INACTIVE, Session::STATE_ACTIVE], true)) {
			throw new \InvalidArgumentException('state');
		}

		$session->setState($state);
		$this->sessionMapper->update($session);
	}

	/**
	 * @param int[] $ids
	 */
	public function deleteSessionsById(array $ids): void {
		$this->sessionMapper->deleteByIds($ids);
	}

	/**
	 * @param Attendee $attendee
	 * @return Session[]
	 */
	public function getAllSessionsForAttendee(Attendee $attendee): array {
		return $this->sessionMapper->findByAttendeeId($attendee->getId());
	}

	/**
	 * @param Attendee $attendee
	 * @param string $forceSessionId
	 * @return Session
	 * @throws Exception
	 */
	public function createSessionForAttendee(Attendee $attendee, string $forceSessionId = ''): Session {
		$session = new Session();
		$session->setAttendeeId($attendee->getId());
		$session->setInCall(Participant::FLAG_DISCONNECTED);

		if ($forceSessionId !== '') {
			$session->setSessionId($forceSessionId);
			$this->sessionMapper->insert($session);
		} else {
			while (true) {
				$sessionId = $this->secureRandom->generate(255);
				$session->setSessionId($sessionId);
				try {
					$this->sessionMapper->insert($session);
					break;
				} catch (Exception $e) {
					// 255 chars are not unique? Try again...
					if ($e->getReason() === Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
						throw $e;
					}
				}
			}
		}

		return $session;
	}
}
