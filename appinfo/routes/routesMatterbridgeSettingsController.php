<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\MatterbridgeSettingsController::stopAllBridges() */
		['name' => 'MatterbridgeSettings#stopAllBridges', 'url' => '/api/{apiVersion}/bridge', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\MatterbridgeSettingsController::getMatterbridgeVersion() */
		['name' => 'MatterbridgeSettings#getMatterbridgeVersion', 'url' => '/api/{apiVersion}/bridge/version', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
