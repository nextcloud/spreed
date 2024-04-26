<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;

/**
 * @method void setToken(string $token)
 * @method string getToken()
 * @method void setActorType(string $actorType)
 * @method string getActorType()
 * @method void setActorId(string $actorId)
 * @method string getActorId()
 * @method void setDateTime(\DateTime $dateTime)
 * @method \DateTime getDateTime()
 */
class Consent extends Entity implements \JsonSerializable {
	protected string $token = '';
	protected string $actorType = '';
	protected string $actorId = '';
	protected ?\DateTime $dateTime = null;

	public function __construct() {
		$this->addType('token', 'string');
		$this->addType('actorType', 'string');
		$this->addType('actorId', 'string');
		$this->addType('dateTime', 'datetime');
	}

	public function jsonSerialize(): array {
		return [
			'token' => $this->getToken(),
			'actorType' => $this->getActorType(),
			'actorId' => $this->getActorId(),
			'timestamp' => $this->getDateTime()->getTimestamp(),
		];
	}
}
