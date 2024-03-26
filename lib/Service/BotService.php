<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk\Service;

use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotConversation;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServerMapper;
use OCA\Talk\Room;
use OCA\Talk\TalkSession;
use OCP\AppFramework\Http;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\Http\Client\IResponse;
use OCP\IConfig;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class BotService {
	public function __construct(
		protected BotServerMapper       $botServerMapper,
		protected BotConversationMapper $botConversationMapper,
		protected IClientService        $clientService,
		protected IConfig               $serverConfig,
		protected IUserSession          $userSession,
		protected TalkSession           $talkSession,
		protected ISession              $session,
		protected ISecureRandom         $secureRandom,
		protected IURLGenerator         $urlGenerator,
		protected IFactory              $l10nFactory,
		protected ITimeFactory          $timeFactory,
		protected LoggerInterface       $logger,
	) {
	}

	public function afterChatMessageSent(ChatMessageSentEvent $event, MessageParser $messageParser): void {
		$attendee = $event->getParticipant()?->getAttendee();
		if (!$attendee instanceof Attendee) {
			// No bots for bots
			return;
		}

		$bots = $this->getBotsForToken($event->getRoom()->getToken(), Bot::FEATURE_WEBHOOK);
		if (empty($bots)) {
			return;
		}

		$message = $messageParser->createMessage(
			$event->getRoom(),
			$event->getParticipant(),
			$event->getComment(),
			$this->l10nFactory->get('spreed', 'en', 'en')
		);
		$messageParser->parseMessage($message);
		$messageData = [
			'message' => $message->getMessage(),
			'parameters' => $message->getMessageParameters(),
		];

		$this->sendAsyncRequests($bots, [
			'type' => 'Create',
			'actor' => [
				'type' => 'Person',
				'id' => $attendee->getActorType() . '/' . $attendee->getActorId(),
				'name' => $attendee->getDisplayName(),
			],
			'object' => [
				'type' => 'Note',
				'id' => $event->getComment()->getId(),
				'name' => 'message',
				'content' => json_encode($messageData, JSON_THROW_ON_ERROR),
				'mediaType' => 'text/markdown', // FIXME or text/plain when markdown is disabled
			],
			'target' => [
				'type' => 'Collection',
				'id' => $event->getRoom()->getToken(),
				'name' => $event->getRoom()->getName(),
			]
		]);
	}

	public function afterSystemMessageSent(SystemMessageSentEvent $event, MessageParser $messageParser): void {
		$bots = $this->getBotsForToken($event->getRoom()->getToken(), Bot::FEATURE_WEBHOOK);
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

		$this->sendAsyncRequests($bots, [
			'type' => 'Activity',
			'actor' => [
				'type' => 'Person',
				'id' => $message->getActorType() . '/' . $message->getActorId(),
				'name' => $message->getActorDisplayName(),
			],
			'object' => [
				'type' => 'Note',
				'id' => $event->getComment()->getId(),
				'name' => $message->getMessageRaw(),
				'content' => json_encode($messageData),
				'mediaType' => 'text/markdown',
			],
			'target' => [
				'type' => 'Collection',
				'id' => $event->getRoom()->getToken(),
				'name' => $event->getRoom()->getName(),
			]
		]);
	}

	/**
	 * @param Bot[] $bots
	 * @param array $body
	 */
	protected function sendAsyncRequests(array $bots, array $body): void {
		$jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

		foreach ($bots as $bot) {
			$botServer = $bot->getBotServer();
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
				'verify' => true,
				'nextcloud' => [
					'allow_local_address' => true,
				],
				'headers' => $headers,
				'timeout' => 5,
				'body' => json_encode($body),
			];

			$client = $this->clientService->newClient();
			$promise = $client->postAsync($botServer->getUrl(), $data);

			$promise->then(function (IResponse $response) use ($botServer) {
				if ($response->getStatusCode() !== Http::STATUS_OK && $response->getStatusCode() !== Http::STATUS_ACCEPTED) {
					$this->logger->error('Bot responded with unexpected status code (Received: ' . $response->getStatusCode() . '), increasing error count');
					$botServer->setErrorCount($botServer->getErrorCount() + 1);
					$botServer->setLastErrorDate($this->timeFactory->now());
					$botServer->setLastErrorMessage('UnexpectedStatusCode: ' . $response->getStatusCode());
					$this->botServerMapper->update($botServer);
				}
			}, function (\Exception $exception) use ($botServer) {
				$this->logger->error('Bot error occurred, increasing error count', ['exception' => $exception]);
				$botServer->setErrorCount($botServer->getErrorCount() + 1);
				$botServer->setLastErrorDate($this->timeFactory->now());
				$botServer->setLastErrorMessage(get_class($exception) . ': ' . $exception->getMessage());
				$this->botServerMapper->update($botServer);
			});
		}
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
			'name' => $user->getDisplayName(),
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

		$url = filter_var($url);
		if (!$url || strlen($url) > 4000 || !(str_starts_with($url, 'http://') || str_starts_with($url, 'https://'))) {
			throw new \InvalidArgumentException('The provided URL is not a valid URL');
		}

		if (strlen($description) > 4000) {
			throw new \InvalidArgumentException('The provided description is too long (max. 4000 chars)');
		}
	}
}
