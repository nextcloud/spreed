<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v4)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\CallController::getPeersForCall() */
		['name' => 'Call#getPeersForCall', 'url' => '/api/{apiVersion}/call/{token}', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallNotificationController::state() */
		['name' => 'CallNotification#state', 'url' => '/api/{apiVersion}/call/{token}/notification-state', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::downloadParticipantsForCall() */
		['name' => 'Call#downloadParticipantsForCall', 'url' => '/api/{apiVersion}/call/{token}/download', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::joinCall() */
		['name' => 'Call#joinCall', 'url' => '/api/{apiVersion}/call/{token}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::joinFederatedCall() */
		['name' => 'Call#joinFederatedCall', 'url' => '/api/{apiVersion}/call/{token}/federation', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::ringAttendee() */
		['name' => 'Call#ringAttendee', 'url' => '/api/{apiVersion}/call/{token}/ring/{attendeeId}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::sipDialOut() */
		['name' => 'Call#sipDialOut', 'url' => '/api/{apiVersion}/call/{token}/dialout/{attendeeId}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::updateCallFlags() */
		['name' => 'Call#updateCallFlags', 'url' => '/api/{apiVersion}/call/{token}', 'verb' => 'PUT', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::updateFederatedCallFlags() */
		['name' => 'Call#updateFederatedCallFlags', 'url' => '/api/{apiVersion}/call/{token}/federation', 'verb' => 'PUT', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::leaveCall() */
		['name' => 'Call#leaveCall', 'url' => '/api/{apiVersion}/call/{token}', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::leaveFederatedCall() */
		['name' => 'Call#leaveFederatedCall', 'url' => '/api/{apiVersion}/call/{token}/federation', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
