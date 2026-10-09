<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\SnowflakeAwareEntity;
use OCP\DB\Types;

/**
 * Membership of a Matrix user in a mirrored Matrix room
 *
 * @method void setMatrixRoomId(string $matrixRoomId)
 * @method string getMatrixRoomId()
 * @method void setMxid(string $mxid)
 * @method string getMxid()
 * @method void setMembership(string $membership)
 * @method string getMembership()
 * @method void setDisplayName(?string $displayName)
 * @method ?string getDisplayName()
 * @method void setAccountId(?string $accountId)
 * @method ?string getAccountId()
 */
class MatrixMember extends SnowflakeAwareEntity {
	/** Id of the MatrixRoom entity */
	protected string $matrixRoomId = '';
	protected string $mxid = '';
	protected string $membership = '';
	protected ?string $displayName = null;
	/** Linked account of the member, null for Matrix users without a Nextcloud account */
	protected ?string $accountId = null;

	public function __construct() {
		$this->addType('matrixRoomId', Types::STRING);
		$this->addType('mxid', Types::STRING);
		$this->addType('membership', Types::STRING);
		$this->addType('displayName', Types::STRING);
		$this->addType('accountId', Types::STRING);
	}
}
