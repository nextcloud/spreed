<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Talk\Migration;

use OCP\IConfig;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2001Date20170929092606 extends SimpleMigrationStep {

	public function __construct(
		protected IConfig $config,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param \Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @since 13.0.0
	 */
	#[\Override]
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
