<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
	'messageId' => '[0-9]+',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\ReactionController::react() */
		['name' => 'Reaction#react', 'url' => '/api/{apiVersion}/reaction/{token}/{messageId}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\ReactionController::delete() */
		['name' => 'Reaction#delete', 'url' => '/api/{apiVersion}/reaction/{token}/{messageId}', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\ReactionController::getReactions() */
		['name' => 'Reaction#getReactions', 'url' => '/api/{apiVersion}/reaction/{token}/{messageId}', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
