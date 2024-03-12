<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2024 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;

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
		$this->addType('remoteServer', 'string');
		$this->addType('numAttempts', 'int');
		$this->addType('nextRetry', 'datetime');
		$this->addType('notificationType', 'string');
		$this->addType('resourceType', 'string');
		$this->addType('providerId', 'string');
		$this->addType('notification', 'string');
	}
}
