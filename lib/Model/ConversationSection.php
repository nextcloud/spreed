<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Model;

use OCP\AppFramework\Db\SnowflakeAwareEntity;
use OCP\DB\Types;

/**
 * @method void setUserId(string $userId)
 * @method string getUserId()
 * @method void setName(string $name)
 * @method string getName()
 * @method void setSortOrder(int $sortOrder)
 * @method int getSortOrder()
 * @method void setCollapsed(bool $collapsed)
 * @method bool isCollapsed()
 */
class ConversationSection extends SnowflakeAwareEntity {
	protected string $userId = '';
	protected string $name = '';
	protected int $sortOrder = 0;
	protected bool $collapsed = false;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('sortOrder', Types::INTEGER);
		$this->addType('collapsed', Types::BOOLEAN);
	}
}
