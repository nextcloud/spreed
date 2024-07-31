<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

/**
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @psalm-method non-empty-string getUserId()
 * @method void setToken(string $token)
 * @method string getToken()
 * @psalm-method non-empty-string getToken()
 * @method void setMessageId(int $messageId)
 * @method int getMessageId()
 * @psalm-method int<1, max> getMessageId()
 * @method void setDateTime(\DateTime $dateTime)
 * @method \DateTime getDateTime()
 *
 * @psalm-import-type TalkChatReminder from ResponseDefinitions
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
	 * @return TalkChatReminder
	 */
	public function jsonSerialize(): array {
		return [
			'userId' => $this->getUserId(),
			'token' => $this->getToken(),
			'messageId' => $this->getMessageId(),
			'timestamp' => max(0, $this->getDateTime()->getTimestamp()),
		];
	}
}
