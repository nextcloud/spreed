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
 * A Nextcloud user's linked Matrix account (= one Matrix device owned by Talk).
 *
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setHomeserverId(int $homeserverId)
 * @method int getHomeserverId()
 * @method void setMxid(string $mxid)
 * @method string getMxid()
 * @method void setAccessToken(?string $accessToken)
 * @method ?string getAccessToken()
 * @method void setDeviceId(string $deviceId)
 * @method string getDeviceId()
 * @method void setNextBatch(?string $nextBatch)
 * @method ?string getNextBatch()
 * @method void setFilterId(?string $filterId)
 * @method ?string getFilterId()
 * @method void setStatus(int $status)
 * @method int getStatus()
 * @method void setLastSync(?\DateTime $lastSync)
 * @method ?\DateTime getLastSync()
 * @method void setLastActivity(?\DateTime $lastActivity)
 * @method ?\DateTime getLastActivity()
 * @method void setLastError(?string $lastError)
 * @method ?string getLastError()
 * @method void setLockUntil(?\DateTime $lockUntil)
 * @method ?\DateTime getLockUntil()
 * @method void setOlmAccount(?string $olmAccount)
 * @method ?string getOlmAccount()
 * @method void setCrossSigning(?string $crossSigning)
 * @method ?string getCrossSigning()
 * @method void setCreatedAt(?\DateTime $createdAt)
 * @method ?\DateTime getCreatedAt()
 */
class Account extends Entity {
	public const STATUS_ACTIVE = 0;
	/** Token rejected by the homeserver – the user has to log in again */
	public const STATUS_TOKEN_INVALID = 1;
	public const STATUS_DISABLED = 2;

	protected string $userId = '';
	protected int $homeserverId = 0;
	protected string $mxid = '';
	/** Encrypted with ICrypto – use AccountService to read it */
	protected ?string $accessToken = null;
	protected string $deviceId = '';
	protected ?string $nextBatch = null;
	protected ?string $filterId = null;
	protected int $status = self::STATUS_ACTIVE;
	protected ?\DateTime $lastSync = null;
	protected ?\DateTime $lastActivity = null;
	protected ?string $lastError = null;
	protected ?\DateTime $lockUntil = null;
	protected ?string $olmAccount = null;
	protected ?string $crossSigning = null;
	protected ?\DateTime $createdAt = null;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('homeserverId', Types::BIGINT);
		$this->addType('mxid', Types::STRING);
		$this->addType('accessToken', Types::STRING);
		$this->addType('deviceId', Types::STRING);
		$this->addType('nextBatch', Types::STRING);
		$this->addType('filterId', Types::STRING);
		$this->addType('status', Types::SMALLINT);
		$this->addType('lastSync', Types::DATETIME);
		$this->addType('lastActivity', Types::DATETIME);
		$this->addType('lastError', Types::STRING);
		$this->addType('lockUntil', Types::DATETIME);
		$this->addType('olmAccount', Types::STRING);
		$this->addType('crossSigning', Types::STRING);
		$this->addType('createdAt', Types::DATETIME);
	}

	public function isActive(): bool {
		return $this->status === self::STATUS_ACTIVE;
	}

	/**
	 * @return array{id: int, mxid: string, deviceId: string, homeserverId: int, status: int, lastSync: ?int, lastError: ?string}
	 */
	public function toUserArray(): array {
		return [
			'id' => $this->getId(),
			'mxid' => $this->getMxid(),
			'deviceId' => $this->getDeviceId(),
			'homeserverId' => $this->getHomeserverId(),
			'status' => $this->getStatus(),
			'lastSync' => $this->getLastSync()?->getTimestamp(),
			'lastError' => $this->getLastError(),
		];
	}
}
