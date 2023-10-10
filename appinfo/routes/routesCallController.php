<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2016 Lukas Reschke <lukas@statuscode.ch>
 *
 * @author Lukas Reschke <lukas@statuscode.ch>
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
	'apiVersion' => '(v4)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\CallController::getPeersForCall() */
		['name' => 'Call#getPeersForCall', 'url' => '/api/{apiVersion}/call/{token}', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::joinCall() */
		['name' => 'Call#joinCall', 'url' => '/api/{apiVersion}/call/{token}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::ringAttendee() */
		['name' => 'Call#ringAttendee', 'url' => '/api/{apiVersion}/call/{token}/ring/{attendeeId}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::sipDialOut() */
		['name' => 'Call#sipDialOut', 'url' => '/api/{apiVersion}/call/{token}/dialout/{attendeeId}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::updateCallFlags() */
		['name' => 'Call#updateCallFlags', 'url' => '/api/{apiVersion}/call/{token}', 'verb' => 'PUT', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\CallController::leaveCall() */
		['name' => 'Call#leaveCall', 'url' => '/api/{apiVersion}/call/{token}', 'verb' => 'DELETE', 'requirements' => $requirements],
	],
];
