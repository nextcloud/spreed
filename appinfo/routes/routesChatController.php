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
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
];

$requirementsWithMessageId = [
	'apiVersion' => '(v1)',
	'token' => '[a-z0-9]{4,30}',
	'messageId' => '[0-9]+',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\ChatController::receiveMessages() */
		['name' => 'Chat#receiveMessages', 'url' => '/api/{apiVersion}/chat/{token}', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\ChatController::sendMessage() */
		['name' => 'Chat#sendMessage', 'url' => '/api/{apiVersion}/chat/{token}', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\ChatController::clearHistory() */
		['name' => 'Chat#clearHistory', 'url' => '/api/{apiVersion}/chat/{token}', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\ChatController::deleteMessage() */
		['name' => 'Chat#deleteMessage', 'url' => '/api/{apiVersion}/chat/{token}/{messageId}', 'verb' => 'DELETE', 'requirements' => $requirementsWithMessageId],
		/** @see \OCA\Talk\Controller\ChatController::editMessage() */
		['name' => 'Chat#editMessage', 'url' => '/api/{apiVersion}/chat/{token}/{messageId}', 'verb' => 'PUT', 'requirements' => $requirementsWithMessageId],
		/** @see \OCA\Talk\Controller\ChatController::getMessageContext() */
		['name' => 'Chat#getMessageContext', 'url' => '/api/{apiVersion}/chat/{token}/{messageId}/context', 'verb' => 'GET', 'requirements' => $requirementsWithMessageId],
		/** @see \OCA\Talk\Controller\ChatController::setReminder() */
		['name' => 'Chat#setReminder', 'url' => '/api/{apiVersion}/chat/{token}/{messageId}/reminder', 'verb' => 'POST', 'requirements' => $requirementsWithMessageId],
		/** @see \OCA\Talk\Controller\ChatController::getReminder() */
		['name' => 'Chat#getReminder', 'url' => '/api/{apiVersion}/chat/{token}/{messageId}/reminder', 'verb' => 'GET', 'requirements' => $requirementsWithMessageId],
		/** @see \OCA\Talk\Controller\ChatController::deleteReminder() */
		['name' => 'Chat#deleteReminder', 'url' => '/api/{apiVersion}/chat/{token}/{messageId}/reminder', 'verb' => 'DELETE', 'requirements' => $requirementsWithMessageId],
		/** @see \OCA\Talk\Controller\ChatController::setReadMarker() */
		['name' => 'Chat#setReadMarker', 'url' => '/api/{apiVersion}/chat/{token}/read', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\ChatController::markUnread() */
		['name' => 'Chat#markUnread', 'url' => '/api/{apiVersion}/chat/{token}/read', 'verb' => 'DELETE', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\ChatController::mentions() */
		['name' => 'Chat#mentions', 'url' => '/api/{apiVersion}/chat/{token}/mentions', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\ChatController::shareObjectToChat() */
		['name' => 'Chat#shareObjectToChat', 'url' => '/api/{apiVersion}/chat/{token}/share', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\ChatController::getObjectsSharedInRoomOverview() */
		['name' => 'Chat#getObjectsSharedInRoomOverview', 'url' => '/api/{apiVersion}/chat/{token}/share/overview', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\ChatController::getObjectsSharedInRoom() */
		['name' => 'Chat#getObjectsSharedInRoom', 'url' => '/api/{apiVersion}/chat/{token}/share', 'verb' => 'GET', 'requirements' => $requirements],
	],
];
