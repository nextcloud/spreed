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
 * @psalm-method int<1, max> getId()
 * @method void setName(string $name)
 * @method string getName()
 * @psalm-method non-empty-string getName()
 * @method void setUrl(string $url)
 * @method string getUrl()
 * @psalm-method non-empty-string getUrl()
 * @method void setUrlHash(string $urlHash)
 * @method string getUrlHash()
 * @psalm-method non-empty-string getUrlHash()
 * @method void setDescription(?string $description)
 * @method null|string getDescription()
 * @method void setSecret(string $secret)
 * @method string getSecret()
 * @psalm-method non-empty-string getSecret()
 * @method void setErrorCount(int $errorCount)
 * @method int getErrorCount()
 * @psalm-method int<0, max> getErrorCount()
 * @method void setLastErrorDate(?\DateTimeImmutable $lastErrorDate)
 * @method null|\DateTimeImmutable getLastErrorDate()
 * @method void setLastErrorMessage(string $lastErrorMessage)
 * @method string getLastErrorMessage()
 * @method void setState(int $state)
 * @method int getState()
 * @psalm-method Bot::STATE_* getState()
 * @method void setFeatures(int $features)
 * @method int getFeatures()
 * @psalm-method int-mask<1, 2> getFeatures()
 *
 * @psalm-import-type TalkBotWithDetailsAndSecret from ResponseDefinitions
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
		$this->addType('name', 'string');
		$this->addType('url', 'string');
		$this->addType('url_hash', 'string');
		$this->addType('description', 'string');
		$this->addType('secret', 'string');
		$this->addType('error_count', 'int');
		$this->addType('last_error_date', 'datetime');
		$this->addType('last_error_message', 'string');
		$this->addType('state', 'int');
		$this->addType('features', 'int');
	}

	/**
	 * @return TalkBotWithDetailsAndSecret
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'name' => $this->getName(),
			'url' => $this->getUrl(),
			'url_hash' => $this->getUrlHash(),
			'description' => $this->getDescription(),
			'secret' => $this->getSecret(),
			'error_count' => $this->getErrorCount(),
			'last_error_date' => $this->getLastErrorDate() ? max(0, $this->getLastErrorDate()->getTimestamp()) : 0,
			'last_error_message' => $this->getLastErrorMessage(),
			'state' => $this->getState(),
			'features' => $this->getFeatures(),
		];
	}
}
