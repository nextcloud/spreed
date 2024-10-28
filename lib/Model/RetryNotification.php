<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method void setRemoteServer(string $remoteServer)
 * @method string getRemoteServer()
 * @method void setNumAttempts(int $numAttempts)
 * @method int getNumAttempts()
 * @method void setNextRetry(\DateTime $nextRetry)
 * @method \DateTime getNextRetry()
 * @method void setNotificationType(string $notificationType)
 * @method string getNotificationType()
 * @method void setResourceType(string $resourceType)
 * @method string getResourceType()
 * @method void setProviderId(string $providerId)
 * @method string getProviderId()
 * @method void setNotification(string $notification)
 * @method string getNotification()
 */
class RetryNotification extends Entity {
	public const MAX_NUM_ATTEMPTS = 20;

	protected string $remoteServer = '';
	protected int $numAttempts = 0;
	protected ?\DateTime $nextRetry = null;
	protected string $notificationType = '';
	protected string $resourceType = '';
	protected string $providerId = '';
	protected string $notification = '';

	public function __construct() {
		$this->addType('remoteServer', Types::STRING);
		$this->addType('numAttempts', Types::INTEGER);
		$this->addType('nextRetry', Types::DATETIME);
		$this->addType('notificationType', Types::STRING);
		$this->addType('resourceType', Types::STRING);
		$this->addType('providerId', Types::STRING);
		$this->addType('notification', Types::TEXT);
	}
}
