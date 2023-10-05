<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2022 Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
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
