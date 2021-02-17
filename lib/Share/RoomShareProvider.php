<?php

declare(strict_types=1);
/**
 *
 * @copyright Copyright (c) 2018, Daniel Calviño Sánchez (danxuliu@gmail.com)
 *
 * Code copied from "lib/private/Share20/DefaultShareProvider.php" and
 * "apps/sharebymail/lib/ShareByMailProvider.php" at d805959e819e64 in Nextcloud
 * server repository.
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

namespace OCA\Talk\Share;

use OC\Files\Cache\Cache;
use OCA\Talk\Events\ParticipantEvent;
use OCA\Talk\Events\RemoveUserEvent;
use OCA\Talk\Events\RoomEvent;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Exceptions\RoomNotFoundException;
use OCA\Talk\Manager;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Utility\ITimeFactory;
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
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

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
	public const SHARE_TYPE_USERROOM = 11;

	public const TALK_FOLDER = '/Talk';
	public const TALK_FOLDER_PLACEHOLDER = '/{TALK_PLACEHOLDER}';

	/** @var IDBConnection */
	private $dbConnection;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var IShareManager */
	private $shareManager;
	/** @var EventDispatcherInterface */
	private $dispatcher;
	/** @var Manager */
	private $manager;
	/** @var ParticipantService */
	private $participantService;
	/** @var ITimeFactory */
	protected $timeFactory;
	/** @var IL10N */
	private $l;
	/** @var IMimeTypeLoader */
	private $mimeTypeLoader;

	public function __construct(
			IDBConnection $connection,
			ISecureRandom $secureRandom,
			IShareManager $shareManager,
			EventDispatcherInterface $dispatcher,
			Manager $manager,
			ParticipantService $participantService,
			ITimeFactory $timeFactory,
			IL10N $l,
			IMimeTypeLoader $mimeTypeLoader
	) {
		$this->dbConnection = $connection;
		$this->secureRandom = $secureRandom;
		$this->shareManager = $shareManager;
		$this->dispatcher = $dispatcher;
		$this->manager = $manager;
		$this->participantService = $participantService;
		$this->timeFactory = $timeFactory;
		$this->l = $l;
		$this->mimeTypeLoader = $mimeTypeLoader;
	}

	public static function register(IEventDispatcher $dispatcher): void {
		$listener = static function (ParticipantEvent $event) {
			$room = $event->getRoom();

			if ($event->getParticipant()->getAttendee()->getParticipantType() === Participant::USER_SELF_JOINED) {
				/** @var self $roomShareProvider */
				$roomShareProvider = \OC::$server->query(self::class);
				$roomShareProvider->deleteInRoom($room->getToken(), $event->getParticipant()->getAttendee()->getActorId());
			}
		};
		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_DISCONNECT, $listener);

		$listener = static function (RemoveUserEvent $event) {
			$room = $event->getRoom();

			/** @var self $roomShareProvider */
			$roomShareProvider = \OC::$server->query(self::class);
			$roomShareProvider->deleteInRoom($room->getToken(), $event->getUser()->getUID());
		};
		$dispatcher->addListener(Room::EVENT_AFTER_USER_REMOVE, $listener);

		$listener = static function (RoomEvent $event) {
			$room = $event->getRoom();

			/** @var self $roomShareProvider */
			$roomShareProvider = \OC::$server->query(self::class);
			$roomShareProvider->deleteInRoom($room->getToken());
		};
		$dispatcher->addListener(Room::EVENT_AFTER_ROOM_DELETE, $listener);
	}

	/**
	 * Return the identifier of this provider.
	 *
	 * @return string Containing only [a-zA-Z0-9]
	 */
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
	public function create(IShare $share): IShare {
		try {
			$room = $this->manager->getRoomByToken($share->getSharedWith(), $share->getSharedBy());
		} catch (RoomNotFoundException $e) {
			throw new GenericShareException('Room not found', $this->l->t('Conversation not found'), 404);
		}

		if ($room->getReadOnly() === Room::READ_ONLY) {
			throw new GenericShareException('Room not found', $this->l->t('Conversation not found'), 404);
		}

		try {
			$room->getParticipant($share->getSharedBy(), false);
		} catch (ParticipantNotFoundException $e) {
			// If the sharer is not a participant of the room even if the room
			// exists the error is still "Room not found".
			throw new GenericShareException('Room not found', $this->l->t('Conversation not found'), 404);
		}

		$existingShares = $this->getSharesByPath($share->getNode());
		foreach ($existingShares as $existingShare) {
			if ($existingShare->getSharedWith() === $share->getSharedWith()) {
				// FIXME Should be moved away from GenericEvent as soon as OCP\Share20\IManager did move too
				$this->dispatcher->dispatch(self::class . '::' . 'share_file_again', new GenericEvent($existingShare));
				throw new GenericShareException('Already shared', $this->l->t('Path is already shared with this room'), 403);
			}
		}

		$share->setToken(
			$this->secureRandom->generate(
				15, // \OC\Share\Constants::TOKEN_LENGTH
				\OCP\Security\ISecureRandom::CHAR_HUMAN_READABLE
			)
		);

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
			?\DateTime $expirationDate
	): int {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->insert('share')
			->setValue('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM))
			->setValue('share_with', $qb->createNamedParameter($shareWith))
			->setValue('uid_initiator', $qb->createNamedParameter($sharedBy))
			->setValue('uid_owner', $qb->createNamedParameter($shareOwner))
			->setValue('item_type', $qb->createNamedParameter($itemType))
			->setValue('item_source', $qb->createNamedParameter($itemSource))
			->setValue('file_source', $qb->createNamedParameter($itemSource))
			->setValue('file_target', $qb->createNamedParameter($target))
			->setValue('permissions', $qb->createNamedParameter($permissions))
			->setValue('token', $qb->createNamedParameter($token))
			->setValue('stime', $qb->createNamedParameter($this->timeFactory->getTime()));

		if ($expirationDate !== null) {
			$qb->setValue('expiration', $qb->createNamedParameter($expirationDate, 'datetime'));
		}

		$qb->execute();
		$id = $qb->getLastInsertId();

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
	private function createShareObject(array $data): IShare {
		$share = $this->shareManager->newShare();
		$share->setId((int)$data['id'])
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
	public function update(IShare $share): IShare {
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
	public function delete(IShare $share): void {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($share->getId())));

		$qb->orWhere($qb->expr()->eq('parent', $qb->createNamedParameter($share->getId())));

		$qb->execute();
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
	public function deleteFromSelf(IShare $share, $recipient): void {
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
					'permissions' => $qb->createNamedParameter(0),
					'stime' => $qb->createNamedParameter($share->getShareTime()->getTimestamp()),
				])->execute();
		} elseif ($data['permissions'] !== 0) {
			// Already a userroom share. Update it.
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->update('share')
				->set('permissions', $qb->createNamedParameter(0))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($data['id'])))
				->execute();
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
	public function restore(IShare $share, string $recipient): IShare {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('permissions')
			->from('share')
			->where(
				$qb->expr()->eq('id', $qb->createNamedParameter($share->getId()))
			);
		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		$originalPermission = $data['permissions'];

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->update('share')
			->set('permissions', $qb->createNamedParameter($originalPermission))
			->where(
				$qb->expr()->eq('parent', $qb->createNamedParameter($share->getId()))
			)->andWhere(
				$qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_USERROOM))
			)->andWhere(
				$qb->expr()->eq('share_with', $qb->createNamedParameter($recipient))
			);

		$qb->execute();

		return $this->getShareById($share->getId(), $recipient);
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
	public function move(IShare $share, $recipient): IShare {
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
	 * @return IShare[][]
	 * @psalm-return array<array-key, non-empty-list<IShare>>
	 */
	public function getSharesInFolder($userId, Folder $node, $reshares): array {
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
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

		$qb->innerJoin('s', 'filecache' ,'f', $qb->expr()->eq('s.file_source', 'f.fileid'));
		$qb->andWhere($qb->expr()->eq('f.parent', $qb->createNamedParameter($node->getId())));

		$qb->orderBy('s.id');

		$cursor = $qb->execute();
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
	public function getShareById($id, $recipientId = null): IShare {
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
			->where($qb->expr()->eq('s.id', $qb->createNamedParameter($id)))
			->andWhere($qb->expr()->eq('s.share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)));

		$cursor = $qb->execute();
		$data = $cursor->fetch();
		$cursor->closeCursor();

		if ($data === false) {
			throw new ShareNotFound();
		}

		if (!$this->isAccessibleResult($data)) {
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
	 * @param IShare[] $shares
	 * @param string $userId
	 * @return IShare[]
	 */
	private function resolveSharesForRecipient(array $shares, string $userId): array {
		$result = [];

		$start = 0;
		while (true) {
			/** @var IShare[] $shareSlice */
			$shareSlice = array_slice($shares, $start, 100);
			$start += 100;

			if ($shareSlice === []) {
				break;
			}

			/** @var int[] $ids */
			$ids = [];
			/** @var IShare[] $shareMap */
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
	public function getSharesByPath(Node $path): array {
		$qb = $this->dbConnection->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->andWhere($qb->expr()->eq('file_source', $qb->createNamedParameter($path->getId())))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
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
	public function getSharedWith($userId, $shareType, $node, $limit, $offset): array {
		$allRooms = $this->manager->getRoomTokensForUser($userId);

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
			$qb->select('s.*',
				'f.fileid', 'f.path', 'f.permissions AS f_permissions', 'f.storage', 'f.path_hash',
				'f.parent AS f_parent', 'f.name', 'f.mimetype', 'f.mimepart', 'f.size', 'f.mtime', 'f.storage_mtime',
				'f.encrypted', 'f.unencrypted_size', 'f.etag', 'f.checksum'
			)
				->selectAlias('st.id', 'storage_string_id')
				->from('share', 's')
				->orderBy('s.id')
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
				)))
				->andWhere($qb->expr()->orX(
					$qb->expr()->eq('s.item_type', $qb->createNamedParameter('file')),
					$qb->expr()->eq('s.item_type', $qb->createNamedParameter('folder'))
				));

			$cursor = $qb->execute();
			while ($data = $cursor->fetch()) {
				if (!$this->isAccessibleResult($data)) {
					continue;
				}

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

	private function isAccessibleResult(array $data): bool {
		// exclude shares leading to deleted file entries
		if ($data['fileid'] === null || $data['path'] === null) {
			return false;
		}

		// exclude shares leading to trashbin on home storages
		$pathSections = explode('/', $data['path'], 2);
		// FIXME: would not detect rare md5'd home storage case properly
		if ($pathSections[0] !== 'files'
			&& in_array(explode(':', $data['storage_string_id'], 2)[0], ['home', 'object'])) {
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
	public function getShareByToken($token): IShare {
		$qb = $this->dbConnection->getQueryBuilder();

		$cursor = $qb->select('*')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
			->andWhere($qb->expr()->eq('token', $qb->createNamedParameter($token)))
			->execute();

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

		if ($room->getType() !== Room::PUBLIC_CALL) {
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
		$cursor = $qb->execute();

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
					$users[$uid] = $users[$uid] ?? [];
					$users[$uid][$row['id']] = $row;
				}
			} elseif ($type === self::SHARE_TYPE_USERROOM && $currentAccess === true) {
				$uid = $row['share_with'];
				$users[$uid] = $users[$uid] ?? [];
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
			$type = (int) $share['share_type'];
			$permissions = (int) $share['permissions'];

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
	public function getChildren(IShare $parent): array {
		$children = [];

		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('*')
			->from('share')
			->where($qb->expr()->eq('parent', $qb->createNamedParameter($parent->getId())))
			->andWhere($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
			->orderBy('id');

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$children[] = $this->createShareObject($data);
		}
		$cursor->closeCursor();

		return $children;
	}

	/**
	 * Delete all shares in a room, or only those from the given user.
	 *
	 * When a user is given all her shares are removed, both own shares and
	 * received shares.
	 *
	 * Not part of IShareProvider API, but needed by the hooks in
	 * OCA\Talk\AppInfo\Application
	 *
	 * @param string $roomToken
	 * @param string|null $user
	 */
	public function deleteInRoom(string $roomToken, string $user = null): void {
		//First delete all custom room shares for the original shares to be removed
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->select('id')
			->from('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($roomToken)));

		if ($user !== null) {
			$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($user)));
		}

		$cursor = $qb->execute();
		$ids = [];
		while ($row = $cursor->fetch()) {
			$ids[] = (int)$row['id'];
		}
		$cursor->closeCursor();

		if (!empty($ids)) {
			$chunks = array_chunk($ids, 100);
			foreach ($chunks as $chunk) {
				$qb->delete('share')
					->where($qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_USERROOM)))
					->andWhere($qb->expr()->in('parent', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
				$qb->execute();
			}
		}

		// Now delete all the original room shares
		$qb = $this->dbConnection->getQueryBuilder();
		$qb->delete('share')
			->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
			->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($roomToken)));

		if ($user !== null) {
			$qb->andWhere($qb->expr()->eq('uid_initiator', $qb->createNamedParameter($user)));
		}

		$qb->execute();

		// Finally delete all custom room shares leftovers for the given user
		if ($user !== null) {
			$qb = $this->dbConnection->getQueryBuilder();
			$qb->select('id')
				->from('share')
				->where($qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM)))
				->andWhere($qb->expr()->eq('share_with', $qb->createNamedParameter($roomToken)));

			$cursor = $qb->execute();
			$ids = [];
			while ($row = $cursor->fetch()) {
				$ids[] = (int)$row['id'];
			}
			$cursor->closeCursor();

			if (!empty($ids)) {
				$chunks = array_chunk($ids, 100);
				foreach ($chunks as $chunk) {
					$qb->delete('share')
						->where($qb->expr()->eq('share_type', $qb->createNamedParameter(self::SHARE_TYPE_USERROOM)))
						->andWhere($qb->expr()->in('share_with', $qb->createNamedParameter($user)))
						->andWhere($qb->expr()->in('parent', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
					$qb->execute();
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
	public function getAllShares(): iterable {
		$qb = $this->dbConnection->getQueryBuilder();

		$qb->select('*')
			->from('share')
			->where(
				$qb->expr()->orX(
					$qb->expr()->eq('share_type', $qb->createNamedParameter(IShare::TYPE_ROOM))
				)
			);

		$cursor = $qb->execute();
		while ($data = $cursor->fetch()) {
			$share = $this->createShareObject($data);

			yield $share;
		}
		$cursor->closeCursor();
	}
}
