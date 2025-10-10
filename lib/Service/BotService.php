<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Events\BotDisabledEvent;
use OCA\Talk\Events\BotEnabledEvent;
use OCA\Talk\Events\BotInvokeEvent;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\ReactionAddedEvent;
use OCA\Talk\Events\ReactionRemovedEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotConversation;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServer;
use OCA\Talk\Model\BotServerMapper;
use OCA\Talk\Room;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\ICertificateManager;
use OCP\IConfig;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Security\ISecureRandom;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * @psalm-import-type InvocationData from BotInvokeEvent
 */
class BotService {
	private ActivityPubHelper $activityPubHelper;

	public function __construct(
		protected BotServerMapper $botServerMapper,
		protected BotConversationMapper $botConversationMapper,
		protected IClientService $clientService,
		protected IConfig $serverConfig,
		protected IUserSession $userSession,
		protected TalkSession $talkSession,
		protected ISession $session,
		protected ISecureRandom $secureRandom,
		protected IURLGenerator $urlGenerator,
		protected IFactory $l10nFactory,
		protected ITimeFactory $timeFactory,
		protected LoggerInterface $logger,
		protected ICertificateManager $certificateManager,
		protected IEventDispatcher $dispatcher,
	) {
		$this->activityPubHelper = new ActivityPubHelper();
	}

	public function afterBotEnabled(BotEnabledEvent $event): void {
		$this->invokeBots([$event->getBotServer()], $event->getRoom(), null, [
			'type' => 'Join',
			'actor' => $this->activityPubHelper->generateApplicationFromBot($event->getBotServer()),
			'object' => $this->activityPubHelper->generateCollectionFromRoom($event->getRoom()),
		]);
	}

	public function afterBotDisabled(BotDisabledEvent $event): void {
		$this->invokeBots([$event->getBotServer()], $event->getRoom(), null, [
			'type' => 'Leave',
			'actor' => $this->activityPubHelper->generateApplicationFromBot($event->getBotServer()),
			'object' => $this->activityPubHelper->generateCollectionFromRoom($event->getRoom()),
		]);
	}

	public function afterChatMessageSent(ChatMessageSentEvent $event, MessageParser $messageParser): void {
		$attendee = $event->getParticipant()?->getAttendee();
		if (!$attendee instanceof Attendee) {
			// No bots for bots
			return;
		}

		$bots = $this->getBotsForToken($event->getRoom()->getToken(), Bot::FEATURE_WEBHOOK | Bot::FEATURE_EVENT);
		if (empty($bots)) {
			return;
		}

		$inReplyTo = null;
		$parent = $event->getParent();
		if ($parent instanceof IComment) {
			$parentMessage = $messageParser->createMessage(
				$event->getRoom(),
				$event->getParticipant(),
				$parent,
				$this->l10nFactory->get('spreed', 'en', 'en')
			);
			$messageParser->parseMessage($parentMessage, true);
			$parentMessageData = [
				'message' => $parentMessage->getMessage(),
				'parameters' => $parentMessage->getMessageParameters(),
			];

			$inReplyTo = [
				'type' => 'Note',
				'actor' => $this->activityPubHelper->generatePersonFromMessageActor($parentMessage),
				'object' => $this->activityPubHelper->generateNote($parent, $parentMessageData, 'message'),
			];
		}

		$message = $messageParser->createMessage(
			$event->getRoom(),
			$event->getParticipant(),
			$event->getComment(),
			$this->l10nFactory->get('spreed', 'en', 'en')
		);
		$messageParser->parseMessage($message, true);
		$messageData = [
			'message' => $message->getMessage(),
			'parameters' => $message->getMessageParameters(),
		];

		$botServers = array_map(static fn (Bot $bot): BotServer => $bot->getBotServer(), $bots);

		$this->invokeBots($botServers, $event->getRoom(), $event->getComment(), [
			'type' => 'Create',
			'actor' => $this->activityPubHelper->generatePersonFromAttendee($attendee),
			'object' => $this->activityPubHelper->generateNote($event->getComment(), $messageData, 'message', $inReplyTo),
			'target' => $this->activityPubHelper->generateCollectionFromRoom($event->getRoom()),
		]);
	}

