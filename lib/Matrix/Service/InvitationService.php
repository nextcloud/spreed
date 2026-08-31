<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Service;

use Nextcloud\Matrix\Exception\MatrixException;
use Nextcloud\Matrix\Model\InvitedRoom;
use Nextcloud\Matrix\Util\Identifier;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Matrix\ClientFactory;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\Homeserver;
use OCA\Talk\Matrix\Model\MatrixRoom;
use OCA\Talk\Matrix\Model\MatrixRoomMapper;
use OCA\Talk\Matrix\Sync\CapabilityResolver;
use OCA\Talk\Model\Invitation;
use OCA\Talk\Model\InvitationMapper;
use OCA\Talk\Room;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUser;
use Psr\Log\LoggerInterface;

/**
 * Matrix invites reuse Talk's pending-invitation table and API so unchanged
 * clients list them next to federation invites. `remote_server_url` carries
 * the marker `matrix:<server_name>`, `remote_token` the Matrix room id.
 */
class InvitationService {
	public const REMOTE_PREFIX = 'matrix:';

	public function __construct(
		private readonly InvitationMapper $invitationMapper,
		private readonly MatrixRoomMapper $roomMapper,
		private readonly RoomService $roomService,
		private readonly Manager $manager,
		private readonly ClientFactory $clientFactory,
		private readonly CapabilityResolver $capabilities,
		private readonly IDBConnection $db,
		private readonly IL10N $l,
		private readonly LoggerInterface $logger,
	) {
	}

	public static function isMatrixInvitation(Invitation $invitation): bool {
		return str_starts_with($invitation->getRemoteServerUrl(), self::REMOTE_PREFIX);
	}

	/**
	 * Store a pending invitation for an invite seen in /sync.
	 * @return bool true when a new invitation was created
	 */
	public function handleInvite(Account $account, Homeserver $homeserver, InvitedRoom $invite): bool {
		if ($this->find($account, $invite->roomId) !== null) {
			return false;
		}
		$state = $invite->getState();
		if ($state->isSpace()) {
			return false;
		}
		$inviter = $invite->getInviter($account->getMxid());
		$inviterName = $state->getMember($inviter)?->getName() ?? $inviter;

		// The conversation is created up-front (without attendees) so the invitation can point at it
		$matrixRoom = null;
		try {
			$matrixRoom = $this->roomMapper->getByMatrixRoomId($invite->roomId);
			$room = $this->manager->getRoomById($matrixRoom->getRoomId());
		} catch (DoesNotExistException|RoomNotFoundException) {
			$name = $state->name ?? $state->canonicalAlias ?? ($invite->isDirect($account->getMxid()) ? $inviterName : $this->l->t('Matrix room'));
			$room = $this->roomService->createConversation(Room::TYPE_GROUP, mb_substr($name, 0, 255), null, Room::OBJECT_TYPE_MATRIX, $invite->roomId);
			$this->roomService->setDefaultPermissions($room, $this->capabilities->roomDefaultPermissions());
			if ($matrixRoom === null) {
				$matrixRoom = new MatrixRoom();
				$matrixRoom->setMatrixRoomId($invite->roomId);
				$matrixRoom->setRoomId($room->getId());
				$matrixRoom->setIsDirect($invite->isDirect($account->getMxid()));
				$matrixRoom->setCreator($state->creator);
				$matrixRoom->setEncrypted($state->isEncrypted());
				$matrixRoom->setCapabilitiesArray($this->capabilities->forRoom($state, $homeserver, $matrixRoom->getIsDirect()));
				$matrixRoom = $this->roomMapper->insert($matrixRoom);
			} else {
				$matrixRoom->setRoomId($room->getId());
				$this->roomMapper->update($matrixRoom);
			}
		}

		$invitation = new Invitation();
		$invitation->setUserId($account->getUserId());
		$invitation->setState(Invitation::STATE_PENDING);
		$invitation->setLocalRoomId($room->getId());
		$invitation->setAccessToken('matrix');
		$invitation->setRemoteServerUrl(self::REMOTE_PREFIX . $homeserver->getServerName());
		$invitation->setRemoteToken($invite->roomId);
		$invitation->setRemoteAttendeeId(0);
		$invitation->setInviterCloudId($inviter);
		$invitation->setInviterDisplayName(mb_substr($inviterName, 0, 255));
		$invitation->setLocalCloudId($account->getMxid());
		$this->invitationMapper->insert($invitation);
		return true;
	}

