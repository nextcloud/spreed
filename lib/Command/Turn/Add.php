<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Denis Mosolov <denismosolov@gmail.com>
 *
 * @author Denis Mosolov <denismosolov@gmail.com>
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

namespace OCA\Talk\Command\Turn;

use OCP\IConfig;
use OC\Core\Command\Base;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Add extends Base {

	/** @var IConfig */
	private $config;

	public function __construct(IConfig $config) {
		parent::__construct();
		$this->config = $config;
	}

	protected function configure(): void {
		$this
			->setName('talk:turn:add')
			->setDescription('Add a TURN server.')
			->addArgument(
				'server',
				InputArgument::REQUIRED,
				'A domain name, ex. turn.nextcloud.com'
			)->addArgument(
				'protocols',
				InputArgument::REQUIRED,
				'Protocols, can be udp or tcp or udp,tcp.'
			)->addOption(
				'secret',
				null,
				InputOption::VALUE_REQUIRED,
				'A shard secret string'
			)->addOption(
				'generate-secret',
				null,
				InputOption::VALUE_NONE,
				'Generate secret if set.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$server = $input->getArgument('server');
		$protocols = $input->getArgument('protocols');
		$secret = $input->getOption('secret');
		$generate = $input->getOption('generate-secret');

		if (! in_array($protocols, ['tcp', 'udp', 'udp,tcp'])) {
			$output->writeln('<error>Not allowed protocols, must be udp or tcp or udp,tcp.</error>');
			return 1;
		}
		// quick validation, similar to turn-server.js
		if (trim($server) === '') {
			$output->writeln('<error>Server cannot be empty.</error>');
			return 1;
		}
		if (($generate === false && $secret === null) ||
			($generate && $secret !== null)) {
			$output->writeln('<error>You must provide --secret or --generate-secret.</error>');
			return 1;
		}
		if (!$generate && trim($secret) === '') {
			$output->writeln('<error>Secret cannot be empty.</error>');
			return 1;
		}
		if ($generate) {
			$secret = $this->getUniqueSecret();
		}
		if (stripos($server, 'https://') === 0) {
			$server = substr($server, 8);
		}
		if (stripos($server, 'http://') === 0) {
			$server = substr($server, 7);
		}

		$config = $this->config->getAppValue('spreed', 'turn_servers');
		$servers = json_decode($config, true);

		if ($servers === null || empty($servers) || !is_array($servers)) {
			$servers = [];
		}

		$servers[] = [
			'server' => $server,
			'secret' => $secret, // @todo: check the order
			'protocols' => $protocols,
		];

		$this->config->setAppValue('spreed', 'turn_servers', json_encode($servers));
		$output->writeln('<info>Added ' . $server . '.</info>');
		return 0;
	}

	protected function getUniqueSecret(): string {
		return sha1(uniqid('', true));
	}
}