	public function afterSystemMessageSent(SystemMessageSentEvent $event, MessageParser $messageParser): void {
		$bots = $this->getBotsForToken($event->getRoom()->getToken(), Bot::FEATURE_WEBHOOK | Bot::FEATURE_EVENT);
		if (empty($bots)) {
			return;
		}

		$message = $messageParser->createMessage(
			$event->getRoom(),
			null,
			$event->getComment(),
			$this->l10nFactory->get('spreed', 'en', 'en')
		);
		$messageParser->parseMessage($message);
		$messageData = [
			'message' => $message->getMessage(),
			'parameters' => $message->getMessageParameters(),
		];

		$botServers = array_map(static fn (Bot $bot): BotServer => $bot->getBotServer(), $bots);

		$this->invokeBots($botServers, $event->getRoom(), $event->getComment(), [
			'type' => 'Activity',
			'actor' => $this->activityPubHelper->generatePersonFromMessageActor($message),
			'object' => $this->activityPubHelper->generateNote($event->getComment(), $messageData, $message->getMessageRaw()),
			'target' => $this->activityPubHelper->generateCollectionFromRoom($event->getRoom()),
		]);
	}

	public function afterReactionAdded(ReactionAddedEvent $event, MessageParser $messageParser): void {
		$bots = $this->getBotsForToken($event->getRoom()->getToken(), Bot::FEATURE_REACTION);
		if (empty($bots)) {
			return;
		}

		$message = $messageParser->createMessage(
			$event->getRoom(),
			null,
			$event->getMessage(),
			$this->l10nFactory->get('spreed', 'en', 'en')
		);
		$messageParser->parseMessage($message);
		$messageData = [
			'message' => $message->getMessage(),
			'parameters' => $message->getMessageParameters(),
		];

		$botServers = array_map(static fn (Bot $bot): BotServer => $bot->getBotServer(), $bots);

		$this->invokeBots($botServers, $event->getRoom(), $event->getMessage(), [
			'type' => 'Like',
			'actor' => $this->activityPubHelper->generatePersonFromMessageActor($message),
			'object' => $this->activityPubHelper->generateNote($event->getMessage(), $messageData, $message->getMessageRaw()),
			'target' => $this->activityPubHelper->generateCollectionFromRoom($event->getRoom()),
			'content' => $event->getReaction(),
		]);
	}

	public function afterReactionRemoved(ReactionRemovedEvent $event, MessageParser $messageParser): void {
		$bots = $this->getBotsForToken($event->getRoom()->getToken(), Bot::FEATURE_REACTION);
		if (empty($bots)) {
			return;
		}

		$message = $messageParser->createMessage(
			$event->getRoom(),
			null,
			$event->getMessage(),
			$this->l10nFactory->get('spreed', 'en', 'en')
		);
		$messageParser->parseMessage($message);
		$messageData = [
			'message' => $message->getMessage(),
			'parameters' => $message->getMessageParameters(),
		];

		$botServers = array_map(static fn (Bot $bot): BotServer => $bot->getBotServer(), $bots);

		$this->invokeBots($botServers, $event->getRoom(), $event->getMessage(), [
			'type' => 'Undo',
			'actor' => $this->activityPubHelper->generatePersonFromMessageActor($message),
			'object' => [
				'type' => 'Like',
				'actor' => $this->activityPubHelper->generatePersonFromMessageActor($message),
				'object' => $this->activityPubHelper->generateNote($event->getMessage(), $messageData, $message->getMessageRaw()),
				'target' => $this->activityPubHelper->generateCollectionFromRoom($event->getRoom()),
				'content' => $event->getReaction(),
			],
			'target' => $this->activityPubHelper->generateCollectionFromRoom($event->getRoom()),
		]);
	}

