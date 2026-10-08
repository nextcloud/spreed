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
 * @method void setName(string $name)
 * @method string getName()
 * @method void setServerName(string $serverName)
 * @method string getServerName()
 * @method void setBaseUrl(string $baseUrl)
 * @method string getBaseUrl()
 * @method void setEnabled(bool $enabled)
 * @method bool getEnabled()
 * @method void setVersionsJson(?string $versionsJson)
 * @method ?string getVersionsJson()
 * @method void setVersionsFetched(?\DateTime $versionsFetched)
 * @method ?\DateTime getVersionsFetched()
 */
class Homeserver extends SnowflakeAwareEntity implements \JsonSerializable {
	protected string $name = '';
	protected string $serverName = '';
	protected string $baseUrl = '';
	protected bool $enabled = true;
	protected ?string $versionsJson = null;
	protected ?\DateTime $versionsFetched = null;

	public function __construct() {
		$this->addType('name', Types::STRING);
		$this->addType('serverName', Types::STRING);
		$this->addType('baseUrl', Types::STRING);
		$this->addType('enabled', Types::BOOLEAN);
		$this->addType('versionsJson', Types::STRING);
		$this->addType('versionsFetched', Types::DATETIME);
	}

	/** @return array<string, mixed>|null */
	public function getVersions(): ?array {
		if ($this->versionsJson === null) {
			return null;
		}
		$decoded = json_decode($this->versionsJson, true);
		return is_array($decoded) ? $decoded : null;
	}

	/**
	 * @return array{id: numeric-string, name: string, serverName: string, baseUrl: string, enabled: bool, specVersions: list<string>}
	 */
	#[\Override]
	public function jsonSerialize(): array {
		$versions = $this->getVersions()['versions'] ?? [];
		return [
			'id' => (string)$this->getId(),
			'name' => $this->getName(),
			'serverName' => $this->getServerName(),
			'baseUrl' => $this->getBaseUrl(),
			'enabled' => $this->getEnabled(),
			'specVersions' => is_array($versions) ? array_values(array_filter($versions, 'is_string')) : [],
		];
	}
}
