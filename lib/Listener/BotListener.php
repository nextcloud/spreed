<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Listener;

use OCA\Talk\Chat\MessageParser;
use OCA\Talk\Events\BotInstallEvent;
use OCA\Talk\Events\BotUninstallEvent;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServer;
use OCA\Talk\Model\BotServerMapper;
use OCA\Talk\Service\BotService;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Server;
use Psr\Log\LoggerInterface;

/**
 * @template-implements IEventListener<Event>
 */
class BotListener implements IEventListener {
	public function __construct(
		protected BotServerMapper $botServerMapper,
		protected BotConversationMapper $botConversationMapper,
		protected BotService $botService,
		protected LoggerInterface $logger,
	) {
	}

	public function handle(Event $event): void {
		if ($event instanceof BotInstallEvent) {
			$this->handleBotInstallEvent($event);
			return;
		}
		if ($event instanceof BotUninstallEvent) {
			$this->handleBotUninstallEvent($event);
			return;
		}

		/** @var BotService $service */
		$service = Server::get(BotService::class);
		/** @var MessageParser $messageParser */
		$messageParser = Server::get(MessageParser::class);

		if ($event instanceof ChatMessageSentEvent) {
			$service->afterChatMessageSent($event, $messageParser);
			return;
		}
		if ($event instanceof SystemMessageSentEvent) {
			$service->afterSystemMessageSent($event, $messageParser);
		}
	}

	protected function handleBotInstallEvent(BotInstallEvent $event): void {
		try {
			$this->botService->validateBotParameters($event->getName(), $event->getSecret(), $event->getUrl(), $event->getDescription());
		} catch (\InvalidArgumentException $e) {
			$this->logger->error('Invalid data in bot install event: ' . $e->getMessage(), ['exception' => $e]);
			throw $e;
		}

		try {
			$bot = $this->botServerMapper->findByUrlAndSecret($event->getUrl(), $event->getSecret());

			$bot->setName($event->getName());
			$bot->setDescription($event->getDescription());
			$this->botServerMapper->update($bot);
		} catch (DoesNotExistException) {
			try {
				$this->botServerMapper->findByUrl($event->getUrl());
				$e = new \InvalidArgumentException('Bot with the same URL and a different secret is already registered');
				$this->logger->error('Invalid data in bot install event: ' . $e->getMessage(), ['exception' => $e]);
				throw $e;
			} catch (DoesNotExistException) {
			}

			$bot = new BotServer();
			$bot->setName($event->getName());
			$bot->setDescription($event->getDescription());
			$bot->setSecret($event->getSecret());
			$bot->setUrl($event->getUrl());
			$bot->setUrlHash(sha1($event->getUrl()));
			$bot->setState(Bot::STATE_ENABLED);
			$bot->setFeatures($event->getFeatures());
			$this->botServerMapper->insert($bot);
		}
	}

	protected function handleBotUninstallEvent(BotUninstallEvent $event): void {
		try {
			$bot = $this->botServerMapper->findByUrlAndSecret($event->getUrl(), $event->getSecret());
			$this->botConversationMapper->deleteByBotId($bot->getId());
			$this->botServerMapper->delete($bot);
		} catch (DoesNotExistException) {
		}
	}
}
