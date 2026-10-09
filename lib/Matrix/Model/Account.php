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
 * A Nextcloud user's linked Matrix account, Talk is one device of it
 *
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setHomeserverId(string $homeserverId)
 * @method string getHomeserverId()
 * @method void setMxid(string $mxid)
 * @method string getMxid()
 * @method void setAccessToken(string $accessToken)
 * @method string getAccessToken()
 * @method void setDeviceId(string $deviceId)
 * @method string getDeviceId()
 * @method void setStatus(int $status)
 * @method int getStatus()
 * @method void setLastError(?string $lastError)
 * @method ?string getLastError()
 * @method void setNextBatch(?string $nextBatch)
 * @method ?string getNextBatch()
 * @method void setFilterId(?string $filterId)
 * @method ?string getFilterId()
 * @method void setLastSync(int $lastSync)
 * @method int getLastSync()
 * @method void setLockUntil(int $lockUntil)
 * @method int getLockUntil()
 */
class Account extends SnowflakeAwareEntity implements \JsonSerializable {
	public const STATUS_ACTIVE = 0;
	/** The homeserver rejected the access token, the user has to log in again */
	public const STATUS_TOKEN_INVALID = 1;

	protected string $userId = '';
	protected string $homeserverId = '';
	protected string $mxid = '';
	/** Encrypted with ICrypto */
	protected string $accessToken = '';
	protected string $deviceId = '';
	protected int $status = self::STATUS_ACTIVE;
	protected ?string $lastError = null;
	/** Position of the last sync, null when the next sync is an initial sync */
	protected ?string $nextBatch = null;
	protected ?string $filterId = null;
	protected int $lastSync = 0;
	protected int $lockUntil = 0;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('homeserverId', Types::STRING);
		$this->addType('mxid', Types::STRING);
		$this->addType('accessToken', Types::STRING);
		$this->addType('deviceId', Types::STRING);
		$this->addType('status', Types::SMALLINT);
		$this->addType('lastError', Types::STRING);
		$this->addType('nextBatch', Types::STRING);
		$this->addType('filterId', Types::STRING);
		$this->addType('lastSync', Types::BIGINT);
		$this->addType('lockUntil', Types::BIGINT);
	}

	/**
	 * @return array{id: numeric-string, homeserverId: numeric-string, mxid: string, deviceId: string, status: Account::STATUS_*, lastError: ?string, lastSync: int}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		/** @var numeric-string $homeserverId */
		$homeserverId = $this->getHomeserverId();
		return [
			'id' => (string)$this->getId(),
			'homeserverId' => $homeserverId,
			'mxid' => $this->getMxid(),
			'deviceId' => $this->getDeviceId(),
			'status' => $this->getStatus() === self::STATUS_TOKEN_INVALID ? self::STATUS_TOKEN_INVALID : self::STATUS_ACTIVE,
			'lastError' => $this->getLastError(),
			'lastSync' => $this->getLastSync(),
		];
	}
}
