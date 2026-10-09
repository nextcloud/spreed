<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCA\Talk\Matrix\Client\Model\PowerLevels;
use OCP\AppFramework\Db\SnowflakeAwareEntity;
use OCP\DB\Types;

/**
 * A Matrix room mirrored as Talk conversation, the conversation has the
 * object type "matrix" and the id of this entity as object id
 *
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setMatrixRoomId(string $matrixRoomId)
 * @method string getMatrixRoomId()
 * @method void setName(?string $name)
 * @method ?string getName()
 * @method void setTopic(?string $topic)
 * @method ?string getTopic()
 * @method void setCanonicalAlias(?string $canonicalAlias)
 * @method ?string getCanonicalAlias()
 * @method void setCreator(?string $creator)
 * @method ?string getCreator()
 * @method void setEncrypted(bool $encrypted)
 * @method bool getEncrypted()
 * @method void setPowerLevels(?string $powerLevels)
 * @method ?string getPowerLevels()
 */
class MatrixRoom extends SnowflakeAwareEntity {
	/** Id of the Talk conversation, 0 until it was created */
	protected int $roomId = 0;
	protected string $matrixRoomId = '';
	/** Name from the m.room.name state, the conversation name can be calculated from the members instead */
	protected ?string $name = null;
	protected ?string $topic = null;
	protected ?string $canonicalAlias = null;
	protected ?string $creator = null;
	protected bool $encrypted = false;
	/** JSON content of m.room.power_levels */
	protected ?string $powerLevels = null;

	public function __construct() {
		$this->addType('roomId', Types::BIGINT);
		$this->addType('matrixRoomId', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('topic', Types::STRING);
		$this->addType('canonicalAlias', Types::STRING);
		$this->addType('creator', Types::STRING);
		$this->addType('encrypted', Types::BOOLEAN);
		$this->addType('powerLevels', Types::STRING);
	}

	/** @return array<string, mixed> */
	public function getPowerLevelsArray(): array {
		$decoded = $this->powerLevels !== null ? json_decode($this->powerLevels, true) : null;
		return is_array($decoded) ? $decoded : [];
	}

	public function getPowerLevelsModel(): PowerLevels {
		return new PowerLevels($this->getPowerLevelsArray(), (string)$this->creator);
	}
}
