<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
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
 * @method void setBotId(int $botId)
 * @method int getBotId()
 * @method void setToken(string $token)
 * @method string getToken()
 * @method void setState(int $state)
 * @method int getState()
 */
class BotConversation extends Entity implements \JsonSerializable {
	protected int $botId = 0;
	protected string $token = '';
	protected int $state = Bot::STATE_DISABLED;

	public function __construct() {
		$this->addType('bot_id', 'int');
		$this->addType('token', 'string');
		$this->addType('state', 'int');
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'bot_id' => $this->getBotId(),
			'token' => $this->getToken(),
			'state' => $this->getState(),
		];
	}
}
