<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v3)',
];


$requirementsWithToken = [
	'apiVersion' => '(v3)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\SignalingController::getSettings() */
		['name' => 'Signaling#getSettings', 'url' => '/api/{apiVersion}/signaling/settings', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\SignalingController::getWelcomeMessage() */
		['name' => 'Signaling#getWelcomeMessage', 'url' => '/api/{apiVersion}/signaling/welcome/{serverId}', 'verb' => 'GET', 'requirements' => array_merge($requirements, [
			'serverId' => '\d+',
		])],
		/** @see \OCA\Talk\Controller\SignalingController::backend() */
		['name' => 'Signaling#backend', 'url' => '/api/{apiVersion}/signaling/backend', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\SignalingController::sendMessages() */
		['name' => 'Signaling#sendMessages', 'url' => '/api/{apiVersion}/signaling/{token}', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\SignalingController::pullMessages() */
		['name' => 'Signaling#pullMessages', 'url' => '/api/{apiVersion}/signaling/{token}', 'verb' => 'GET', 'requirements' => $requirementsWithToken],
	],
];
