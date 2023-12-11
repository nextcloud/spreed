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

namespace OCA\Talk\Command\Stun;

use OC\Core\Command\Base;
use OCP\IConfig;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Add extends Base {
	private IConfig $config;

	public function __construct(IConfig $config) {
		parent::__construct();
		$this->config = $config;
	}

	protected function configure(): void {
		$this
			->setName('talk:stun:add')
			->setDescription('Add a new STUN server.')
			->addArgument(
				'server',
				InputArgument::REQUIRED,
				'A domain name and port number separated by the colons, ex. stun.nextcloud.com:443'
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$server = $input->getArgument('server');
		// check input, similar to stun-server.js
		$host = parse_url($server, PHP_URL_HOST);
		$port = parse_url($server, PHP_URL_PORT);
		if (empty($host) || empty($port)) {
			$output->writeln('<error>Incorrect value. Must be stunserver:port.</error>');
			return 1;
		}

		$config = $this->config->getAppValue('spreed', 'stun_servers');
		$servers = json_decode($config, true);

		if ($servers === null || empty($servers) || !is_array($servers)) {
			$servers = [];
		}

		$servers[] = "$host:$port";

		$this->config->setAppValue('spreed', 'stun_servers', json_encode($servers));
		$output->writeln('<info>Added ' . "$host:$port" . '.</info>');
		return 0;
	}
}
