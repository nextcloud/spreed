<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Matrix\Model;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * Matrix-side state of a Talk conversation with object type "matrix".
 *
 * @method void setRoomId(int $roomId)
 * @method int getRoomId()
 * @method void setMatrixRoomId(string $matrixRoomId)
 * @method string getMatrixRoomId()
 * @method void setRoomVersion(string $roomVersion)
 * @method string getRoomVersion()
 * @method void setEncrypted(bool $encrypted)
 * @method bool getEncrypted()
 * @method void setEncryptionAlgo(?string $encryptionAlgo)
 * @method ?string getEncryptionAlgo()
 * @method void setRotationPeriodMs(?int $rotationPeriodMs)
 * @method ?int getRotationPeriodMs()
 * @method void setRotationPeriodMsgs(?int $rotationPeriodMsgs)
 * @method ?int getRotationPeriodMsgs()
 * @method void setJoinRule(string $joinRule)
 * @method string getJoinRule()
 * @method void setIsDirect(bool $isDirect)
 * @method bool getIsDirect()
 * @method void setCanonicalAlias(?string $canonicalAlias)
 * @method ?string getCanonicalAlias()
 * @method void setPowerLevels(?string $powerLevels)
 * @method ?string getPowerLevels()
 * @method void setCreator(string $creator)
 * @method string getCreator()
 * @method void setTombstoneTarget(?string $tombstoneTarget)
 * @method ?string getTombstoneTarget()
 * @method void setPrevBatch(?string $prevBatch)
 * @method ?string getPrevBatch()
 * @method void setBackfillDone(bool $backfillDone)
 * @method bool getBackfillDone()
 * @method void setCapabilities(?string $capabilities)
 * @method ?string getCapabilities()
 * @method void setStateUpdated(?\DateTime $stateUpdated)
 * @method ?\DateTime getStateUpdated()
 */
class MatrixRoom extends Entity {
	protected int $roomId = 0;
	protected string $matrixRoomId = '';
	protected string $roomVersion = '1';
	protected bool $encrypted = false;
	protected ?string $encryptionAlgo = null;
	protected ?int $rotationPeriodMs = null;
	protected ?int $rotationPeriodMsgs = null;
	protected string $joinRule = 'invite';
	protected bool $isDirect = false;
	protected ?string $canonicalAlias = null;
	protected ?string $powerLevels = null;
	protected string $creator = '';
	protected ?string $tombstoneTarget = null;
	protected ?string $prevBatch = null;
	protected bool $backfillDone = false;
	protected ?string $capabilities = null;
	protected ?\DateTime $stateUpdated = null;

	public function __construct() {
		$this->addType('roomId', Types::BIGINT);
		$this->addType('matrixRoomId', Types::STRING);
		$this->addType('roomVersion', Types::STRING);
		$this->addType('encrypted', Types::BOOLEAN);
		$this->addType('encryptionAlgo', Types::STRING);
		$this->addType('rotationPeriodMs', Types::BIGINT);
		$this->addType('rotationPeriodMsgs', Types::INTEGER);
		$this->addType('joinRule', Types::STRING);
		$this->addType('isDirect', Types::BOOLEAN);
		$this->addType('canonicalAlias', Types::STRING);
		$this->addType('powerLevels', Types::STRING);
		$this->addType('creator', Types::STRING);
		$this->addType('tombstoneTarget', Types::STRING);
		$this->addType('prevBatch', Types::STRING);
		$this->addType('backfillDone', Types::BOOLEAN);
		$this->addType('capabilities', Types::STRING);
		$this->addType('stateUpdated', Types::DATETIME);
	}

	/** @return array<string, mixed> */
	public function getPowerLevelsArray(): array {
		$decoded = $this->powerLevels === null ? null : json_decode($this->powerLevels, true);
		return is_array($decoded) ? $decoded : [];
	}

	/** @param array<string, mixed> $powerLevels */
	public function setPowerLevelsArray(array $powerLevels): void {
		$this->setPowerLevels(json_encode($powerLevels, JSON_THROW_ON_ERROR));
	}

	/** @return array<string, mixed> */
	public function getCapabilitiesArray(): array {
		$decoded = $this->capabilities === null ? null : json_decode($this->capabilities, true);
		return is_array($decoded) ? $decoded : [];
	}

	/** @param array<string, mixed> $capabilities */
	public function setCapabilitiesArray(array $capabilities): void {
		$this->setCapabilities(json_encode($capabilities, JSON_THROW_ON_ERROR));
	}
}
