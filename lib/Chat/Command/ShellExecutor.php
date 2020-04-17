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

namespace OCA\Talk\Chat\Command;


class ShellExecutor {

	public const PLACEHOLDER_ROOM = '{ROOM}';
	public const PLACEHOLDER_USER = '{USER}';
	public const PLACEHOLDER_ARGUMENTS = '{ARGUMENTS}';
	public const PLACEHOLDER_ARGUMENTS_DOUBLEQUOTE_ESCAPED = '{ARGUMENTS_DOUBLEQUOTE_ESCAPED}';

	/**
	 * @param string $cmd
	 * @param string $arguments
	 * @param string $room
	 * @param string $user
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public function execShell(string $cmd, string $arguments, string $room = '', string $user = ''): string {
		$cmd = str_replace([
			self::PLACEHOLDER_ROOM,
			self::PLACEHOLDER_USER,
			self::PLACEHOLDER_ARGUMENTS,
			self::PLACEHOLDER_ARGUMENTS_DOUBLEQUOTE_ESCAPED,
		], [
			escapeshellarg($room),
			escapeshellarg($user),
			$this->escapeArguments($arguments),
			str_replace(['$', '`', '"'], ['\\$', '\\`', '\\"'], $arguments),
		], $cmd);

		return $this->wrapExec($cmd);
	}

	protected function escapeArguments(string $argumentString): string {
		$arguments = explode(' ', $argumentString);

		$result = [];
		$buffer = [];
		$quote = '';
		foreach ($arguments as $argument) {
			if ($quote === '') {
				if (ltrim($argument, '"\'') === $argument) {
					$result[] = escapeshellarg($argument);
				} else {
					$quote = $argument[0];
					$temp = substr($argument, 1);
					if (rtrim($temp, $quote) === $temp) {
						$buffer[] = $temp;
					} else {
						$result[] = $quote . str_replace($quote, '\\'. $quote, substr($temp, 0, -1)) . $quote;
						$quote = '';
					}
				}
			} else if (rtrim($argument, $quote) === $argument) {
				$buffer[] = $argument;
			} else {
				$buffer[] = substr($argument, 0, -1);

				$result[] = $quote . str_replace($quote, '\\'. $quote, implode(' ', $buffer)) . $quote;
				$quote = '';
				$buffer = [];
			}
		}

		if ($quote !== '') {
			$result[] = escapeshellarg($quote . implode(' ', $buffer));
		}

		return implode(' ', $result);
	}

	/**
	 * @param string $cmd
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	protected function wrapExec(string $cmd): string {
		$output = [];
		$returnCode = 0;
		@exec($cmd, $output, $returnCode);

		if ($returnCode) {
			throw new \InvalidArgumentException('Chat command failed [Code: ' . $returnCode . ']: ' . $cmd);
		}

		return implode("\n", $output);
	}
}
