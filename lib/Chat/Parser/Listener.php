<?php
declare(strict_types=1);
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

namespace OCA\Spreed\Chat\Parser;

use OCA\Spreed\Chat\MessageParser;
use OCA\Spreed\Chat\Parser\Command as CommandParser;
use OCA\Spreed\Model\Message;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;

class Listener {

	public static function register(EventDispatcherInterface $dispatcher): void {
		$dispatcher->addListener(MessageParser::class . '::parseMessage', function(GenericEvent $event) {
			/** @var Message $message */
			$message = $event->getSubject();

			if ($message->getMessageType() !== 'comment') {
				return;
			}

			/** @var UserMention $parser */
			$parser = \OC::$server->query(UserMention::class);
			$parser->parseMessage($message);
		}, -100);

		$dispatcher->addListener(MessageParser::class . '::parseMessage', function(GenericEvent $event) {
			/** @var Message $message */
			$message = $event->getSubject();

			if ($message->getMessageType() !== 'comment') {
				return;
			}

			/** @var Changelog $parser */
			$parser = \OC::$server->query(Changelog::class);
			$parser->parseMessage($message);
		}, -75);

		$dispatcher->addListener(MessageParser::class . '::parseMessage', function(GenericEvent $event) {
			/** @var Message $message */
			$message = $event->getSubject();

			if ($message->getMessageType() !== 'system') {
				return;
			}

			/** @var SystemMessage $parser */
			$parser = \OC::$server->query(SystemMessage::class);

			try {
				$parser->parseMessage($message);
				$event->stopPropagation();
			} catch (\OutOfBoundsException $e) {
				// Unknown message, ignore
			}
		});

		$dispatcher->addListener(MessageParser::class . '::parseMessage', function(GenericEvent $event) {
			/** @var Message $chatMessage */
			$chatMessage = $event->getSubject();

			if ($chatMessage->getMessageType() !== 'command') {
				return;
			}

			/** @var CommandParser $parser */
			$parser = \OC::$server->query(CommandParser::class);

			try {
				$parser->parseMessage($chatMessage);
				$event->stopPropagation();
			} catch (\OutOfBoundsException $e) {
				// Unknown message, ignore
			} catch (\RuntimeException $e) {
				$event->stopPropagation();
			}
		});
	}
}
