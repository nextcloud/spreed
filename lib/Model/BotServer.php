<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\Talk\Model;

use OCA\Talk\ResponseDefinitions;
use OCP\AppFramework\Db\Entity;

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
			'last_error_date' => $this->getLastErrorDate() ? $this->getLastErrorDate()->getTimestamp() : 0,
			'last_error_message' => $this->getLastErrorMessage(),
			'state' => $this->getState(),
			'features' => $this->getFeatures(),
		];
	}
}
