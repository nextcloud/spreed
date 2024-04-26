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
		/** @see \OCA\Talk\Controller\FederationController::acceptShare() */
		['name' => 'Federation#acceptShare', 'url' => 'api/{apiVersion}/federation/invitation/{id}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\FederationController::rejectShare() */
		['name' => 'Federation#rejectShare', 'url' => 'api/{apiVersion}/federation/invitation/{id}', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\FederationController::getShares() */
		['name' => 'Federation#getShares', 'url' => 'api/{apiVersion}/federation/invitation', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
