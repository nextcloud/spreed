<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Membership of one Matrix user in one Matrix room, incl. users without a Nextcloud account.
 *
 * @method void setMatrixRoomId(string $matrixRoomId)
 * @method string getMatrixRoomId()
 * @method void setMxid(string $mxid)
 * @method string getMxid()
 * @method void setMembership(string $membership)
 * @method string getMembership()
 * @method void setDisplayName(?string $displayName)
 * @method ?string getDisplayName()
 * @method void setAvatarUrl(?string $avatarUrl)
 * @method ?string getAvatarUrl()
 * @method void setPowerLevel(int $powerLevel)
 * @method int getPowerLevel()
 * @method void setAttendeeId(?int $attendeeId)
 * @method ?int getAttendeeId()
 * @method void setAccountId(?int $accountId)
 * @method ?int getAccountId()
 * @method void setUpdatedAt(?\DateTime $updatedAt)
 * @method ?\DateTime getUpdatedAt()
 */
class MatrixMember extends Entity {
	protected string $matrixRoomId = '';
	protected string $mxid = '';
	protected string $membership = 'leave';
	protected ?string $displayName = null;
	protected ?string $avatarUrl = null;
	protected int $powerLevel = 0;
	protected ?int $attendeeId = null;
	protected ?int $accountId = null;
	protected ?\DateTime $updatedAt = null;

	public function __construct() {
		$this->addType('matrixRoomId', Types::STRING);
		$this->addType('mxid', Types::STRING);
		$this->addType('membership', Types::STRING);
		$this->addType('displayName', Types::STRING);
		$this->addType('avatarUrl', Types::STRING);
		$this->addType('powerLevel', Types::INTEGER);
		$this->addType('attendeeId', Types::BIGINT);
		$this->addType('accountId', Types::BIGINT);
		$this->addType('updatedAt', Types::DATETIME);
	}

	public function getName(): string {
		$name = trim((string)$this->displayName);
		return $name !== '' ? $name : $this->mxid;
	}
}
