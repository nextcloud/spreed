<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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
namespace OCA\Talk\Migration;

use OCP\IConfig;
use OCP\Migration\SimpleMigrationStep;
use OCP\Migration\IOutput;

class Version2001Date20170929092606 extends SimpleMigrationStep {

	/** @var IConfig */
	protected $config;

	public function __construct(IConfig $config) {
		$this->config = $config;
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	public function preSchemaChange(IOutput $output, \Closure $schemaClosure, array $options): void {
		$stunServer = $this->config->getAppValue('spreed', 'stun_server', 'stun.nextcloud.com:443');
		$turnServer = [
			'server' => $this->config->getAppValue('spreed', 'turn_server'),
			'secret' => $this->config->getAppValue('spreed', 'turn_server_secret'),
			'protocols' => $this->config->getAppValue('spreed', 'turn_server_protocols'),
		];

		$this->config->setAppValue('spreed', 'stun_servers', json_encode([$stunServer]));
		if ($turnServer['server'] !== '' && $turnServer['secret'] !== '' && $turnServer['protocols'] !== '') {
			$this->config->setAppValue('spreed', 'turn_servers', json_encode([$turnServer]));
		}

		$this->config->deleteAppValue('spreed', 'stun_server');
		$this->config->deleteAppValue('spreed', 'turn_server');
		$this->config->deleteAppValue('spreed', 'turn_server_secret');
		$this->config->deleteAppValue('spreed', 'turn_server_protocols');
	}
}
