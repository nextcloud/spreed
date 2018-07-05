<?php
declare(strict_types=1);

/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

namespace OCA\Spreed\Share;

use OCA\Spreed\Exceptions\ParticipantNotFoundException;
use OCA\Spreed\Exceptions\RoomNotFoundException;
use OCA\Spreed\Manager;
use OCA\Spreed\Room;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\IUserManager;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IManager as IShareManager;
use OCP\Share\IShare;
use OCP\Share\IShareProvider;

/**
 * Share provider for room shares.
 *
 * Files are shared with a room identified by its token; only users currently in
 * the room can share with and access the shared files (although the access
 * checks are not enforced by the provider, but done on a higher layer).
 *
 * Like in group shares, a recipient can move or delete a share without
 * modifying the share for the other users in the room.
 */
class RoomShareProvider implements IShareProvider {

	// Special share type for user modified room shares
	const SHARE_TYPE_USERROOM = 11;

	/** @var IDBConnection */
	private $dbConnection;

	/** @var IUserManager */
	private $userManager;

	/** @var IShareManager */
	private $shareManager;

	/** @var IRootFolder */
	private $rootFolder;

	/** @var IL10N */
	private $l;

	/** @var Manager */
	private $manager;

	/**
	 * RoomShareProvider constructor.
	 *
	 * @param IDBConnection $connection
	 * @param IUserManager $userManager
	 * @param IShareManager $shareManager
	 * @param IRootFolder $rootFolder
	 * @param IL10N $l10n
	 * @param Manager $manager
	 */
	public function __construct(
			IDBConnection $connection,
			IUserManager $userManager,
			IShareManager $shareManager,
			IRootFolder $rootFolder,
			IL10N $l,
			Manager $manager
	) {
		$this->dbConnection = $connection;
		$this->userManager = $userManager;
		$this->shareManager = $shareManager;
		$this->rootFolder = $rootFolder;
		$this->l = $l;
		$this->manager = $manager;
	}

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
	public function identifier() {
		return 'ocRoomShare';
	}

	/**
	 * Create a share
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 * @throws GenericShareException
	 */
	public function create(IShare $share) {
		try {
			$room = $this->manager->getRoomByToken($share->getSharedWith());
		} catch (RoomNotFoundException $e) {
			throw new GenericShareException("Room not found", $this->l->t('Conversation not found'), 404);
		}

		try {
			$room->getParticipant($share->getSharedBy());
		} catch (ParticipantNotFoundException $e) {
			// If the sharer is not a participant of the room even if the room
			// exists the error is still "Room not found".
			throw new GenericShareException("Room not found", $this->l->t('Conversation not found'), 404);
		}

		$existingShares = $this->getSharesByPath($share->getNode());
		foreach ($existingShares as $existingShare) {
			if ($existingShare->getSharedWith() === $share->getSharedWith()) {
				throw new GenericShareException("Already shared", $this->l->t('Path is already shared with this room'), 403);
			}
		}

		$shareId = $this->addShareToDB(
			$share->getSharedWith(),
			$share->getSharedBy(),
			$share->getShareOwner(),
			$share->getNodeType(),
			$share->getNodeId(),
			$share->getTarget(),
			$share->getPermissions(),
			$share->getExpirationDate()
		);

		$data = $this->getRawShare($shareId);

		return $this->createShareObject($data);
	}

