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

namespace OCA\Talk\Command\Signaling;

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
			->setName('talk:signaling:add')
			->setDescription('Add an external signaling server.')
			->addArgument(
				'server',
				InputArgument::REQUIRED,
				'A server string, ex. wss://signaling.example.org'
			)->addArgument(
				'secret',
				InputArgument::REQUIRED,
				'A shared secret string.'
			)->addOption(
				'verify',
				null,
				InputOption::VALUE_NONE,
				'Validate SSL certificate if set.'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$server = $input->getArgument('server');
		$secret = $input->getArgument('secret');
		$verify = $input->getOption('verify');

		// quick validation, similar to signaling-server.js
		if (trim($server) === '') {
			$output->writeln('<error>Server cannot be empty.</error>');
			return 1;
		}
		if (trim($secret) === '') {
			$output->writeln('<error>Secret cannot be empty.</error>');
			return 1;
		}

		$config = $this->config->getAppValue('spreed', 'signaling_servers');

		$signaling = json_decode($config, true);
		if ($signaling === null || empty($signaling) || !is_array($signaling)) {
			$servers = [];
		} else {
			$servers = is_array($signaling['servers']) ? $signaling['servers'] : [];
		}
		$servers[] = [
			'server' => $server,
			'verify' => $verify,
		];
		$signaling = [
			'servers' => $servers,
			'secret' => $secret,
		];

		$this->config->setAppValue('spreed', 'signaling_servers', json_encode($signaling));
		$output->writeln('<info>Added signaling server ' . $server . '.</info>');
		return 0;
	}
}
