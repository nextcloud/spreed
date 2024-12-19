<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
];

$requirementsWithToken = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\RecordingController::getWelcomeMessage() */
		['name' => 'Recording#getWelcomeMessage', 'url' => '/api/{apiVersion}/recording/welcome/{serverId}', 'verb' => 'GET', 'requirements' => array_merge($requirements, [
			'serverId' => '\d+',
		])],
		/** @see \OCA\Talk\Controller\RecordingController::backend() */
		['name' => 'Recording#backend', 'url' => '/api/{apiVersion}/recording/backend', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RecordingController::start() */
		['name' => 'Recording#start', 'url' => '/api/{apiVersion}/recording/{token}', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RecordingController::stop() */
		['name' => 'Recording#stop', 'url' => '/api/{apiVersion}/recording/{token}', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RecordingController::store() */
		['name' => 'Recording#store', 'url' => '/api/{apiVersion}/recording/{token}/store', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RecordingController::notificationDismiss() */
		['name' => 'Recording#notificationDismiss', 'url' => '/api/{apiVersion}/recording/{token}/notification', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RecordingController::shareToChat() */
		['name' => 'Recording#shareToChat', 'url' => '/api/{apiVersion}/recording/{token}/share-chat', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
	],
];
