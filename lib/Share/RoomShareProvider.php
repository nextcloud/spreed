<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Share;

use OC\Files\Cache\Cache;
use OCA\Talk\Events\BeforeDuplicateShareSentEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomService;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Cache\CappedMemoryCache;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeLoader;
use OCP\Files\Node;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\Security\ISecureRandom;
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
	use TTransactional;
	// Special share type for user modified room shares
	public const SHARE_TYPE_USERROOM = 11;

	public const TALK_FOLDER = '/Talk';
	public const TALK_FOLDER_PLACEHOLDER = '/{TALK_PLACEHOLDER}';

	private CappedMemoryCache $sharesByIdCache;

	public function __construct(
		private IDBConnection $dbConnection,
		private ISecureRandom $secureRandom,
		private IShareManager $shareManager,
		private IEventDispatcher $dispatcher,
		private Manager $manager,
		private ParticipantService $participantService,
		protected RoomService $roomService,
		protected ITimeFactory $timeFactory,
		private IL10N $l,
		private IMimeTypeLoader $mimeTypeLoader,
	) {
		$this->sharesByIdCache = new CappedMemoryCache();
	}

	/*
	 * Clean sharesByIdCache
	 */
	private function cleanSharesByIdCache(): void {
		$this->sharesByIdCache = new CappedMemoryCache();
	}

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
	#[\Override]
	public function identifier(): string {
		return 'ocRoomShare';
	}

	/**
	 * Create a share
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 * @throws GenericShareException
	 */
	#[\Override]
	public function create(IShare $share): IShare {
		try {
			$room = $this->manager->getRoomByToken($share->getSharedWith(), $share->getSharedBy());
		} catch (RoomNotFoundException $e) {
			throw new GenericShareException('Room not found', $this->l->t('Conversation not found'), 404);
		}

		if ($room->getReadOnly() === Room::READ_ONLY) {
			throw new GenericShareException('Room not found', $this->l->t('Conversation not found'), 404);
		}

		if ($room->isFederatedConversation()) {
			throw new GenericShareException('Room not found', $this->l->t('Conversation not found'), 404);
		}

		try {
			$participant = $this->participantService->getParticipant($room, $share->getSharedBy(), false);
		} catch (ParticipantNotFoundException $e) {
			// If the sharer is not a participant of the room even if the room
			// exists the error is still "Room not found".
			throw new GenericShareException('Room not found', $this->l->t('Conversation not found'), 404);
		}

		if (!($participant->getPermissions() & Attendee::PERMISSIONS_CHAT)) {
			// No chat permissions is like read-only
			throw new GenericShareException('Room not found', $this->l->t('Conversation not found'), 404);
		}

		$existingShares = $this->getSharesByPath($share->getNode());
		foreach ($existingShares as $existingShare) {
			if ($existingShare->getSharedWith() === $share->getSharedWith()) {
				$event = new BeforeDuplicateShareSentEvent($existingShare);
				$this->dispatcher->dispatchTyped($event);
				throw new GenericShareException('Already shared', $this->l->t('Path is already shared with this conversation'), 403);
			}
		}

		$share->setToken(
			$this->secureRandom->generate(
				15, // \OC\Share\Constants::TOKEN_LENGTH
				ISecureRandom::CHAR_HUMAN_READABLE
			)
		);

		$data = $this->atomic(function () use ($share) {
			$shareId = $this->addShareToDB(
				$share->getSharedWith(),
				$share->getSharedBy(),
				$share->getShareOwner(),
				$share->getNodeType(),
				$share->getNodeId(),
				$share->getTarget(),
				$share->getPermissions(),
				$share->getToken(),
				$share->getExpirationDate()
			);

			return $this->getRawShare($shareId);
		}, $this->dbConnection);

		$this->roomService->setHasAttachments($room);

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
	 * @param string $token
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
		string $token,
		?\DateTime $expirationDate,
	): int {
		$insert = $this->dbConnection->getQueryBuilder();
		$insert->insert('share')
			->setValue('share_type', $insert->createNamedParameter(IShare::TYPE_ROOM))
			->setValue('share_with', $insert->createNamedParameter($shareWith))
			->setValue('uid_initiator', $insert->createNamedParameter($sharedBy))
			->setValue('uid_owner', $insert->createNamedParameter($shareOwner))
			->setValue('item_type', $insert->createNamedParameter($itemType))
			->setValue('item_source', $insert->createNamedParameter($itemSource))
			->setValue('file_source', $insert->createNamedParameter($itemSource))
			->setValue('file_target', $insert->createNamedParameter($target))
			->setValue('permissions', $insert->createNamedParameter($permissions))
			->setValue('token', $insert->createNamedParameter($token))
			->setValue('stime', $insert->createNamedParameter($this->timeFactory->getTime()));

		if ($expirationDate !== null) {
			$insert->setValue('expiration', $insert->createNamedParameter($expirationDate, 'datetime'));
		}

		$insert->executeStatement();
		$id = $insert->getLastInsertId();

		return $id;
	}

	/**
	 * Get database row of the given share
	 *
	 * @param int $id
	 * @return array
	 * @throws ShareNotFound
	 */
	private function getRawShare(int $id): array {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

		$cursor = $qb->executeQuery();
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
	private function createShareObject(array $data): IShare {
		$share = $this->shareManager->newShare();
		$share->setId($data['id'])
			->setShareType((int)$data['share_type'])
			->setPermissions((int)$data['permissions'])
			->setTarget($data['file_target'])
			->setStatus((int)$data['accepted'])
			->setToken($data['token']);

		$shareTime = $this->timeFactory->getDateTime();
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

		if (isset($data['f_permissions'])) {
			$entryData = $data;
			$entryData['permissions'] = $entryData['f_permissions'];
			$entryData['parent'] = $entryData['f_parent'];
			$share->setNodeCacheEntry(Cache::cacheEntryFromData($entryData,
				$this->mimeTypeLoader));
		}

		return $share;
	}

	/**
	 * Update a share
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 */
	#[\Override]
	public function update(IShare $share): IShare {
		$this->cleanSharesByIdCache();

		$update = $this->dbConnection->getQueryBuilder();
		$update->update('share')
			->where($update->expr()->eq('id', $update->createNamedParameter($share->getId())))
			->set('uid_owner', $update->createNamedParameter($share->getShareOwner()))
			->set('uid_initiator', $update->createNamedParameter($share->getSharedBy()))
			->set('permissions', $update->createNamedParameter($share->getPermissions()))
			->set('item_source', $update->createNamedParameter($share->getNode()->getId()))
			->set('file_source', $update->createNamedParameter($share->getNode()->getId()))
			->set('expiration', $update->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
			->executeStatement();

		/*
		 * Update all user defined group shares
		 */
		$update = $this->dbConnection->getQueryBuilder();
		$update->update('share')
			->where($update->expr()->eq('parent', $update->createNamedParameter($share->getId())))
			->set('uid_owner', $update->createNamedParameter($share->getShareOwner()))
			->set('uid_initiator', $update->createNamedParameter($share->getSharedBy()))
			->set('item_source', $update->createNamedParameter($share->getNode()->getId()))
			->set('file_source', $update->createNamedParameter($share->getNode()->getId()))
			->set('expiration', $update->createNamedParameter($share->getExpirationDate(), IQueryBuilder::PARAM_DATE))
			->executeStatement();

		/*
		 * Now update the permissions for all children that have not set it to 0
		 */
		$update = $this->dbConnection->getQueryBuilder();
		$update->update('share')
			->where($update->expr()->eq('parent', $update->createNamedParameter($share->getId())))
			->andWhere($update->expr()->neq('permissions', $update->createNamedParameter(0)))
			->set('permissions', $update->createNamedParameter($share->getPermissions()))
			->executeStatement();

		return $share;
	}

	/**
	 * Delete a share
	 *
	 * @param IShare $share
	 */
	#[\Override]
	public function delete(IShare $share): void {
		$this->cleanSharesByIdCache();

		$delete = $this->dbConnection->getQueryBuilder();
		$delete->delete('share')
			->where($delete->expr()->eq('id', $delete->createNamedParameter($share->getId())));

		$delete->orWhere($delete->expr()->eq('parent', $delete->createNamedParameter($share->getId())));

		$delete->executeStatement();
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
	#[\Override]
	public function deleteFromSelf(IShare $share, $recipient): void {
		$this->cleanSharesByIdCache();

		// Check if there is a userroom share
		$qb = $this->dbConnection->getQueryBuilder();
		$stmt = $qb->select(['id', 'permissions'])
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_USERROOM)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($recipient)))
			->andWhere($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			))
			->executeQuery();

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
					'permissions' => $qb->createNamedParameter(0),
					'stime' => $qb->createNamedParameter($share->getShareTime()->getTimestamp()),
				])->executeStatement();
		} elseif ($data['permissions'] !== 0) {
			// Already a userroom share. Update it.
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->update('share')
				->set('permissions', $qb->createNamedParameter(0))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($data['id'])))
				->executeStatement();
		}
	}

	/**
	 * Restore a share for a given recipient. The implementation could be provider independant.
	 *
	 * @param IShare $share
	 * @param string $recipient
	 * @return IShare The restored share object
	 * @throws GenericShareException In case the share could not be restored
	 */
	#[\Override]
	public function restore(IShare $share, string $recipient): IShare {
		$this->cleanSharesByIdCache();

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('permissions')
			->from('share')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($share->getId()))
			);
		$cursor = $qb->executeQuery();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		$originalPermission = $data['permissions'];

		$update = $this->dbConnection->getQueryBuilder();
		$update->update('share')
			->set('permissions', $update->createNamedParameter($originalPermission))
			->where(
				$update->expr()->eq('parent', $update->createNamedParameter($share->getId()))
			)->andWhere(
				$update->expr()->eq('share_type', $update->createNamedParameter(self::SHARE_TYPE_USERROOM))
			)->andWhere(
				$update->expr()->eq('share_with', $update->createNamedParameter($recipient))
			);

		$update->executeStatement();

		return $this->getShareById((int)$share->getId(), $recipient);
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
	#[\Override]
	public function move(IShare $share, $recipient): IShare {
		$this->cleanSharesByIdCache();

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
			->executeQuery();

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
				])->executeStatement();
		} else {
			// Already a userroom share. Update it.
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->update('share')
				->set('file_target', $qb->createNamedParameter($share->getTarget()))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($data['id'])))
				->executeStatement();
		}

		return $share;
	}

	/**
	 * Get all shares by the given user in a folder
	 *
	 * @param string $userId
	 * @param Folder $node
	 * @param bool $reshares Also get the shares where $user is the owner instead of just the shares where $user is the initiator
	 * @param bool $shallow Whether the method should stop at the first level, or look into sub-folders.
	 * @return IShare[][]
	 * @psalm-return array<array-key, non-empty-list<IShare>>
	 */
	#[\Override]
	public function getSharesInFolder($userId, Folder $node, $reshares, $shallow = true): array {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('s.*',
			'f.fileid', 'f.path', 'f.permissions AS f_permissions', 'f.storage', 'f.path_hash',
			'f.parent AS f_parent', 'f.name', 'f.mimetype', 'f.mimepart', 'f.size', 'f.mtime', 'f.storage_mtime',
			'f.encrypted', 'f.unencrypted_size', 'f.etag', 'f.checksum'
		)
			->from('share', 's')
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('s.item_type', $qb->createNamedParameter('folder'))
			))
			->andWhere(
				$qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_ROOM))
			);

		/**
		 * Reshares for this user are shares where they are the owner.
		 */
		if ($reshares === false) {
			$qb->andWhere($qb->expr()->eq('s.uid_initiator', $qb->createNamedParameter($userId)));
		} else {
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->eq('s.uid_owner', $qb->createNamedParameter($userId)),
					$qb->expr()->eq('s.uid_initiator', $qb->createNamedParameter($userId))
				)
			);
		}

		$qb->innerJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'));
		$qb->andWhere($qb->expr()->eq('f.storage', $qb->createNamedParameter($node->getMountPoint()->getNumericStorageId(), IQueryBuilder::PARAM_INT)));
		if ($shallow) {
			$qb->andWhere($qb->expr()->eq('f.parent', $qb->createNamedParameter($node->getId())));
		} else {
			$qb->andWhere($qb->expr()->like('f.path', $qb->createNamedParameter($this->dbConnection->escapeLikeParameter($node->getInternalPath()) . '/%')));
		}

		$qb->orderBy('s.id', 'ASC');

		$cursor = $qb->executeQuery();
		$shares = [];
		while ($data = $cursor->fetch()) {
			$shares[$data['fileid']][] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $shares;
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
	#[\Override]
	public function getSharesBy($userId, $shareType, $node, $reshares, $limit, $offset): array {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share');

		$qb->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)));

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
		$qb->orderBy('id', 'ASC');

		$cursor = $qb->executeQuery();
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
	#[\Override]
	public function getShareById($id, $recipientId = null): IShare {
		if (($recipientId === null) && isset($this->sharesByIdCache[$id])) {
			$share = $this->sharesByIdCache[$id];
		} else {
			$shares = $this->getSharesByIds([$id], $recipientId);
			if (empty($shares)) {
				throw new ShareNotFound();
			}
			$share = $shares[0];
		}

		// Shares referring to deleted files are stored as 'false' in the cache.
		if ($share === false) {
			throw new ShareNotFound();
		}

		return $share;
	}

	/**
	 * Get shares by ids
	 *
	 * Not part of IShareProvider API, but needed by OCA\Talk\Controller\ChatController.
	 *
	 * @param int[] $ids
	 * @param string|null $recipientId
	 * @return IShare[]
	 */
	public function getSharesByIds(array $ids, ?string $recipientId = null): array {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('s.*',
			'f.fileid', 'f.path', 'f.permissions AS f_permissions', 'f.storage', 'f.path_hash',
			'f.parent AS f_parent', 'f.name', 'f.mimetype', 'f.mimepart', 'f.size', 'f.mtime', 'f.storage_mtime',
			'f.encrypted', 'f.unencrypted_size', 'f.etag', 'f.checksum'
		)
			->selectAlias('st.id', 'storage_string_id')
			->from('share', 's')
			->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
			->leftJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'))
			->where($qb->expr()->in('s.id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)));

		$cursor = $qb->executeQuery();

		/*
		 * Keep retrieved shares in sharesByIdCache.
		 *
		 * Fill the cache only when $recipientId === null.
		 *
		 * For inaccessible shares use 'false' instead of the IShare object.
		 * (This is required to avoid additional queries in getShareById when
		 * the share refers to a deleted file.)
		 */
		$shares = [];
		while ($data = $cursor->fetch()) {
			$id = $data['id'];
			if ($this->isAccessibleResult($data)) {
				$share = $this->createShareObject($data);
				$shares[(int)$share->getId()] = $share;
			} else {
				$share = false;
			}
			if ($recipientId === null && !isset($this->sharesByIdCache[$id])) {
				$this->sharesByIdCache[$id] = $share;
			}
		}
		$cursor->closeCursor();

		if ($recipientId !== null) {
			return $this->resolveSharesForRecipient($shares, $recipientId);
		} else {
			return array_values($shares);
		}
	}

	/**
	 * Returns each given share as seen by the given recipient.
	 *
	 * If the recipient has not modified the share the original one is returned
	 * instead.
	 *
	 * @param array<int, IShare> $shareMap shares indexed by share id
	 * @param string $userId
	 * @param bool $allRoomShares indicates that the passed in shares are all room shares for the user
	 * @return list<IShare>
	 */
	private function resolveSharesForRecipient(array $shareMap, string $userId, bool $allRoomShares = false): array {
		$qb = $this->dbConnection->getQueryBuilder();

		$query = $qb->select('*')
			->from('share')

			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_USERROOM)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));

		if ($allRoomShares) {
			$stmt = $query->executeQuery();

			while ($data = $stmt->fetch()) {
				if (isset($shareMap[$data['parent']])) {
					$shareMap[$data['parent']]->setPermissions((int)$data['permissions']);
					$shareMap[$data['parent']]->setTarget($data['file_target']);
				}
			}

			$stmt->closeCursor();
		} else {
			$chunks = array_chunk($shareMap, 1000, true);
			$query->andWhere($qb->expr()->in('parent', $qb->createParameter('share_ids')));
			foreach ($chunks as $chunk) {
				$ids = array_keys($chunk);
				$query->setParameter('share_ids', $ids, IQueryBuilder::PARAM_INT_ARRAY);
				$stmt = $query->executeQuery();

				while ($data = $stmt->fetch()) {
					$shareMap[$data['parent']]->setPermissions((int)$data['permissions']);
					$shareMap[$data['parent']]->setTarget($data['file_target']);
				}

				$stmt->closeCursor();
			}
		}

		return array_values($shareMap);
	}

	/**
	 * Get shares for a given path
	 *
	 * @param Node $path
	 * @return IShare[]
	 */
	#[\Override]
	public function getSharesByPath(Node $path): array {
		$qb = $this->dbConnection->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($path->getId())))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
			->executeQuery();

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
	#[\Override]
	public function getSharedWith($userId, $shareType, $node, $limit, $offset): array {
		$allRooms = $this->manager->getRoomTokensWithAttachmentsForUser($userId);

		if (empty($allRooms)) {
			return [];
		}

		/** @var IShare[] $shares */
		$shares = [];

		$chunks = array_chunk($allRooms, 100);
		foreach ($chunks as $rooms) {
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->select('s.*',
				'f.fileid', 'f.path', 'f.permissions AS f_permissions', 'f.storage', 'f.path_hash',
				'f.parent AS f_parent', 'f.name', 'f.mimetype', 'f.mimepart', 'f.size', 'f.mtime', 'f.storage_mtime',
				'f.encrypted', 'f.unencrypted_size', 'f.etag', 'f.checksum'
			)
				->selectAlias('st.id', 'storage_string_id')
				->from('share', 's')
				->orderBy('s.id', 'ASC')
				->leftJoin('s', 'filecache', 'f', $qb->expr()->eq('s.file_source', 'f.fileid'))
				->leftJoin('f', 'storages', 'st', $qb->expr()->eq('f.storage', 'st.numeric_id'));

			if ($limit !== -1) {
				$qb->setMaxResults($limit);
			}

			// Filter by node if provided
			if ($node !== null) {
				$qb->andWhere($qb->expr()->eq('s.file_source', $qb->createNamedParameter($node->getId())));
			}

			$qb->andWhere($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
				->andWhere($qb->expr()->in('s.share_with', $qb->createNamedParameter(
					$rooms,
					IQueryBuilder::PARAM_STR_ARRAY
				)));

			$cursor = $qb->executeQuery();
			while ($data = $cursor->fetch()) {
				if ($data['uid_initiator'] === $userId || $data['uid_owner'] === $userId) {
					continue;
				}

				if (!$this->isAccessibleResult($data)) {
					continue;
				}

				if ($offset > 0) {
					$offset--;
					continue;
				}

				$share = $this->createShareObject($data);
				$shares[$share->getId()] = $share;
			}
			$cursor->closeCursor();
		}

		$shares = $this->resolveSharesForRecipient($shares, $userId, true);

		return $shares;
	}

	private function isAccessibleResult(array $data): bool {
		// exclude shares leading to deleted file entries
		if ($data['fileid'] === null || $data['path'] === null) {
			return false;
		}

		// exclude shares leading to trashbin on home storages
		$pathSections = explode('/', $data['path'], 2);
		// FIXME: would not detect rare md5'd home storage case properly
		if ($pathSections[0] !== 'files'
			&& (strpos($data['storage_string_id'], 'home::') === 0 || strpos($data['storage_string_id'], 'object::user') === 0)) {
			return false;
		}
		return true;
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
	#[\Override]
	public function getShareByToken($token): IShare {
		$qb = $this->dbConnection->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->executeQuery();

		$data = $cursor->fetch();

		if ($data === false) {
			throw new ShareNotFound();
		}

		$roomToken = $data['share_with'];
		try {
			$room = $this->manager->getRoomByToken($roomToken);
		} catch (RoomNotFoundException $e) {
			throw new ShareNotFound();
		}

		if ($room->getType() !== Room::TYPE_PUBLIC) {
			throw new ShareNotFound();
		}

		return $this->createShareObject($data);
	}

	/**
	 * A user is deleted from the system
	 * So clean up the relevant shares.
	 *
	 * @param string $uid
	 * @param int $shareType
	 */
	#[\Override]
	public function userDeleted($uid, $shareType): void {
		// A deleted user is handled automatically by the room hooks due to the
		// user being removed from the room.
	}

	/**
	 * A group is deleted from the system.
	 * We have to clean up all shares to this group.
	 * Providers not handling group shares should just return
	 *
	 * @param string $gid
	 */
	#[\Override]
	public function groupDeleted($gid): void {
	}

	/**
	 * A user is deleted from a group
	 * We have to clean up all the related user specific group shares
	 * Providers not handling group shares should just return
	 *
	 * @param string $uid
	 * @param string $gid
	 */
	#[\Override]
	public function userDeletedFromGroup($uid, $gid): void {
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
	#[\Override]
	public function getAccessList($nodes, $currentAccess): array {
		$ids = [];
		foreach ($nodes as $node) {
			$ids[] = $node->getId();
		}

		$qb = $this->dbConnection->getQueryBuilder();

		$types = [IShare::TYPE_ROOM];
		if ($currentAccess) {
			$types[] = self::SHARE_TYPE_USERROOM;
		}

		$qb->select('id', 'parent', 'share_type', 'share_with', 'file_source', 'file_target', 'permissions')
			->from('share')
			->where($qb->expr()->in('share_type', $qb->createNamedParameter($types, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->in('file_source', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('item_type', $qb->createNamedParameter('file')),
				$qb->expr()->eq('item_type', $qb->createNamedParameter('folder'))
			));
		$cursor = $qb->executeQuery();

		$users = [];
		while ($row = $cursor->fetch()) {
			$type = (int)$row['share_type'];
			if ($type === IShare::TYPE_ROOM) {
				$roomToken = $row['share_with'];
				try {
					$room = $this->manager->getRoomByToken($roomToken);
				} catch (RoomNotFoundException $e) {
					continue;
				}

				$userList = $this->participantService->getParticipantUserIds($room);
				foreach ($userList as $uid) {
					$users[$uid] ??= [];
					$users[$uid][$row['id']] = $row;
				}
			} elseif ($type === self::SHARE_TYPE_USERROOM && $currentAccess === true) {
				$uid = $row['share_with'];
				$users[$uid] ??= [];
				$users[$uid][$row['id']] = $row;
			}
		}
		$cursor->closeCursor();

		if ($currentAccess === true) {
			$users = array_map([$this, 'filterSharesOfUser'], $users);
			$users = array_filter($users);
		} else {
			$users = array_keys($users);
		}

		return ['users' => $users];
	}

	/**
	 * For each user the path with the fewest slashes is returned
	 * @param array $shares
	 * @return array
	 */
	protected function filterSharesOfUser(array $shares): array {
		// Room shares when the user has a share exception
		foreach ($shares as $id => $share) {
			$type = (int)$share['share_type'];
			$permissions = (int)$share['permissions'];

			if ($type === self::SHARE_TYPE_USERROOM) {
				unset($shares[$share['parent']]);

				if ($permissions === 0) {
					unset($shares[$id]);
				}
			}
		}

		$best = [];
		$bestDepth = 0;
		foreach ($shares as $id => $share) {
			$depth = substr_count($share['file_target'], '/');
			if (empty($best) || $depth < $bestDepth) {
				$bestDepth = $depth;
				$best = [
					'node_id' => $share['file_source'],
					'node_path' => $share['file_target'],
				];
			}
		}

		return $best;
	}

	/**
	 * Get all children of this share
	 *
	 * Not part of IShareProvider API, but needed by OC\Share20\Manager.
	 *
	 * @param IShare $parent
	 * @return IShare[]
	 */
	#[\Override]
	public function getChildren(IShare $parent): array {
		$children = [];

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($parent->getId())))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
			->orderBy('id', 'ASC');

		$cursor = $qb->executeQuery();
		while ($data = $cursor->fetch()) {
			$children[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $children;
	}

	/**
	 * Delete all shares in a room, or only those from the given user.
	 *
	 * When a user is given all their shares are removed, both own shares and
	 * received shares.
	 *
	 * Not part of IShareProvider API, but needed by the hooks in
	 * OCA\Talk\AppInfo\Application
	 *
	 * @param string $roomToken
	 * @param string|null $user
	 */
	public function deleteInRoom(string $roomToken, ?string $user = null): void {
		$this->cleanSharesByIdCache();

		//First delete all custom room shares for the original shares to be removed
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('id')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($roomToken)));

		if ($user !== null) {
			$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($user)));
		}

		$cursor = $qb->executeQuery();
		$ids = [];
		while ($row = $cursor->fetch()) {
			$ids[] = (int)$row['id'];
		}
		$cursor->closeCursor();

		if (!empty($ids)) {
			$delete = $this->dbConnection->getQueryBuilder();
			$delete->delete('share')
				->where($delete->expr()->eq('share_type', $delete->createNamedParameter(self::SHARE_TYPE_USERROOM)))
				->andWhere($delete->expr()->in('parent', $delete->createParameter('ids')));

			$chunks = array_chunk($ids, 100);
			foreach ($chunks as $chunk) {
				$delete->setParameter('ids', $chunk, IQueryBuilder::PARAM_INT_ARRAY);
				$delete->executeStatement();
			}
		}

		// Now delete all the original room shares
		$delete = $this->dbConnection->getQueryBuilder();
		$delete->delete('share')
			->where($delete->expr()->eq('share_type', $delete->createNamedParameter(IShare::TYPE_ROOM)))
			->andWhere($delete->expr()->eq('share_with', $delete->createNamedParameter($roomToken)));

		if ($user !== null) {
			$delete->andWhere($delete->expr()->eq('uid_initiator', $delete->createNamedParameter($user)));
		}

		$delete->executeStatement();

		// Finally delete all custom room shares leftovers for the given user
		if ($user !== null) {
			$query = $this->dbConnection->getQueryBuilder();
			$query->select('id')
				->from('share')
				->where($query->expr()->eq('share_type', $query->createNamedParameter(IShare::TYPE_ROOM)))
				->andWhere($query->expr()->eq('share_with', $query->createNamedParameter($roomToken)));

			$cursor = $query->executeQuery();
			$ids = [];
			while ($row = $cursor->fetch()) {
				$ids[] = (int)$row['id'];
			}
			$cursor->closeCursor();

			if (!empty($ids)) {
				$chunks = array_chunk($ids, 100);
				foreach ($chunks as $chunk) {
					$delete->delete('share')
						->where($delete->expr()->eq('share_type', $delete->createNamedParameter(self::SHARE_TYPE_USERROOM)))
						->andWhere($delete->expr()->in('share_with', $delete->createNamedParameter($user)))
						->andWhere($delete->expr()->in('parent', $delete->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
					$delete->executeStatement();
				}
			}
		}
	}

	/**
	 * Get all the shares in this provider returned as iterable to reduce memory
	 * overhead
	 *
	 * @return iterable
	 * @since 18.0.0
	 */
	#[\Override]
	public function getAllShares(): iterable {
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM))
				)
			);

		$cursor = $qb->executeQuery();
		while ($data = $cursor->fetch()) {
			$share = $this->createShareObject($data);

			yield $share;
		}
		$cursor->closeCursor();
	}
}
