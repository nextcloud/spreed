<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
 *
 * @author Kate Döen <kate.doeen@nextcloud.com>
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
 * @method void setApp(string $app)
 * @method string getApp()
 * @method void setName(string $name)
 * @method string getName()
 * @method void setCommand(string $command)
 * @method string getCommand()
 * @method void setScript(string $name)
 * @method string getScript()
 * @method void setResponse(int $response)
 * @method int getResponse()
 * @method void setEnabled(int $enabled)
 * @method int getEnabled()
 *
 * @psalm-import-type SpreedCommand from ResponseDefinitions
 */
class Command extends Entity {
	public const RESPONSE_NONE = 0;
	public const RESPONSE_USER = 1;
	public const RESPONSE_ALL = 2;

	public const ENABLED_OFF = 0;
	public const ENABLED_MODERATOR = 1;
	public const ENABLED_USERS = 2;
	public const ENABLED_ALL = 3;

	/** @var string */
	protected $app;

	/** @var string */
	protected $name;

	/** @var string */
	protected $command;

	/** @var string */
	protected $script;

	/** @var int */
	protected $response;

	/** @var int */
	protected $enabled;

	public function __construct() {
		$this->addType('app', 'string');
		$this->addType('name', 'string');
		$this->addType('command', 'string');
		$this->addType('script', 'string');
		$this->addType('response', 'int');
		$this->addType('enabled', 'int');
	}

	/**
	 * @return SpreedCommand
	 */
	public function asArray(): array {
		return [
			'id' => $this->getId(),
			'app' => $this->getApp(),
			'name' => $this->getName(),
			'command' => $this->getCommand(),
			'script' => $this->getScript(),
			'response' => $this->getResponse(),
			'enabled' => $this->getEnabled(),
		];
	}
}
