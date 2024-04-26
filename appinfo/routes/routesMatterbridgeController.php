<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\MatterbridgeController::getBridgeOfRoom() */
		['name' => 'Matterbridge#getBridgeOfRoom', 'url' => '/api/{apiVersion}/bridge/{token}', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\MatterbridgeController::getBridgeProcessState() */
		['name' => 'Matterbridge#getBridgeProcessState', 'url' => '/api/{apiVersion}/bridge/{token}/process', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\MatterbridgeController::editBridgeOfRoom() */
		['name' => 'Matterbridge#editBridgeOfRoom', 'url' => '/api/{apiVersion}/bridge/{token}', 'verb' => 'PUT', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\MatterbridgeController::deleteBridgeOfRoom() */
		['name' => 'Matterbridge#deleteBridgeOfRoom', 'url' => '/api/{apiVersion}/bridge/{token}', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
