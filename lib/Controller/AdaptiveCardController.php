<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Middleware\Attribute\RequireLoggedInParticipant;
use OCA\Talk\Middleware\Attribute\RequireParticipant;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Room;
use OCA\Talk\Service\BotService;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

/**
 * Controller for Adaptive Cards functionality
 *
 * @psalm-import-type TalkChatMessage from ResponseDefinitions
 */
class AdaptiveCardController extends AEnvironmentAwareOCSController {

	protected ?Room $room = null;

	public function __construct(
		string $appName,
		IRequest $request,
		protected ChatManager $chatManager,
		protected BotService $botService,
		protected ParticipantService $participantService,
		protected IUserSession $userSession,
		protected ITimeFactory $timeFactory,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Submit a response to an Adaptive Card
	 *
	 * @param string $token Conversation token
	 * @param string $cardId Adaptive Card ID
	 * @param array $values Collected input values from the card
	 * @return DataResponse<Http::STATUS_OK, array{success: true}, array{}>|DataResponse<Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED, array{error: string}, array{}>
	 *
	 * 200: Response submitted successfully
	 * 400: Invalid card ID or values
	 * 401: Participant not authorized
	 */
	#[NoAdminRequired]
	#[RequireLoggedInParticipant]
	#[ApiRoute(verb: 'POST', url: '/api/{apiVersion}/adaptivecard/{token}/{cardId}/respond', requirements: [
		'apiVersion' => '(v1)',
		'token' => '[a-z0-9]{4,30}',
	])]
	#[OpenAPI(scope: 'bots')]
	public function submitResponse(string $token, string $cardId, array $values): DataResponse {
		try {
			// Get the current user
			$user = $this->userSession->getUser();
			if (!$user instanceof IUser) {
				return new DataResponse(['error' => 'User not found'], Http::STATUS_UNAUTHORIZED);
			}

			// Get the room
			$room = $this->room;
			if ($room === null) {
				return new DataResponse(['error' => 'Room not found'], Http::STATUS_BAD_REQUEST);
			}

			// Get the participant
			$participant = $this->participantService->getParticipant($room, $user->getUID());

			// TODO: Validate card ID exists and is active
			// TODO: Validate values against card schema
			// For Phase 1, we'll pass through to bot webhook

			// Send webhook to bot
			$this->botService->sendAdaptiveCardSubmissionWebhook(
				$room,
				$participant->getAttendee(),
				$cardId,
				$values
			);

			return new DataResponse(['success' => true]);
		} catch (UnauthorizedException $e) {
			return new DataResponse(['error' => 'Unauthorized'], Http::STATUS_UNAUTHORIZED);
		} catch (\Exception $e) {
			$this->logger->error('Error submitting adaptive card response: ' . $e->getMessage(), [
				'exception' => $e,
				'token' => $token,
				'cardId' => $cardId,
			]);
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
