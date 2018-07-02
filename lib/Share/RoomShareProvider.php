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

use OCP\Files\Folder;
use OCP\Files\Node;
use OCP\Share\Exceptions\GenericShareException;
use OCP\Share\Exceptions\ShareNotFound;
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
	 */
	public function create(IShare $share) {
		throw new \Exception("Not implemented");
	}

	/**
	 * Update a share
	 *
	 * @param IShare $share
	 * @return IShare The share object
	 */
	public function update(IShare $share) {
		throw new \Exception("Not implemented");
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
	 * This is updating the share target. Thus the mount point of the recipient.
	 * This may require special handling. If a user moves a group share
	 * the target should only be changed for them.
	 *
	 * @param IShare $share
	 * @param string $recipient userId of recipient
	 * @return IShare
	 */
	public function move(IShare $share, $recipient) {
		throw new \Exception("Not implemented");
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
		throw new \Exception("Not implemented");
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
		throw new \Exception("Not implemented");
	}

	/**
	 * Get shares for a given path
	 *
	 * @param Node $path
	 * @return IShare[]
	 */
	public function getSharesByPath(Node $path) {
		throw new \Exception("Not implemented");
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
		throw new \Exception("Not implemented");
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
