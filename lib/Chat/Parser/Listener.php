<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Vitor Mattos <vitor@php.rio>
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

namespace OCA\Talk\Chat\Parser;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Chat\Parser\Command as CommandParser;
use OCA\Talk\Events\ChatMessageEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Server;

class Listener {
	public static function register(IEventDispatcher $dispatcher): void {
		$dispatcher->addListener(MessageParser::EVENT_MESSAGE_PARSE, [self::class, 'parseMention'], -100);

		$dispatcher->addListener(MessageParser::EVENT_MESSAGE_PARSE, [self::class, 'parseChangelog'], -75);

		$dispatcher->addListener(MessageParser::EVENT_MESSAGE_PARSE, [self::class, 'parseSystemMessage']);

		$dispatcher->addListener(MessageParser::EVENT_MESSAGE_PARSE, [self::class, 'parseCommand']);

		$dispatcher->addListener(MessageParser::EVENT_MESSAGE_PARSE, [self::class, 'parseReaction']);

		$dispatcher->addListener(MessageParser::EVENT_MESSAGE_PARSE, [self::class, 'parseDeletedMessage'], 9999);
	}

	public static function parseMention(ChatMessageEvent $event): void {
		$message = $event->getMessage();

		if ($message->getMessageType() !== ChatManager::VERB_MESSAGE) {
			return;
		}

		$parser = Server::get(UserMention::class);
		$parser->parseMessage($message);
	}

	public static function parseChangelog(ChatMessageEvent $event): void {
		$message = $event->getMessage();

		if ($message->getMessageType() !== ChatManager::VERB_MESSAGE) {
			return;
		}

		$parser = Server::get(Changelog::class);
		try {
			$parser->parseMessage($message);
			$event->stopPropagation();
		} catch (\OutOfBoundsException $e) {
			// Unknown message, ignore
		}
	}

	public static function parseSystemMessage(ChatMessageEvent $event): void {
		$message = $event->getMessage();

		if ($message->getMessageType() !== ChatManager::VERB_SYSTEM) {
			return;
		}

		$parser = Server::get(SystemMessage::class);

		try {
			$parser->parseMessage($message);
			// Disabled so we can parse mentions in captions: $event->stopPropagation();
		} catch (\OutOfBoundsException $e) {
			// Unknown message, ignore
		}
	}

	public static function parseCommand(ChatMessageEvent $event): void {
		$chatMessage = $event->getMessage();

		if ($chatMessage->getMessageType() !== ChatManager::VERB_COMMAND) {
			return;
		}

		$parser = Server::get(CommandParser::class);

		try {
			$parser->parseMessage($chatMessage);
			$event->stopPropagation();
		} catch (\OutOfBoundsException $e) {
			// Unknown message, ignore
		} catch (\RuntimeException $e) {
			$event->stopPropagation();
		}
	}

	public static function parseReaction(ChatMessageEvent $event): void {
		$chatMessage = $event->getMessage();

		if ($chatMessage->getMessageType() !== ChatManager::VERB_REACTION && $chatMessage->getMessageType() !== ChatManager::VERB_REACTION_DELETED) {
			return;
		}

		$parser = Server::get(ReactionParser::class);
		$parser->parseMessage($chatMessage);
	}

	public static function parseDeletedMessage(ChatMessageEvent $event): void {
		$chatMessage = $event->getMessage();

		if ($chatMessage->getMessageType() !== ChatManager::VERB_MESSAGE_DELETED) {
			return;
		}

		$parser = Server::get(SystemMessage::class);

		try {
			$parser->parseDeletedMessage($chatMessage);
			$event->stopPropagation();
		} catch (\OutOfBoundsException $e) {
			// Unknown message, ignore
		} catch (\RuntimeException $e) {
			$event->stopPropagation();
		}
	}
}