	/**
	 * Add share to the database and return the ID
	 *
	 * @param string $shareWith
	 * @param string $sharedBy
	 * @param string $shareOwner
	 * @param string $itemType
	 * @param int $itemSource
	 * @param string $target
	 * @param int $permissions
	 * @param \DateTime|null $expirationDate
	 * @return int
	 */
	private function addShareToDB(
			string $shareWith,
			string $sharedBy,
			string $shareOwner,
			string $itemType,
			int $itemSource,
			string $target,
			int $permissions,
			\DateTime $expirationDate = null
	): int {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_ROOM))
			->setValue('share_with', $qb->createNamedParameter($shareWith))
			->setValue('uid_initiator', $qb->createNamedParameter($sharedBy))
			->setValue('uid_owner', $qb->createNamedParameter($shareOwner))
			->setValue('item_type', $qb->createNamedParameter($itemType))
			->setValue('item_source', $qb->createNamedParameter($itemSource))
			->setValue('file_source', $qb->createNamedParameter($itemSource))
			->setValue('file_target', $qb->createNamedParameter($target))
			->setValue('permissions', $qb->createNamedParameter($permissions))
			->setValue('stime', $qb->createNamedParameter(time()));

		if ($expirationDate !== null) {
			$qb->setValue('expiration', $qb->createNamedParameter($expirationDate, 'datetime'));
		}

		$qb->execute();
		$id = $qb->getLastInsertId();

		return (int)$id;
	}

	/**
	 * Get database row of the given share
	 *
	 * @param int $id
	 * @return array
	 * @throws ShareNotFound
	 */
	private function getRawShare(int $id) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		return $data;
	}

	/**
	 * Create a share object from a database row
	 *
	 * @param array $data
	 * @return IShare
	 */
	private function createShareObject($data) {
		$share = $this->shareManager->newShare();
		$share->setId((int)$data['id'])
			->setShareType((int)$data['share_type'])
			->setPermissions((int)$data['permissions'])
			->setTarget($data['file_target']);

		$shareTime = new \DateTime();
		$shareTime->setTimestamp((int)$data['stime']);
		$share->setShareTime($shareTime);
		$share->setSharedWith($data['share_with']);

		$share->setSharedBy($data['uid_initiator']);
		$share->setShareOwner($data['uid_owner']);

		if ($data['expiration'] !== null) {
			$expiration = \DateTime::createFromFormat('Y-m-d H:i:s', $data['expiration']);
			if ($expiration !== false) {
				$share->setExpirationDate($expiration);
			}
		}

		$share->setNodeId((int)$data['file_source']);
		$share->setNodeType($data['item_type']);

		$share->setProviderId($this->identifier());

		return $share;
	}

	/**
	 * Update a share
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 */
	public function update(IShare $share) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())))
			->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
			->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
			->set('permissions', $qb->createNamedParameter($share->getPermissions()))
			->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
			->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
			->set('expiration', $qb->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
			->execute();

		/*
		 * Update all user defined group shares
		 */
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
			->set('uid_owner', $qb->createNamedParameter($share->getShareOwner()))
			->set('uid_initiator', $qb->createNamedParameter($share->getSharedBy()))
			->set('item_source', $qb->createNamedParameter($share->getNode()->getId()))
			->set('file_source', $qb->createNamedParameter($share->getNode()->getId()))
			->set('expiration', $qb->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
			->execute();

		/*
		 * Now update the permissions for all children that have not set it to 0
		 */
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
			->andWhere($qb->expr()->neq('permissions', $qb->createNamedParameter(0)))
			->set('permissions', $qb->createNamedParameter($share->getPermissions()))
			->execute();

		return $share;
	}

	/**
	 * Delete a share
	 *
	 * @param IShare $share
	 */
	public function delete(IShare $share) {
		throw new \Exception("Not implemented");
	}

	/**
	 * Unshare a file from self as recipient.
	 *
	 * If a user unshares a room share from their self then the original room
	 * share should still exist.
	 *
	 * @param IShare $share
	 * @param string $recipient UserId of the recipient
	 */
	public function deleteFromSelf(IShare $share, $recipient) {
		throw new \Exception("Not implemented");
	}

	/**
	 * Restore a share for a given recipient. The implementation could be provider independant.
	 *
	 * @param IShare $share
	 * @param string $recipient
	 * @return IShare The restored share object
	 * @throws GenericShareException In case the share could not be restored
	 */
	public function restore(IShare $share, string $recipient): IShare {
		throw new \Exception("Not implemented");
	}

	/**
	 * Move a share as a recipient.
	 *
	 * This is updating the share target. Thus the mount point of the recipient.
	 * This may require special handling. If a user moves a room share
	 * the target should only be changed for them.
	 *
	 * @param IShare $share
	 * @param string $recipient userId of recipient
	 * @return IShare
	 */
	public function move(IShare $share, $recipient) {
		// Check if there is a userroom share
		$qb = $this->dbConnection->getQueryBuilder();
		$stmt = $qb->select('id')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_USERROOM)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($recipient)))
			->andWhere($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->setMaxResults(1)
			->execute();

		$data = $stmt->fetch();
		$stmt->closeCursor();

		if ($data === false) {
			// No userroom share yet. Create one.
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->insert('share')
				->values([
					'share_type' => $qb->createNamedParameter(self::SHARE_TYPE_USERROOM),
					'share_with' => $qb->createNamedParameter($recipient),
					'uid_owner' => $qb->createNamedParameter($share->getShareOwner()),
					'uid_initiator' => $qb->createNamedParameter($share->getSharedBy()),
					'parent' => $qb->createNamedParameter($share->getId()),
					'item_type' => $qb->createNamedParameter($share->getNodeType()),
					'item_source' => $qb->createNamedParameter($share->getNodeId()),
					'file_source' => $qb->createNamedParameter($share->getNodeId()),
					'file_target' => $qb->createNamedParameter($share->getTarget()),
					'permissions' => $qb->createNamedParameter($share->getPermissions()),
					'stime' => $qb->createNamedParameter($share->getShareTime()->getTimestamp()),
				])->execute();
		} else {
			// Already a userroom share. Update it.
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->update('share')
				->set('file_target', $qb->createNamedParameter($share->getTarget()))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($data['id'])))
				->execute();
		}

		return $share;
	}

	/**
	 * Get all shares by the given user in a folder
	 *
	 * @param string $userId
	 * @param Folder $node
	 * @param bool $reshares Also get the shares where $user is the owner instead of just the shares where $user is the initiator
	 * @return IShare[]
	 */
	public function getSharesInFolder($userId, Folder $node, $reshares) {
		throw new \Exception("Not implemented");
	}

	/**
	 * Get all shares by the given user
	 *
	 * @param string $userId
	 * @param int $shareType
	 * @param Node|null $node
	 * @param bool $reshares Also get the shares where $user is the owner instead of just the shares where $user is the initiator
	 * @param int $limit The maximum number of shares to be returned, -1 for all shares
	 * @param int $offset
	 * @return IShare[]
	 */
	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset) {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_ROOM)));

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId)));
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		if ($node !== null) {
			$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
		}

		if ($limit !== -1) {
			$qb->setMaxResults($limit);
		}

		$qb->setFirstResult($offset);
		$qb->orderBy('id');

		$cursor = $qb->execute();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * Get share by id
	 *
	 * @param int $id
	 * @param string|null $recipientId
	 * @return IShare
	 * @throws ShareNotFound
	 */
	public function getShareById($id, $recipientId = null) {
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_ROOM)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		$share = $this->createShareObject($data);

		if ($recipientId !== null) {
			$share = $this->resolveSharesForRecipient([$share], $recipientId)[0];
		}

		return $share;
	}

	/**
	 * Returns each given share as seen by the given recipient.
	 *
	 * If the recipient has not modified the share the original one is returned
	 * instead.
	 *
	 * @param Share[] $shares
	 * @param string $userId
	 * @return Share[]
	 */
	private function resolveSharesForRecipient(array $shares, string $userId): array {
		$result = [];

		$start = 0;
		while (true) {
			/** @var Share[] $shareSlice */
			$shareSlice = array_slice($shares, $start, 100);
			$start += 100;

			if ($shareSlice === []) {
				break;
			}

			/** @var int[] $ids */
			$ids = [];
			/** @var Share[] $shareMap */
			$shareMap = [];

			foreach ($shareSlice as $share) {
				$ids[] = (int)$share->getId();
				$shareMap[$share->getId()] = $share;
			}

			$qb = $this->dbConnection->getQueryBuilder();

			$query = $qb->select('*')
				->from('share')
				->where($qb->expr()->in('parent', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($userId)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
				));

			$stmt = $query->execute();

			while ($data = $stmt->fetch()) {
				$shareMap[$data['parent']]->setPermissions((int)$data['permissions']);
				$shareMap[$data['parent']]->setTarget($data['file_target']);
			}

			$stmt->closeCursor();

			foreach ($shareMap as $share) {
				$result[] = $share;
			}
		}

		return $result;
	}

	/**
	 * Get shares for a given path
	 *
	 * @param Node $path
	 * @return IShare[]
	 */
	public function getSharesByPath(Node $path) {
		$qb = $this->dbConnection->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($path->getId())))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_ROOM)))
			->execute();

		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
	}

	/**
	 * Get shared with the given user
	 *
	 * @param string $userId get shares where this user is the recipient
	 * @param int $shareType
	 * @param Node|null $node
	 * @param int $limit The max number of entries returned, -1 for all
	 * @param int $offset
	 * @return IShare[]
	 */
	public function getSharedWith($userId, $shareType, $node, $limit, $offset) {
		$allRooms = $this->manager->getRoomsForParticipant($userId);

		/** @var IShare[] $shares */
		$shares = [];

		$start = 0;
		while (true) {
			$rooms = array_slice($allRooms, $start, 100);
			$start += 100;

			if ($rooms === []) {
				break;
			}

			$qb = $this->dbConnection->getQueryBuilder();
			$qb->select('*')
				->from('share')
				->orderBy('id')
				->setFirstResult(0);

			if ($limit !== -1) {
				$qb->setMaxResults($limit);
			}

			// Filter by node if provided
			if ($node !== null) {
				$qb->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($node->getId())));
			}

			$rooms = array_map(function(Room $room) { return $room->getToken(); }, $rooms);

			$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(\OCP\Share::SHARE_TYPE_ROOM)))
				->andWhere($qb->expr()->in('share_with', $qb->createNamedParameter(
					$rooms,
					IQueryBuilder::PARAM_STR_ARRAY
				)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
				));

			$cursor = $qb->execute();
			while ($data = $cursor->fetch()) {
				if ($offset > 0) {
					$offset--;
					continue;
				}

				$shares[] = $this->createShareObject($data);
			}
			$cursor->closeCursor();
		}

		$shares = $this->resolveSharesForRecipient($shares, $userId);

		return $shares;
	}

	/**
	 * Get a share by token
	 *
	 * Note that token here refers to share token, not room token.
	 *
	 * @param string $token
	 * @return IShare
	 * @throws ShareNotFound
	 */
	public function getShareByToken($token) {
		throw new \Exception("Not implemented");
	}

	/**
	 * A user is deleted from the system
	 * So clean up the relevant shares.
	 *
	 * @param string $uid
	 * @param int $shareType
	 */
	public function userDeleted($uid, $shareType) {
		throw new \Exception("Not implemented");
	}

	/**
	 * A group is deleted from the system.
	 * We have to clean up all shares to this group.
	 * Providers not handling group shares should just return
	 *
	 * @param string $gid
	 */
	public function groupDeleted($gid) {
		throw new \Exception("Not implemented");
	}

	/**
	 * A user is deleted from a group
	 * We have to clean up all the related user specific group shares
	 * Providers not handling group shares should just return
	 *
	 * @param string $uid
	 * @param string $gid
	 */
	public function userDeletedFromGroup($uid, $gid) {
		throw new \Exception("Not implemented");
	}

	/**
	 * Get the access list to the array of provided nodes.
	 *
	 * @see IManager::getAccessList() for sample docs
	 *
	 * @param Node[] $nodes The list of nodes to get access for
	 * @param bool $currentAccess If current access is required (like for removed shares that might get revived later)
	 * @return array
	 */
	public function getAccessList($nodes, $currentAccess) {
		throw new \Exception("Not implemented");
	}

}
