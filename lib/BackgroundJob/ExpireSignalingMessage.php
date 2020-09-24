<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2017 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\BackgroundJob;

use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCA\Talk\Signaling\Messages;

/**
 * Class ExpireSignalingMessage
 *
 * @package OCA\Talk\BackgroundJob
 */
class ExpireSignalingMessage extends TimedJob {

	/** @var Messages */
	protected $messages;

	public function __construct(ITimeFactory $timeFactory,
								Messages $messages) {
		parent::__construct($timeFactory);

		// Every 5 minutes
		$this->setInterval(60 * 5);

		$this->messages = $messages;
	}

	protected function run($argument): void {
		$this->messages->expireOlderThan(5 * 60);
	}
}
