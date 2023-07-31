<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
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

use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setToken(string $token)
 * @method string getToken()
 * @method void setMessageId(int $messageId)
 * @method int getMessageId()
 * @method void setDateTime(\DateTime $dateTime)
 * @method \DateTime getDateTime()
 *
 * @psalm-import-type SpreedReminder from ResponseDefinitions
 */
class Reminder extends Entity implements \JsonSerializable {
	protected string $userId = '';
	protected string $token = '';
	protected int $messageId = 0;
	protected ?\DateTime $dateTime = null;

	public function __construct() {
		$this->addType('userId', 'string');
		$this->addType('token', 'string');
		$this->addType('messageId', 'int');
		$this->addType('dateTime', 'datetime');
	}

	/**
	 * @return SpreedReminder
	 */
	public function jsonSerialize(): array {
		return [
			'userId' => $this->getUserId(),
			'token' => $this->getToken(),
			'messageId' => $this->getMessageId(),
			'timestamp' => $this->getDateTime()->getTimestamp(),
		];
	}
}
