<?php
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
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

namespace OCA\Spreed\Chat;

use OCA\Spreed\Chat\Parser\UserMention;
use OCP\Comments\IComment;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	/** @var EventDispatcherInterface */
	protected $dispatcher;

	public function __construct(EventDispatcherInterface $dispatcher) {
		$this->dispatcher = $dispatcher;
	}

	public function register() {
		$this->dispatcher->addListener(MessageParser::class . '::parseMessage', function(GenericEvent $event) {
			/** @var IComment $chatMessage */
			$chatMessage = $event->getSubject();

			if ($chatMessage->getVerb() !== 'comment') {
				return;
			}

			/** @var UserMention $parser */
			$parser = \OC::$server->query(UserMention::class);
			list($message, $parameters) = $parser->parseMessage($chatMessage);

			$event->setArguments([
				'message' => $message,
				'parameters' => $parameters,
			]);
		}, -100);
	}
}
