<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
];
return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\BanController::banActor() */
		['name' => 'Ban#banActor', 'url' => '/api/{apiVersion}/ban/{token}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BanController::listBans() */
		['name' => 'Ban#listBans', 'url' => '/api/{apiVersion}/ban/{token}', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BanController::unbanActor() */
		['name' => 'Ban#unbanActor', 'url' => '/api/{apiVersion}/ban/{token}/{banId}', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