	/**
	 * @param BotServer[] $bots
	 * @param InvocationData $body
	 */
	protected function invokeBots(array $bots, Room $room, ?IComment $comment, array $body): void {
		$jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

		foreach ($bots as $bot) {
			if ($bot->getFeatures() & Bot::FEATURE_EVENT) {
				$event = new BotInvokeEvent($bot->getUrl(), $body);
				$this->dispatcher->dispatchTyped($event);

				if ($comment instanceof IComment) {
					if (!empty($event->getReactions())) {
						$reactionManager = Server::get(ReactionManager::class);
						foreach ($event->getReactions() as $reaction) {
							try {
								$reactionManager->addReactionMessage(
									$room,
									Attendee::ACTOR_BOTS,
									Attendee::ACTOR_BOT_PREFIX . $bot->getUrlHash(),
									$bot->getName(),
									(int)$comment->getId(),
									$reaction
								);
							} catch (\Exception $e) {
								$this->logger->error('Error while trying to react as a bot: ' . $e->getMessage(), ['exception' => $e]);
							}
						}
					}
					if (!empty($event->getAnswers())) {
						$chatManager = Server::get(ChatManager::class);
						foreach ($event->getAnswers() as $answer) {
							$creationDateTime = $this->timeFactory->getDateTime('now', new \DateTimeZone('UTC'));
							try {
								$replyTo = null;
								if ($answer['reply'] === true) {
									$replyTo = $comment;
								} elseif (is_int($answer['reply'])) {
									$replyTo = $chatManager->getParentComment($room, (string)$answer['reply']);
								}
								$chatManager->sendMessage(
									$room,
									null,
									Attendee::ACTOR_BOTS,
									Attendee::ACTOR_BOT_PREFIX . $bot->getUrlHash(),
									$answer['message'],
									$creationDateTime,
									$replyTo,
									$answer['referenceId'],
									$answer['silent'],
									rateLimitGuestMentions: false
								);
							} catch (\Exception $e) {
								$this->logger->error('Error while trying to answer as a bot: ' . $e->getMessage(), ['exception' => $e]);
							}
						}
					}
				}
			} else {
				$this->sendAsyncRequest($bot, $body, $jsonBody);
			}
		}
	}

	/**
	 * @param BotServer $botServer
	 * @param array $body
	 *                    #param string|null $jsonBody
	 */
	protected function sendAsyncRequest(BotServer $botServer, array $body, ?string $jsonBody = null): void {
		$jsonBody = $jsonBody ?? json_encode($body, JSON_THROW_ON_ERROR);

		$random = $this->secureRandom->generate(64);
		$hash = hash_hmac('sha256', $random . $jsonBody, $botServer->getSecret());
		$headers = [
			'Content-Type' => 'application/json',
			'X-Nextcloud-Talk-Random' => $random,
			'X-Nextcloud-Talk-Signature' => $hash,
			'X-Nextcloud-Talk-Backend' => rtrim($this->serverConfig->getSystemValueString('overwrite.cli.url'), '/') . '/',
			'OCS-APIRequest' => 'true',
		];

		$data = [
			'verify' => $this->certificateManager->getAbsoluteBundlePath(),
			'nextcloud' => [
				'allow_local_address' => true,
			],
			'headers' => $headers,
			'timeout' => 5,
			'body' => $jsonBody,
		];

		$client = $this->clientService->newClient();
		$promise = $client->postAsync($botServer->getUrl(), $data);

		$promise->then(function (IResponse $response) use ($botServer): void {
			if ($response->getStatusCode() !== Http::STATUS_OK && $response->getStatusCode() !== Http::STATUS_ACCEPTED) {
				$this->logger->error('Bot responded with unexpected status code (Received: ' . $response->getStatusCode() . '), increasing error count');
				$botServer->setErrorCount($botServer->getErrorCount() + 1);
				$botServer->setLastErrorDate($this->timeFactory->now());
				$botServer->setLastErrorMessage('UnexpectedStatusCode: ' . $response->getStatusCode());
				$this->botServerMapper->update($botServer);
			}
		}, function (\Exception $exception) use ($botServer): void {
			$this->logger->error('Bot error occurred, increasing error count', ['exception' => $exception]);
			$botServer->setErrorCount($botServer->getErrorCount() + 1);
			$botServer->setLastErrorDate($this->timeFactory->now());
			$botServer->setLastErrorMessage(get_class($exception) . ': ' . $exception->getMessage());
			$this->botServerMapper->update($botServer);
		});
	}

