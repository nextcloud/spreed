<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
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
		/** @see \OCA\Talk\Controller\RoomController::unbindRoomFromObject() */
		['name' => 'Room#unbindRoomFromObject', 'url' => '/api/{apiVersion}/room/{token}/object', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
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
		/** @see \OCA\Talk\Controller\RoomController::joinFederatedRoom() */
		['name' => 'Room#joinFederatedRoom', 'url' => '/api/{apiVersion}/room/{token}/federation/active', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::resendInvitations() */
		['name' => 'Room#resendInvitations', 'url' => '/api/{apiVersion}/room/{token}/participants/resend-invitations', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::leaveRoom() */
		['name' => 'Room#leaveRoom', 'url' => '/api/{apiVersion}/room/{token}/participants/active', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::leaveFederatedRoom() */
		['name' => 'Room#leaveFederatedRoom', 'url' => '/api/{apiVersion}/room/{token}/federation/active', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
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
		/** @see \OCA\Talk\Controller\RoomController::markConversationAsImportant() */
		['name' => 'Room#markConversationAsImportant', 'url' => '/api/{apiVersion}/room/{token}/important', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::markConversationAsUnimportant() */
		['name' => 'Room#markConversationAsUnimportant', 'url' => '/api/{apiVersion}/room/{token}/important', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::markConversationAsSensitive() */
		['name' => 'Room#markConversationAsSensitive', 'url' => '/api/{apiVersion}/room/{token}/sensitive', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::markConversationAsInsensitive() */
		['name' => 'Room#markConversationAsInsensitive', 'url' => '/api/{apiVersion}/room/{token}/sensitive', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::verifyDialInPin() */
		['name' => 'Room#verifyDialInPin', 'url' => '/api/{apiVersion}/room/{token}/pin/{pin}', 'verb' => 'GET', 'requirements' => array_merge($requirementsWithToken, [
			'pin' => '\d{7,32}',
		]), 'postfix' => 'deprecated'],
		/** @see \OCA\Talk\Controller\RoomController::verifyDialInPin() */
		['name' => 'Room#verifyDialInPin', 'url' => '/api/{apiVersion}/room/{token}/verify-dialin', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::verifyDialOutNumber() */
		['name' => 'Room#verifyDialOutNumber', 'url' => '/api/{apiVersion}/room/{token}/verify-dialout', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::directDialIn() */
		['name' => 'Room#directDialIn', 'url' => '/api/{apiVersion}/room/direct-dial-in', 'verb' => 'POST', 'requirements' => $requirements],
		/** @see \OCA\Talk\Controller\RoomController::createGuestByDialIn() */
		['name' => 'Room#createGuestByDialIn', 'url' => '/api/{apiVersion}/room/{token}/open-dial-in', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::rejectedDialOutRequest() */
		['name' => 'Room#rejectedDialOutRequest', 'url' => '/api/{apiVersion}/room/{token}/rejected-dialout', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
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
		/** @see \OCA\Talk\Controller\RoomController::getCapabilities() */
		['name' => 'Room#getCapabilities', 'url' => '/api/{apiVersion}/room/{token}/capabilities', 'verb' => 'GET', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::setMentionPermissions() */
		['name' => 'Room#setMentionPermissions', 'url' => '/api/{apiVersion}/room/{token}/mention-permissions', 'verb' => 'PUT', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::archiveConversation() */
		['name' => 'Room#archiveConversation', 'url' => '/api/{apiVersion}/room/{token}/archive', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::unarchiveConversation() */
		['name' => 'Room#unarchiveConversation', 'url' => '/api/{apiVersion}/room/{token}/archive', 'verb' => 'DELETE', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::importEmailsAsParticipants() */
		['name' => 'Room#importEmailsAsParticipants', 'url' => '/api/{apiVersion}/room/{token}/import-emails', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
		/** @see \OCA\Talk\Controller\RoomController::scheduleMeeting() */
		['name' => 'Room#scheduleMeeting', 'url' => '/api/{apiVersion}/room/{token}/meeting', 'verb' => 'POST', 'requirements' => $requirementsWithToken],
	],
];
