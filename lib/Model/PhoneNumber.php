<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @psalm-method int<1, max> getId()
 * @method void setPhoneNumber(string $phoneNumber)
 * @method string getPhoneNumber()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 */
class PhoneNumber extends Entity {
	protected string $phoneNumber = '';
	protected string $actorId = '';

	public function __construct() {
		$this->addType('phoneNumber', Types::STRING);
		$this->addType('actorId', Types::STRING);
	}
}
