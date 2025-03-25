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
 * @method void setName(string $name)
 * @method string getName()
 * @method void setUrl(string $url)
 * @method string getUrl()
 * @method void setUrlHash(string $urlHash)
 * @method string getUrlHash()
 * @method void setDescription(?string $description)
 * @method null|string getDescription()
 * @method void setSecret(string $secret)
 * @method string getSecret()
 * @method void setErrorCount(int $errorCount)
 * @method int getErrorCount()
 * @method void setLastErrorDate(?\DateTimeImmutable $lastErrorDate)
 * @method ?\DateTimeImmutable getLastErrorDate()
 * @method void setLastErrorMessage(string $lastErrorMessage)
 * @method string getLastErrorMessage()
 * @method void setState(int $state)
 * @method int getState()
 * @method void setFeatures(int $features)
 * @method int getFeatures()
 */
class BotServer extends Entity implements \JsonSerializable {
	protected string $name = '';
	protected string $url = '';
	protected string $urlHash = '';
	protected ?string $description = null;
	protected string $secret = '';
	protected int $errorCount = 0;
	protected ?\DateTimeImmutable $lastErrorDate = null;
	protected ?string $lastErrorMessage = null;
	protected int $state = Bot::STATE_DISABLED;
	protected int $features = Bot::FEATURE_NONE;

	public function __construct() {
		$this->addType('name', Types::STRING);
		$this->addType('url', Types::STRING);
		$this->addType('url_hash', Types::STRING);
		$this->addType('description', Types::STRING);
		$this->addType('secret', Types::STRING);
		$this->addType('error_count', Types::BIGINT);
		$this->addType('last_error_date', Types::DATETIME);
		$this->addType('last_error_message', Types::STRING);
		$this->addType('state', Types::SMALLINT);
		$this->addType('features', Types::INTEGER);
	}

	/**
	 * @return array{
	 *     id: int,
	 *     name: string,
	 *     url: string,
	 *     url_hash: string,
	 *     description: ?string,
	 *     secret: string,
	 *     error_count: int,
	 *     last_error_date: int,
	 *     last_error_message: string,
	 *     state: int,
	 *     features: int,
	 * }
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'url' => $this->getUrl(),
			'url_hash' => $this->getUrlHash(),
			'description' => $this->getDescription(),
			'secret' => $this->getSecret(),
			'error_count' => $this->getErrorCount(),
			'last_error_date' => $this->getLastErrorDate() ? $this->getLastErrorDate()->getTimestamp() : 0,
			'last_error_message' => $this->getLastErrorMessage(),
			'state' => $this->getState(),
			'features' => $this->getFeatures(),
		];
	}
}