	/**
	 * @param Room $room
	 * @return array
	 * @psalm-return array{type: string, id: string, name: string}
	 */
	protected function getActor(Room $room): array {
		if (\OC::$CLI || $this->session->exists('talk-overwrite-actor-cli')) {
			return [
				'type' => Attendee::ACTOR_GUESTS,
				'id' => 'cli',
				'name' => 'Administration',
			];
		}

		if ($this->session->exists('talk-overwrite-actor-type')) {
			return [
				'type' => $this->session->get('talk-overwrite-actor-type'),
				'id' => $this->session->get('talk-overwrite-actor-id'),
				'name' => $this->session->get('talk-overwrite-actor-displayname'),
			];
		}

		if ($this->session->exists('talk-overwrite-actor-id')) {
			return [
				'type' => Attendee::ACTOR_USERS,
				'id' => $this->session->get('talk-overwrite-actor-id'),
				'name' => $this->session->get('talk-overwrite-actor-displayname'),
			];
		}

		$user = $this->userSession->getUser();
		if ($user instanceof IUser) {
			return [
				'type' => Attendee::ACTOR_USERS,
				'id' => $user->getUID(),
				'name' => $user->getDisplayName(),
			];
		}

		$sessionId = $this->talkSession->getSessionForRoom($room->getToken());
		$actorId = $sessionId ? sha1($sessionId) : 'failed-to-get-session';
		return [
			'type' => Attendee::ACTOR_GUESTS,
			'id' => $actorId,
			'name' => '',
		];
	}

	/**
	 * @param string $token
	 * @param int|null $requiredFeature
	 * @return Bot[]
	 */
	public function getBotsForToken(string $token, ?int $requiredFeature): array {
		$botConversations = $this->botConversationMapper->findForToken($token);

		if (empty($botConversations)) {
			return [];
		}

		$botIds = array_map(static fn (BotConversation $bot): int => $bot->getBotId(), $botConversations);

		$serversMap = [];
		$botServers = $this->botServerMapper->findByIds($botIds);
		foreach ($botServers as $botServer) {
			$serversMap[$botServer->getId()] = $botServer;
		}

		$bots = [];
		foreach ($botConversations as $botConversation) {
			if (!isset($serversMap[$botConversation->getBotId()])) {
				$this->logger->warning('Can not find bot by ID ' . $botConversation->getBotId() . ' for token ' . $botConversation->getToken());
				continue;
			}
			$botServer = $serversMap[$botConversation->getBotId()];

			if ($requiredFeature && !($botServer->getFeatures() & $requiredFeature)) {
				$this->logger->debug('Ignoring bot ID ' . $botConversation->getBotId() . ' because the feature (' . $requiredFeature . ') is disabled for it');
				continue;
			}

			$bot = new Bot(
				$botServer,
				$botConversation,
			);

			if ($bot->isEnabled()) {
				$bots[] = $bot;
			}
		}

		return $bots;
	}

	/**
	 * @throws \InvalidArgumentException
	 */
	public function validateBotParameters(string $name, string $secret, string $url, string $description): void {
		$nameLength = strlen($name);
		if ($nameLength === 0 || $nameLength > 64) {
			throw new \InvalidArgumentException('The provided name is too short or too long (min. 1 char, max. 64 chars)');
		}
		$secretLength = strlen($secret);
		if ($secretLength < 40 || $secretLength > 128) {
			throw new \InvalidArgumentException('The provided secret is too short (min. 40 chars, max. 128 chars)');
		}

		if (!$url || strlen($url) > 4000 || !(str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, Bot::URL_APP_PREFIX) || str_starts_with($url, Bot::URL_RESPONSE_ONLY_PREFIX))) {
			throw new \InvalidArgumentException('The provided URL is not a valid URL');
		}

		if (strlen($description) > 4000) {
			throw new \InvalidArgumentException('The provided description is too long (max. 4000 chars)');
		}
	}
}
