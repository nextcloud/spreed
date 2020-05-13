<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Command\Command;

use OCA\Talk\Model\Command;
use OC\Core\Command\Base;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

trait TRenderCommand {
	protected function renderCommands(string $outputFormat, OutputInterface $output, array $commands, bool $showHelp = false): void {
		$result = array_map(function (Command $command) {
			return $command->asArray();
		}, $commands);

		if ($outputFormat === Base::OUTPUT_FORMAT_PLAIN) {
			if ($showHelp) {
				$output->writeln('Response values: 0 - No one,   1 - User,       2 - All');
				$output->writeln('Enabled values:  0 - Disabled, 1 - Moderators, 2 - Users, 3 - Guests');
				$output->writeln('');
			}

			$table = new Table($output);
			if (isset($result[0])) {
				$table->setHeaders(array_keys($result[0]));
			}
			$table->addRows($result);
			$table->render();
		} else {
			$this->writeMixedInOutputFormat($input, $output, $result);
		}
	}
}
