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
 * Published keys of a device of a Matrix user, as seen by one account
 *
 * @method void setAccountId(string $accountId)
 * @method string getAccountId()
 * @method void setMxid(string $mxid)
 * @method string getMxid()
 * @method void setDeviceId(string $deviceId)
 * @method string getDeviceId()
 * @method void setCurve25519(string $curve25519)
 * @method string getCurve25519()
 * @method void setEd25519(string $ed25519)
 * @method string getEd25519()
 * @method void setOutdated(bool $outdated)
 * @method bool getOutdated()
 * @method void setVerified(bool $verified)
 * @method bool getVerified()
 */
class MatrixDevice extends SnowflakeAwareEntity {
	protected string $accountId = '';
	protected string $mxid = '';
	protected string $deviceId = '';
	/** Unpadded base64 identity key */
	protected string $curve25519 = '';
	/** Unpadded base64 signing key */
	protected string $ed25519 = '';
	/** The user changed their devices, so they are queried again before they are used */
	protected bool $outdated = false;
	/** A device of the user that was verified with emoji, room keys it forwards are accepted */
	protected bool $verified = false;

	public function __construct() {
		$this->addType('accountId', Types::STRING);
		$this->addType('mxid', Types::STRING);
		$this->addType('deviceId', Types::STRING);
		$this->addType('curve25519', Types::STRING);
		$this->addType('ed25519', Types::STRING);
		$this->addType('outdated', Types::BOOLEAN);
		$this->addType('verified', Types::BOOLEAN);
	}
}
