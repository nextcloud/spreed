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

namespace OCA\Spreed\Chat\Command;


use OCA\Spreed\Model\Command;
use OCA\Spreed\Room;
use OCP\Comments\IComment;

class DefaultExecutor {

	public const PLACEHOLDER_ROOM = '{ROOM}';
	public const PLACEHOLDER_USER = '{USER}';
	public const PLACEHOLDER_ARGUMENTS = '{ARGUMENTS}';

	public function exec(Room $room, IComment $message, Command $command): void {
		$arguments = '';
		if (strpos($message->getMessage(), ' ') !== false) {
			[, $arguments] = explode(' ', $message->getMessage(), 2);
		}

		$user = $message->getActorType() === 'users' ? $message->getActorId() : '';

		$cmd = str_replace([
			self::PLACEHOLDER_ROOM,
			self::PLACEHOLDER_USER,
			self::PLACEHOLDER_ARGUMENTS,
		], [
			$room->getToken(),
			$user,
			$arguments,
		], $command->getScript());

		$output = [];
		exec($cmd, $output);

		$message->setMessage(json_encode([
			'user' => $user,
			'visibility' => $command->getOutput(),
			'output' => implode("\n", $output),
		]));
		$message->setActor('bot', $command->getName());
		$message->setVerb('command');
	}
}
