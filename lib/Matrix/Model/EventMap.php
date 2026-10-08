<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\SnowflakeAwareEntity;
use OCP\DB\Types;

/**
 * A Matrix event that was mirrored, recorded before the chat message is
 * written so every event is only applied once
 *
 * @method void setMatrixRoomId(string $matrixRoomId)
 * @method string getMatrixRoomId()
 * @method void setEventId(string $eventId)
 * @method string getEventId()
 * @method void setEventType(string $eventType)
 * @method string getEventType()
 * @method void setSender(string $sender)
 * @method string getSender()
 * @method void setCommentId(?int $commentId)
 * @method ?int getCommentId()
 */
class EventMap extends SnowflakeAwareEntity {
	/** Id of the MatrixRoom entity */
	protected string $matrixRoomId = '';
	protected string $eventId = '';
	protected string $eventType = '';
	protected string $sender = '';
	/** Chat message, reaction or deletion created for the event */
	protected ?int $commentId = null;

	public function __construct() {
		$this->addType('matrixRoomId', Types::STRING);
		$this->addType('eventId', Types::STRING);
		$this->addType('eventType', Types::STRING);
		$this->addType('sender', Types::STRING);
		$this->addType('commentId', Types::BIGINT);
	}
}
