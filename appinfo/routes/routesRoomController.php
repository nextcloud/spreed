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
];

$requirementsWithToken = [
	'apiVersion' => '(v4)',
	'token' => '[a-z0-9]{4,30}',
];

return [
	'ocs' => [
		/** @see \OCA\Talk\Controller\RoomController::getRooms() */
		['name' => 'Room#getRooms', 'url' => '/api/{apiVersion}/room', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RoomController::getListedRooms() */
		['name' => 'Room#getListedRooms', 'url' => '/api/{apiVersion}/listed-room', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RoomController::createRoom() */
		['name' => 'Room#createRoom', 'url' => '/api/{apiVersion}/room', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RoomController::getNoteToSelfConversation() */
		['name' => 'Room#getNoteToSelfConversation', 'url' => '/api/{apiVersion}/room/note-to-self', 'verb' => 'GET', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RoomController::getSingleRoom() */
		['name' => 'Room#getSingleRoom', 'url' => '/api/{apiVersion}/room/{token}', 'verb' => 'GET', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::getBreakoutRooms() */
		['name' => 'Room#getBreakoutRooms', 'url' => '/api/{apiVersion}/room/{token}/breakout-rooms', 'verb' => 'GET', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::renameRoom() */
		['name' => 'Room#renameRoom', 'url' => '/api/{apiVersion}/room/{token}', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::deleteRoom() */
		['name' => 'Room#deleteRoom', 'url' => '/api/{apiVersion}/room/{token}', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::makePublic() */
		['name' => 'Room#makePublic', 'url' => '/api/{apiVersion}/room/{token}/public', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::makePrivate() */
		['name' => 'Room#makePrivate', 'url' => '/api/{apiVersion}/room/{token}/public', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setDescription() */
		['name' => 'Room#setDescription', 'url' => '/api/{apiVersion}/room/{token}/description', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setReadOnly() */
		['name' => 'Room#setReadOnly', 'url' => '/api/{apiVersion}/room/{token}/read-only', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setListable() */
		['name' => 'Room#setListable', 'url' => '/api/{apiVersion}/room/{token}/listable', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setPassword() */
		['name' => 'Room#setPassword', 'url' => '/api/{apiVersion}/room/{token}/password', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setPermissions() */
		['name' => 'Room#setPermissions', 'url' => '/api/{apiVersion}/room/{token}/permissions/{mode}', 'verb' => 'PUT', 'requirements' => array_merge($requirementsWithToken, [
			'mode' => '(call|default)',
		])],
		/** @see \OCA\Talk\Controller\RoomController::getParticipants() */
		['name' => 'Room#getParticipants', 'url' => '/api/{apiVersion}/room/{token}/participants', 'verb' => 'GET', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::getBreakoutRoomParticipants() */
		['name' => 'Room#getBreakoutRoomParticipants', 'url' => '/api/{apiVersion}/room/{token}/breakout-rooms/participants', 'verb' => 'GET', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::addParticipantToRoom() */
		['name' => 'Room#addParticipantToRoom', 'url' => '/api/{apiVersion}/room/{token}/participants', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::removeSelfFromRoom() */
		['name' => 'Room#removeSelfFromRoom', 'url' => '/api/{apiVersion}/room/{token}/participants/self', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::removeAttendeeFromRoom() */
		['name' => 'Room#removeAttendeeFromRoom', 'url' => '/api/{apiVersion}/room/{token}/attendees', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setAttendeePermissions() */
		['name' => 'Room#setAttendeePermissions', 'url' => '/api/{apiVersion}/room/{token}/attendees/permissions', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setAllAttendeesPermissions() */
		['name' => 'Room#setAllAttendeesPermissions', 'url' => '/api/{apiVersion}/room/{token}/attendees/permissions/all', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::joinRoom() */
		['name' => 'Room#joinRoom', 'url' => '/api/{apiVersion}/room/{token}/participants/active', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::resendInvitations() */
		['name' => 'Room#resendInvitations', 'url' => '/api/{apiVersion}/room/{token}/participants/resend-invitations', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::leaveRoom() */
		['name' => 'Room#leaveRoom', 'url' => '/api/{apiVersion}/room/{token}/participants/active', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setSessionState() */
		['name' => 'Room#setSessionState', 'url' => '/api/{apiVersion}/room/{token}/participants/state', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::promoteModerator() */
		['name' => 'Room#promoteModerator', 'url' => '/api/{apiVersion}/room/{token}/moderators', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::demoteModerator() */
		['name' => 'Room#demoteModerator', 'url' => '/api/{apiVersion}/room/{token}/moderators', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::addToFavorites() */
		['name' => 'Room#addToFavorites', 'url' => '/api/{apiVersion}/room/{token}/favorite', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::removeFromFavorites() */
		['name' => 'Room#removeFromFavorites', 'url' => '/api/{apiVersion}/room/{token}/favorite', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::getParticipantByDialInPin() */
		['name' => 'Room#getParticipantByDialInPin', 'url' => '/api/{apiVersion}/room/{token}/pin/{pin}', 'verb' => 'GET', 'requirements' => array_merge($requirementsWithToken, [
			'pin' => '\d{7,32}',
		])],
		/** @see \OCA\Talk\Controller\RoomController::createGuestByDialIn() */
		['name' => 'Room#createGuestByDialIn', 'url' => '/api/{apiVersion}/room/{token}/open-dial-in', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setNotificationLevel() */
		['name' => 'Room#setNotificationLevel', 'url' => '/api/{apiVersion}/room/{token}/notify', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setNotificationCalls() */
		['name' => 'Room#setNotificationCalls', 'url' => '/api/{apiVersion}/room/{token}/notify-calls', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setLobby() */
		['name' => 'Room#setLobby', 'url' => '/api/{apiVersion}/room/{token}/webinar/lobby', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setSIPEnabled() */
		['name' => 'Room#setSIPEnabled', 'url' => '/api/{apiVersion}/room/{token}/webinar/sip', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setRecordingConsent() */
		['name' => 'Room#setRecordingConsent', 'url' => '/api/{apiVersion}/room/{token}/recording-consent', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setMessageExpiration() */
		['name' => 'Room#setMessageExpiration', 'url' => '/api/{apiVersion}/room/{token}/message-expiration', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
	],
];