	/**
	 * Accept from Talk: join on Matrix; the room shows up with the next sync (forced by the caller).
	 * @throws MatrixException
	 */
	public function accept(IUser $user, Account $account, Invitation $invitation): string {
		$client = $this->clientFactory->forAccount($account, 20);
		$via = [Identifier::serverName($invitation->getInviterCloudId())];
		$roomId = $client->join($invitation->getRemoteToken(), array_filter($via));
		$invitation->setState(Invitation::STATE_ACCEPTED);
		$this->invitationMapper->update($invitation);
		return $roomId !== '' ? $roomId : $invitation->getRemoteToken();
	}

	/**
	 * Reject from Talk: leave on Matrix and drop the invitation (+ the empty stub room).
	 * @throws MatrixException
	 */
	public function reject(Account $account, Invitation $invitation): void {
		$client = $this->clientFactory->forAccount($account, 20);
		try {
			$client->leave($invitation->getRemoteToken());
		} catch (\Nextcloud\Matrix\Exception\NotFoundException|\Nextcloud\Matrix\Exception\ForbiddenException) {
			// already gone
		}
		$this->invitationMapper->delete($invitation);
		$this->cleanupStub($invitation->getRemoteToken());
	}

	/** The account joined the room (via Talk or any other client). */
	public function markAccepted(Account $account, string $matrixRoomId, Room $room): void {
		$invitation = $this->find($account, $matrixRoomId);
		if ($invitation === null) {
			return;
		}
		$this->invitationMapper->delete($invitation);
	}

	/** The invite was declined elsewhere or retracted. */
	public function markRejected(Account $account, string $matrixRoomId): void {
		$invitation = $this->find($account, $matrixRoomId);
		if ($invitation === null) {
			return;
		}
		$this->invitationMapper->delete($invitation);
		$this->cleanupStub($matrixRoomId);
	}

	public function find(Account $account, string $matrixRoomId): ?Invitation {
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('talk_invitations')
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($account->getUserId())))
			->andWhere($qb->expr()->eq('remote_token', $qb->createNamedParameter($matrixRoomId)))
			->andWhere($qb->expr()->like('remote_server_url', $qb->createNamedParameter(self::REMOTE_PREFIX . '%')))
			->setMaxResults(1);
		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();
		if ($row === false) {
			return null;
		}
		return Invitation::fromRow($row);
	}

	/**
	 * A stub conversation (no attendees, no other pending invitations) is deleted again.
	 */
	private function cleanupStub(string $matrixRoomId): void {
		try {
			$matrixRoom = $this->roomMapper->getByMatrixRoomId($matrixRoomId);
			$room = $this->manager->getRoomById($matrixRoom->getRoomId());
		} catch (DoesNotExistException|RoomNotFoundException) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'num'))
			->from('talk_invitations')
			->where($qb->expr()->eq('local_room_id', $qb->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$pending = (int)$qb->executeQuery()->fetchOne();
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('*', 'num'))
			->from('talk_attendees')
			->where($qb->expr()->eq('room_id', $qb->createNamedParameter($room->getId(), IQueryBuilder::PARAM_INT)));
		$attendees = (int)$qb->executeQuery()->fetchOne();
		if ($pending === 0 && $attendees === 0) {
			$this->roomMapper->delete($matrixRoom);
			$this->roomService->deleteRoom($room);
		}
	}
}
