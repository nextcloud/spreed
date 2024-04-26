<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

$requirements = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\BreakoutRoomController::configureBreakoutRooms() */
		['name' => 'BreakoutRoom#configureBreakoutRooms', 'url' => '/api/{apiVersion}/breakout-rooms/{token}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BreakoutRoomController::removeBreakoutRooms() */
		['name' => 'BreakoutRoom#removeBreakoutRooms', 'url' => '/api/{apiVersion}/breakout-rooms/{token}', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BreakoutRoomController::broadcastChatMessage() */
		['name' => 'BreakoutRoom#broadcastChatMessage', 'url' => '/api/{apiVersion}/breakout-rooms/{token}/broadcast', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BreakoutRoomController::applyAttendeeMap() */
		['name' => 'BreakoutRoom#applyAttendeeMap', 'url' => '/api/{apiVersion}/breakout-rooms/{token}/attendees', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BreakoutRoomController::requestAssistance() */
		['name' => 'BreakoutRoom#requestAssistance', 'url' => '/api/{apiVersion}/breakout-rooms/{token}/request-assistance', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BreakoutRoomController::resetRequestForAssistance() */
		['name' => 'BreakoutRoom#resetRequestForAssistance', 'url' => '/api/{apiVersion}/breakout-rooms/{token}/request-assistance', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BreakoutRoomController::startBreakoutRooms() */
		['name' => 'BreakoutRoom#startBreakoutRooms', 'url' => '/api/{apiVersion}/breakout-rooms/{token}/rooms', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BreakoutRoomController::stopBreakoutRooms() */
		['name' => 'BreakoutRoom#stopBreakoutRooms', 'url' => '/api/{apiVersion}/breakout-rooms/{token}/rooms', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\BreakoutRoomController::switchBreakoutRoom() */
		['name' => 'BreakoutRoom#switchBreakoutRoom', 'url' => '/api/{apiVersion}/breakout-rooms/{token}/switch', 'verb' => 'POST', 'requirements' => $requirements],
	],
];
