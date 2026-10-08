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
 */
class Account extends SnowflakeAwareEntity implements \JsonSerializable {
	protected string $userId = '';
	protected string $homeserverId = '';
	protected string $mxid = '';
	/** Encrypted with ICrypto */
	protected string $accessToken = '';
	protected string $deviceId = '';

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('homeserverId', Types::STRING);
		$this->addType('mxid', Types::STRING);
		$this->addType('accessToken', Types::STRING);
		$this->addType('deviceId', Types::STRING);
	}

	/**
	 * @return array{id: numeric-string, homeserverId: numeric-string, mxid: string, deviceId: string}
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
		];
	}
}
