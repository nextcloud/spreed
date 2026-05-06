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
 * @method void setType(string $type)
 */
class ConversationTag extends SnowflakeAwareEntity {
	public const TYPE_CUSTOM = 'custom';
	public const TYPE_FAVORITES = 'favorites';
	public const TYPE_OTHER = 'other';

	protected string $userId = '';
	protected string $name = '';
	protected int $sortOrder = 0;
	protected bool $collapsed = false;
	/** @var self::TYPE_* */
	protected string $type = self::TYPE_CUSTOM;

	public function __construct() {
		$this->addType('userId', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('sortOrder', Types::INTEGER);
		$this->addType('collapsed', Types::BOOLEAN);
		$this->addType('type', Types::STRING);
	}

	/**
	 * Overrides the magic `@method getType()` to narrow the return type for static analysis.
	 * The DB can only hold one of these three values (see ensureBuiltInTags / createTag).
	 *
	 * @return self::TYPE_*
	 */
	public function getType(): string {
		return $this->type;
	}
}
