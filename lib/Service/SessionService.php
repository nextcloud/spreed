<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Session;
use OCA\Talk\Model\SessionMapper;
use OCA\Talk\Participant;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Security\ISecureRandom;

class SessionService {

	public function __construct(
		protected SessionMapper $sessionMapper,
		protected IDBConnection $connection,
		protected ISecureRandom $secureRandom,
		protected ITimeFactory $timeFactory,
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
		$session->setLastPing($this->timeFactory->getTime());

		if ($forceSessionId !== '') {
			$session->setSessionId($forceSessionId);
			$this->sessionMapper->insert($session);
		} else {
			while (true) {
				$sessionId = $this->secureRandom->generate(255);
				if (!empty($attendee->getInvitedCloudId())) {
					$sessionId = $this->extendSessionIdWithCloudId($sessionId, $attendee->getInvitedCloudId());
				}
				$session->setSessionId($sessionId);
				try {
					$this->sessionMapper->insert($session);
					break;
				} catch (Exception $e) {
					// 255 chars are not unique? Try again...
					if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
						throw $e;
					}
				}
			}
		}

		return $session;
	}

	/**
	 * Adds the given cloud id to the given session id.
	 *
	 * The session id and the cloud id are separated by '#'.
	 *
	 * If the resulting session id is longer than the column length it is
	 * trimmed at the end as needed.
	 *
	 * @param string $sessionId
	 * @param string $invitedCloudId
	 * @return string
	 */
	public function extendSessionIdWithCloudId(string $sessionId, string $invitedCloudId): string {
		// Session id column length is 512, while generated session ids are 255
		// characters.
		$invitedCloudId = substr($invitedCloudId, 0, 256);

		return $sessionId . '#' . $invitedCloudId;
	}
}
