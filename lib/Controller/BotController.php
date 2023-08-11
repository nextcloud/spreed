<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Controller;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Exceptions\ReactionAlreadyExistsException;
use OCA\Talk\Exceptions\ReactionNotSupportedException;
use OCA\Talk\Exceptions\ReactionOutOfContextException;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Manager;
use OCA\Talk\Middleware\Attribute\RequireLoggedInModeratorParticipant;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotConversation;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServer;
use OCA\Talk\Model\BotServerMapper;
use OCA\Talk\ResponseDefinitions;
use OCA\Talk\Service\BotService;
use OCA\Talk\Service\ChecksumVerificationService;
use OCA\Talk\Service\ParticipantService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\MessageTooLongException;
use OCP\Comments\NotFoundException;
use OCP\IRequest;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type SpreedAdminBot from ResponseDefinitions
 * @psalm-import-type SpreedBot from ResponseDefinitions
 */
class BotController extends AEnvironmentAwareController {
	public function __construct(
		string $appName,
		IRequest $request,
		protected ChatManager $chatManager,
		protected ParticipantService $participantService,
		protected ITimeFactory $timeFactory,
		protected ChecksumVerificationService $checksumVerificationService,
		protected BotConversationMapper $botConversationMapper,
		protected BotServerMapper $botServerMapper,
		protected BotService $botService,
		protected Manager $manager,
		protected ReactionManager $reactionManager,
		protected LoggerInterface $logger,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * @param string $token
	 * @param string $message
	 * @return Bot
	 * @throws \InvalidArgumentException When the request could not be linked with a bot
	 */
	protected function getBotFromHeaders(string $token, string $message): Bot {
		$random = $this->request->getHeader('X-Nextcloud-Talk-Bot-Random');
		if (empty($random) || strlen($random) < 32) {
			$this->logger->error('Invalid Random received from bot response');
			throw new \InvalidArgumentException('Invalid Random received from bot response', Http::STATUS_BAD_REQUEST);
		}
		$checksum = $this->request->getHeader('X-Nextcloud-Talk-Bot-Signature');
		if (empty($checksum)) {
			$this->logger->error('Invalid Signature received from bot response');
			throw new \InvalidArgumentException('Invalid Signature received from bot response', Http::STATUS_BAD_REQUEST);
		}

		$bots = $this->botService->getBotsForToken($token, Bot::FEATURE_RESPONSE);
		foreach ($bots as $botAttempt) {
			try {
				$this->checksumVerificationService->validateRequest(
					$random,
					$checksum,
					$botAttempt->getBotServer()->getSecret(),
					$message
				);

				if (!($botAttempt->getBotServer()->getFeatures() & Bot::FEATURE_RESPONSE)) {
					$this->logger->debug('Not accepting response from bot ID ' . $botAttempt->getBotServer()->getId() . ' because the feature is disabled for it');
					throw new \InvalidArgumentException('Feature not enabled for bot', Http::STATUS_BAD_REQUEST);
				}

				return $botAttempt;
			} catch (UnauthorizedException) {
			}
		}

		$this->logger->debug('No valid Bot entry found');
		throw new \InvalidArgumentException('No valid Bot entry found', Http::STATUS_UNAUTHORIZED);
	}

	/**
	 * Sends a new chat message to the given room
	 *
	 * The author and timestamp are automatically set to the current user/guest
	 * and time.
	 *
	 * @param string $token Conversation token
	 * @param string $message The message to send
	 * @param string $referenceId For the message to be able to later identify it again
	 * @param int $replyTo Parent id which this message is a reply to
	 * @param bool $silent If sent silent the chat message will not create any notifications
	 * @return DataResponse<Http::STATUS_CREATED|Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_REQUEST_ENTITY_TOO_LARGE, array<empty>, array{}>
	 *
	 * 201: Message sent successfully
	 * 400: Sending message is not possible
	 * 401: Sending message is not allowed
	 * 404: Room or session not found
	 * 413: Message too long
	 */
	#[BruteForceProtection(action: 'bot')]
	#[PublicPage]
	public function sendMessage(string $token, string $message, string $referenceId = '', int $replyTo = 0, bool $silent = false): DataResponse {
		try {
			$bot = $this->getBotFromHeaders($token, $message);
		} catch (\InvalidArgumentException $e) {
			/** @var Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED $status */
			$status = $e->getCode();
			$response = new DataResponse([], $status);
			if ($e->getCode() === Http::STATUS_UNAUTHORIZED) {
				$response->throttle(['action' => 'bot']);
			}
			return $response;
		}

		$room = $this->manager->getRoomByToken($token);

		$actorType = Attendee::ACTOR_BOTS;
		$actorId = Attendee::ACTOR_BOT_PREFIX . $bot->getBotServer()->getUrlHash();

		$parent = null;
		if ($replyTo !== 0) {
			try {
				$parent = $this->chatManager->getParentComment($room, (string) $replyTo);
			} catch (NotFoundException $e) {
				// Someone is trying to reply cross-rooms or to a non-existing message
				return new DataResponse([], Http::STATUS_BAD_REQUEST);
			}
		}

		$this->participantService->ensureOneToOneRoomIsFilled($room);
		$creationDateTime = $this->timeFactory->getDateTime('now', new \DateTimeZone('UTC'));

		try {
			$this->chatManager->sendMessage($room, $this->participant, $actorType, $actorId, $message, $creationDateTime, $parent, $referenceId, $silent);
		} catch (MessageTooLongException) {
			return new DataResponse([], Http::STATUS_REQUEST_ENTITY_TOO_LARGE);
		} catch (\Exception) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse([], Http::STATUS_CREATED);
	}

	/**
	 * Adds a reaction to a chat message
	 *
	 * @param string $token Conversation token
	 * @param int $messageId ID of the message
	 * @param string $reaction Reaction to add
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CREATED|Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED|Http::STATUS_NOT_FOUND, array<empty>, array{}>
	 *
	 * 200: Reaction already exists
	 * 201: Reacted successfully
	 * 400: Reacting is not possible
	 * 401: Reacting is not allowed
	 * 404: Reaction not found
	 */
	#[BruteForceProtection(action: 'bot')]
	#[PublicPage]
	public function react(string $token, int $messageId, string $reaction): DataResponse {
		try {
			$bot = $this->getBotFromHeaders($token, $reaction);
		} catch (\InvalidArgumentException $e) {
			/** @var Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED $status */
			$status = $e->getCode();
			$response = new DataResponse([], $status);
			if ($e->getCode() === Http::STATUS_UNAUTHORIZED) {
				$response->throttle(['action' => 'bot']);
			}
			return $response;
		}

		$room = $this->manager->getRoomByToken($token);

		$actorType = Attendee::ACTOR_BOTS;
		$actorId = Attendee::ACTOR_BOT_PREFIX . $bot->getBotServer()->getUrlHash();

		try {
			$this->reactionManager->addReactionMessage(
				$room,
				$actorType,
				$actorId,
				$messageId,
				$reaction
			);
		} catch (NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (ReactionAlreadyExistsException) {
			return new DataResponse([], Http::STATUS_OK);
		} catch (ReactionNotSupportedException | ReactionOutOfContextException | \Exception) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse([], Http::STATUS_CREATED);
	}

	/**
	 * Deletes a reaction from a chat message
	 *
	 * @param string $token Conversation token
	 * @param int $messageId ID of the message
	 * @param string $reaction Reaction to delete
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_BAD_REQUEST|Http::STATUS_NOT_FOUND|Http::STATUS_UNAUTHORIZED, array<empty>, array{}>
	 *
	 * 200: Reaction deleted successfully
	 * 400: Reacting is not possible
	 * 401: Reacting is not allowed
	 * 404: Reaction not found
	 */
	#[BruteForceProtection(action: 'bot')]
	#[PublicPage]
	public function deleteReaction(string $token, int $messageId, string $reaction): DataResponse {
		try {
			$bot = $this->getBotFromHeaders($token, $reaction);
		} catch (\InvalidArgumentException $e) {
			/** @var Http::STATUS_BAD_REQUEST|Http::STATUS_UNAUTHORIZED $status */
			$status = $e->getCode();
			$response = new DataResponse([], $status);
			if ($e->getCode() === Http::STATUS_UNAUTHORIZED) {
				$response->throttle(['action' => 'bot']);
			}
			return $response;
		}

		$room = $this->manager->getRoomByToken($token);

		$actorType = Attendee::ACTOR_BOTS;
		$actorId = Attendee::ACTOR_BOT_PREFIX . $bot->getBotServer()->getUrlHash();

		try {
			$this->reactionManager->deleteReactionMessage(
				$room,
				$actorType,
				$actorId,
				$messageId,
				$reaction
			);
		} catch (ReactionNotSupportedException | ReactionOutOfContextException | NotFoundException) {
			return new DataResponse([], Http::STATUS_NOT_FOUND);
		} catch (\Exception) {
			return new DataResponse([], Http::STATUS_BAD_REQUEST);
		}

		return new DataResponse([], Http::STATUS_OK);
	}

	/**
	 * List admin bots
	 *
	 * @return DataResponse<Http::STATUS_OK, SpreedAdminBot[], array{}>
	 *
	 * 200: Bot list returned
	 */
	public function adminListBots(): DataResponse {
		$data = [];
		$bots = $this->botServerMapper->getAllBots();
		foreach ($bots as $bot) {
			$botData = $bot->jsonSerialize();
			unset($botData['secret']);
			$data[] = $botData;
		}

		return new DataResponse($data);
	}

	/**
	 * List bots
	 *
	 * @return DataResponse<Http::STATUS_OK, SpreedBot[], array{}>
	 *
	 * 200: Bot list returned
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function listBots(): DataResponse {
		$alreadyInstalled = array_map(static function (BotConversation $bot): int {
			return $bot->getBotId();
		}, $this->botConversationMapper->findForToken($this->room->getToken()));

		$data = [];
		$bots = $this->botServerMapper->getAllBots();
		foreach ($bots as $bot) {
			$botData = $this->formatBot($bot, in_array($bot->getId(), $alreadyInstalled, true));
			if ($botData !== null) {
				$data[] = $botData;
			}
		}

		return new DataResponse($data);
	}

	/**
	 * Enables a bot
	 *
	 * @param int $botId ID of the bot
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CREATED, ?SpreedBot, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Bot already enabled
	 * 201: Bot enabled successfully
	 * 400: Enabling bot is not possible
	 */
	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function enableBot(int $botId): DataResponse {
		try {
			$bot = $this->botServerMapper->findById($botId);
		} catch (DoesNotExistException) {
			return new DataResponse([
				'error' => 'bot',
			], Http::STATUS_BAD_REQUEST);
		}

		if ($bot->getState() !== Bot::STATE_ENABLED) {
			return new DataResponse([
				'error' => 'bot',
			], Http::STATUS_BAD_REQUEST);
		}

		$alreadyInstalled = array_map(static function (BotConversation $bot): int {
			return $bot->getBotId();
		}, $this->botConversationMapper->findForToken($this->room->getToken()));

		if (in_array($botId, $alreadyInstalled)) {
			return new DataResponse($this->formatBot($bot, true), Http::STATUS_OK);
		}

		$conversationBot = new BotConversation();
		$conversationBot->setBotId($botId);
		$conversationBot->setToken($this->room->getToken());
		$conversationBot->setState(Bot::STATE_ENABLED);

		$this->botConversationMapper->insert($conversationBot);
		return new DataResponse($this->formatBot($bot, true), Http::STATUS_CREATED);
	}

	/**
	 * Disables a bot
	 *
	 * @param int $botId ID of the bot
	 * @return DataResponse<Http::STATUS_OK, ?SpreedBot, array{}>|DataResponse<Http::STATUS_BAD_REQUEST, array{error: string}, array{}>
	 *
	 * 200: Bot already enabled
	 * 201: Bot enabled successfully
	 * 400: Enabling bot is not possible
	 */

	#[NoAdminRequired]
	#[RequireLoggedInModeratorParticipant]
	public function disableBot(int $botId): DataResponse {
		try {
			$bot = $this->botServerMapper->findById($botId);
		} catch (DoesNotExistException) {
			return new DataResponse([
				'error' => 'bot',
			], Http::STATUS_BAD_REQUEST);
		}

		if ($bot->getState() !== Bot::STATE_ENABLED) {
			return new DataResponse([
				'error' => 'bot',
			], Http::STATUS_BAD_REQUEST);
		}

		$this->botConversationMapper->deleteByBotIdAndTokens($botId, [$this->room->getToken()]);
		return new DataResponse($this->formatBot($bot, false), Http::STATUS_OK);
	}

	/**
	 * @param BotServer $bot
	 * @param bool $conversationEnabled
	 * @return array|null
	 * @psalm-return ?SpreedBot
	 */
	protected function formatBot(BotServer $bot, bool $conversationEnabled): ?array {
		$state = $conversationEnabled ? Bot::STATE_ENABLED : Bot::STATE_DISABLED;

		if ($bot->getState() === Bot::STATE_NO_SETUP) {
			if ($state === Bot::STATE_DISABLED) {
				return null;
			}
			$state = Bot::STATE_NO_SETUP;
		}

		return [
			'id' => $bot->getId(),
			'name' => $bot->getName(),
			'description' => $bot->getDescription(),
			'state' => $state,
		];
	}
}
