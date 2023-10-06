<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
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

$requirements = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
];

$requirementsWithMessageId = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
	'messageId' => '[0-9]+',
];

$requirementsWithBotId = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
	'botId' => '[0-9]+',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\BotController::sendMessage() */
		['name' => 'Bot#sendMessage', 'url' => '/api/{apiVersion}/bot/{token}/message', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BotController::react() */
		['name' => 'Bot#react', 'url' => '/api/{apiVersion}/bot/{token}/reaction/{messageId}', 'verb' => 'POST', 'requirements' => $requirementsWithMessageId],
		/** @see \OCA\Talk\Controller\BotController::deleteReaction() */
		['name' => 'Bot#deleteReaction', 'url' => '/api/{apiVersion}/bot/{token}/reaction/{messageId}', 'verb' => 'DELETE', 'requirements' => $requirementsWithMessageId],
		/** @see \OCA\Talk\Controller\BotController::adminListBots() */
		['name' => 'Bot#adminListBots', 'url' => '/api/{apiVersion}/bot/admin', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BotController::listBots() */
		['name' => 'Bot#listBots', 'url' => '/api/{apiVersion}/bot/{token}', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BotController::enableBot() */
		['name' => 'Bot#enableBot', 'url' => '/api/{apiVersion}/bot/{token}/{botId}', 'verb' => 'POST', 'requirements' => $requirementsWithBotId],
		/** @see \OCA\Talk\Controller\BotController::disableBot() */
		['name' => 'Bot#disableBot', 'url' => '/api/{apiVersion}/bot/{token}/{botId}', 'verb' => 'DELETE', 'requirements' => $requirementsWithBotId],
	],
];
