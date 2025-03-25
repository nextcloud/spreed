<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

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
		$this->addType('bot_id', Types::BIGINT);
		$this->addType('token', Types::STRING);
		$this->addType('state', Types::SMALLINT);
	}

	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'bot_id' => $this->getBotId(),
			'token' => $this->getToken(),
			'state' => $this->getState(),
		];
	}
}
