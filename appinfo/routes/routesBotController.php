<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
];

$requirementsWithToken = [
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
		['name' => 'Bot#sendMessage', 'url' => '/api/{apiVersion}/bot/{token}/message', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\BotController::react() */
		['name' => 'Bot#react', 'url' => '/api/{apiVersion}/bot/{token}/reaction/{messageId}', 'verb' => 'POST', 'requirements' => $requirementsWithMessageId],
		/** @see \OCA\Talk\Controller\BotController::deleteReaction() */
		['name' => 'Bot#deleteReaction', 'url' => '/api/{apiVersion}/bot/{token}/reaction/{messageId}', 'verb' => 'DELETE', 'requirements' => $requirementsWithMessageId],
		/** @see \OCA\Talk\Controller\BotController::adminListBots() */
		['name' => 'Bot#adminListBots', 'url' => '/api/{apiVersion}/bot/admin', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BotController::listBots() */
		['name' => 'Bot#listBots', 'url' => '/api/{apiVersion}/bot/{token}', 'verb' => 'GET', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\BotController::enableBot() */
		['name' => 'Bot#enableBot', 'url' => '/api/{apiVersion}/bot/{token}/{botId}', 'verb' => 'POST', 'requirements' => $requirementsWithBotId],
		/** @see \OCA\Talk\Controller\BotController::disableBot() */
		['name' => 'Bot#disableBot', 'url' => '/api/{apiVersion}/bot/{token}/{botId}', 'verb' => 'DELETE', 'requirements' => $requirementsWithBotId],
	],
];
