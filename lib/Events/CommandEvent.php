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

namespace OCA\Talk\Events;

use OCA\Talk\Model\Command;
use OCA\Talk\Room;
use OCP\Comments\IComment;

/**
 * @deprecated
 */
class CommandEvent extends ARoomEvent {
	protected string $output = '';

	public function __construct(
		Room $room,
		protected IComment $message,
		protected Command $command,
		protected string $arguments,
	) {
		parent::__construct($room);
	}

	public function getComment(): IComment {
		return $this->message;
	}

	public function shouldSkipLastActivityUpdate(): bool {
		return false;
	}

	public function isSilentMessage(): bool {
		return false;
	}

	public function getCommand(): Command {
		return $this->command;
	}

	public function getArguments(): string {
		return $this->arguments;
	}

	public function setOutput(string $output): void {
		$this->output = $output;
	}

	public function getOutput(): string {
		return $this->output;
	}
}
