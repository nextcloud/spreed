<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use InvalidArgumentException;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Middleware\Attribute\RequireLoggedInModeratorParticipant;
use OCA\Talk\Middleware\Attribute\RequireLoggedInParticipant;
use OCA\Talk\Model\BreakoutRoom;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\BreakoutRoomService;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Service\RoomFormatter;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\Comments\MessageTooLongException;
use OCP\IRequest;

/**
 * @psalm-import-type TalkRoom from ResponseDefinitions
 */
class BreakoutRoomController extends AEnvironmentAwareOCSController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected BreakoutRoomService $breakoutRoomService,
		protected ParticipantService $participantService,
		protected RoomFormatter $roomFormatter,
		protected ?string $userId,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Configure the breakout rooms
	 *
	 * @param 0|1|2|3 $mode Mode of the breakout rooms
	 * @psalm-param BreakoutRoom::MODE_* $mode
	 * @param int<1, 20> $amount Number of breakout rooms - Constants {@see BreakoutRoom::MINIMUM_ROOM_AMOUNT} and {@see BreakoutRoom::MAXIMUM_ROOM_AMOUNT}
	 * @param string $attendeeMap Mapping of the attendees to breakout rooms
	 * @return DataResponse<Http::STATUS_OK, list<TalkRoom>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Breakout rooms configured successfully
	 * 400: Configuring breakout rooms errored
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function configureBreakoutRooms(int $mode, int $amount, string $attendeeMap = '[]'): DataResponse {
		try {
			$rooms = $this->breakoutRoomService->setupBreakoutRooms($this->room, $mode, $amount, $attendeeMap);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		$rooms[] = $this->room;
		return new DataResponse($this->formatMultipleRooms($rooms), Http::STATUS_OK);
	}

	/**
	 * Remove the breakout rooms
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{}>
	 *
	 * 200: Breakout rooms removed successfully
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function removeBreakoutRooms(): DataResponse {
		$this->breakoutRoomService->removeBreakoutRooms($this->room);

		return new DataResponse($this->roomFormatter->formatRoom(
			$this->getResponseFormat(),
			[],
			$this->room,
			$this->participant,
		));
	}

	/**
	 * Broadcast a chat message to all breakout rooms
	 *
	 * @param string $message Message to broadcast
	 * @return DataResponse<Http::STATUS_CREATED, list<TalkRoom>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_REQUEST_ENTITY_TOO_LARGE, array{error: string}, array{}>
	 *
	 * 201: Chat message broadcasted successfully
	 * 400: Broadcasting chat message is not possible
	 * 413: Chat message too long
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function broadcastChatMessage(string $message): DataResponse {
		try {
			$rooms = $this->breakoutRoomService->broadcastChatMessage($this->room, $this->participant, $message);
		} catch (MessageTooLongException $e) {
			return new DataResponse(['error' => 'message'], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		$rooms[] = $this->room;
		return new DataResponse($this->formatMultipleRooms($rooms), Http::STATUS_CREATED);
	}

	/**
	 * Apply an attendee map to the breakout rooms
	 *
	 * @param string $attendeeMap JSON encoded mapping of the attendees to breakout rooms `array<int, int>`
	 * @return DataResponse<Http::STATUS_OK, list<TalkRoom>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Attendee map applied successfully
	 * 400: Applying attendee map is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function applyAttendeeMap(string $attendeeMap): DataResponse {
		try {
			$rooms = $this->breakoutRoomService->applyAttendeeMap($this->room, $attendeeMap);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
		$rooms[] = $this->room;
		return new DataResponse($this->formatMultipleRooms($rooms), Http::STATUS_OK);
	}

	/**
	 * Request assistance
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Assistance requested successfully
	 * 400: Requesting assistance is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInParticipant]
	public function requestAssistance(): DataResponse {
		try {
			$this->breakoutRoomService->requestAssistance($this->room);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->roomFormatter->formatRoom(
			$this->getResponseFormat(),
			[],
			$this->room,
			$this->participant,
		));
	}

	/**
	 * Reset the request for assistance
	 *
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Request for assistance reset successfully
	 * 400: Resetting the request for assistance is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInParticipant]
	public function resetRequestForAssistance(): DataResponse {
		try {
			$this->breakoutRoomService->resetRequestForAssistance($this->room);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->roomFormatter->formatRoom(
			$this->getResponseFormat(),
			[],
			$this->room,
			$this->participant,
		));
	}

	/**
	 * Start the breakout rooms
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkRoom>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Breakout rooms started successfully
	 * 400: Starting breakout rooms is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function startBreakoutRooms(): DataResponse {
		try {
			$rooms = $this->breakoutRoomService->startBreakoutRooms($this->room);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		$rooms[] = $this->room;
		return new DataResponse($this->formatMultipleRooms($rooms), Http::STATUS_OK);
	}

	/**
	 * Stop the breakout rooms
	 *
	 * @return DataResponse<Http::STATUS_OK, list<TalkRoom>, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Breakout rooms stopped successfully
	 * 400: Stopping breakout rooms is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function stopBreakoutRooms(): DataResponse {
		try {
			$rooms = $this->breakoutRoomService->stopBreakoutRooms($this->room);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		$rooms[] = $this->room;
		return new DataResponse($this->formatMultipleRooms($rooms), Http::STATUS_OK);
	}

	/**
	 * Switch to another breakout room
	 *
	 * @param string $target Target breakout room
	 * @return DataResponse<Http::STATUS_OK, TalkRoom, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Switched to breakout room successfully
	 * 400: Switching to breakout room is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInParticipant]
	public function switchBreakoutRoom(string $target): DataResponse {
		try {
			$room = $this->breakoutRoomService->switchBreakoutRoom($this->room, $this->participant, $target);
		} catch (InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse($this->roomFormatter->formatRoom(
			$this->getResponseFormat(),
			[],
			$room,
			$this->participant,
		));
	}

	/**
	 * @return list<TalkRoom>
	 */
	protected function formatMultipleRooms(array $rooms): array {
		$return = [];
		foreach ($rooms as $room) {
			try {
				$return[] = $this->roomFormatter->formatRoom(
					$this->getResponseFormat(),
					[],
					$room,
					$this->participantService->getParticipant($room, $this->userId),
					[],
					false,
					true
				);
			} catch (ParticipantNotFoundException $e) {
			}
		}
		return $return;
	}
}
