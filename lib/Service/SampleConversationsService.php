<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\ReactionManager;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCP\AppFramework\Services\IAppConfig;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Security\ISecureRandom;
use Psr\Log\LoggerInterface;

class SampleConversationsService {
	public function __construct(
		protected IConfig $config,
		protected IAppConfig $appConfig,
		protected IUserManager $userManager,
		protected Manager $manager,
		protected ChatManager $chatManager,
		protected ReactionManager $reactionManager,
		protected RoomService $roomService,
		protected AvatarService $avatarService,
		protected ParticipantService $participantService,
		protected ISecureRandom $secureRandom,
		protected IRootFolder $rootFolder,
		protected IURLGenerator $url,
		protected ITimeFactory $timeFactory,
		protected IFactory $l10nFactory,
		protected IL10N $l,
		protected LoggerInterface $logger,
	) {
	}

	public function initialCreateSamples(string $userId): void {
		if (!$this->appConfig->getAppValueBool('create_samples', true)) {
			return;
		}

		$created = $this->config->getUserValue($userId, 'spreed', 'samples_created');
		if ($created !== '') {
			return;
		}

		$this->config->setUserValue($userId, 'spreed', 'samples_created', $this->timeFactory->now()->format(\DateTime::ATOM));

		$user = $this->userManager->get($userId);
		if (!$user instanceof IUser) {
			throw new \InvalidArgumentException('User not found');
		}

		$sampleDirectory = $this->appConfig->getAppValueString('samples_directory');
		if ($sampleDirectory !== '') {
			$this->logger->debug('Creating custom sample conversations for user ' . $userId . ' from ' . $sampleDirectory);
			$this->customSampleConversations($user, $sampleDirectory);
		} else {
			$this->logger->debug('Creating default sample conversations for user ' . $userId);
			$this->defaultSampleConversation($user);
		}
	}

	protected function defaultSampleConversation(IUser $user): void {
		$room = $this->roomService->createConversation(
			Room::TYPE_GROUP,
			$this->l->t('Let\'s get started!'),
			$user,
			Room::OBJECT_TYPE_SAMPLE,
			$user->getUID()
		);

		$this->avatarService->setAvatarFromEmoji($room, '💡', null);

		$this->roomService->setDescription($room, $this->l->t('**Nextcloud Talk** is a secure, self-hosted communication platform that integrates seamlessly with the Nextcloud ecosystem.

#### Key Features of Nextcloud Talk:

* Chat and messaging in private and group chats
* Voice and video calls
* File sharing and integration with other Nextcloud apps
* Customizable conversation settings, moderation and privacy controls
* Web, desktop and mobile (iOS and Android)
* Private & secure communication

 Find out more in the [user documentation](https://docs.nextcloud.com/server/latest/user_manual/en/talk/index.html).'));

		$messages = [
			$this->l->t('# Welcome to Nextcloud Talk

Nextcloud Talk is a private and powerful messaging app that integrates with Nextcloud. Chat in private or group conversations, collaborate over voice and video calls, organize webinars and events, customize your conversations and more.'),
			$this->l->t('## 🎨 Format texts to create rich messages

In Nextcloud Talk, you can use Markdown syntax to format your messages. For example, apply **bold** or *italic* formatting, or `highlight texts as code`. You can even create tables and add headings to your text.

Need to fix a typo or change formatting? Edit your message by clicking "Edit message" in the message menu.'),
			$this->l->t('## 🔗 Add attachments and links

Attach files from your Nextcloud Hub using the "+" button. Share items from Files and various Nextcloud apps. Some apps even support interactive widgets, for example, the Text app.')
			. "\n\n" . '{FILE:Readme.md}',
			$this->l->t('## 💭 Let the conversations flow: mention users, react to messages and more

You can mention everybody in the conversation by using %s or mention specific participants by typing "@" and picking their name from the list.', ['@all'])
			. "\n" . '{REACTION:😍}{REACTION:👍}',
			'{REPLY}' . $this->l->t('You can reply to messages, forward them to other chats and people, or copy message content.'),
			$this->l->t('## ✨ Do more with Smart Picker

Simply type "/" or go to the "+" menu to open the Smart Picker where you can attach various content to your messages. You can configure the Smart Picker to be able to add items from Nextcloud apps, GIFs, map locations, AI generated content and much more.'),
			$this->l->t('## ⚙️ Manage conversation settings

In the conversation menu, you can access various settings to manage your conversations, such as:
* Edit conversation info
* Manage notifications
* Apply numerous moderation rules
* Configure access and security
* Enable bots
* and more!'),
		];

		$this->fillConversation($user, $room, $messages);
	}

	protected function fillConversation(IUser $user, Room $room, array $messages): void {
		$userFolder = $this->rootFolder->getUserFolder($user->getUID());

		$previous = null;
		foreach ($messages as $message) {
			$message = trim($message);
			$replyTo = '';
			if (str_starts_with($message, '{REPLY}')) {
				$message = trim(str_replace('{REPLY}', '', $message));
				$replyTo = $previous->getId();
			}
			if (str_contains($message, '{FILE:')) {
				preg_match_all('/{FILE:([^}]*)}/', $message, $matches);
				foreach ($matches[1] as $match) {
					try {
						$node = $userFolder->get($match);
						$message = str_replace('{FILE:' . $match . '}', $this->url->linkToRouteAbsolute(
							'files.view.showFile', ['fileid' => $node->getId()]
						), $message);
					} catch (NotFoundException|NotPermittedException) {
						$message = trim(str_replace('{FILE:' . $match . '}', '', $message));
					}
				}
			}

			$reactions = [];
			if (str_contains($message, '{REACTION:')) {
				preg_match_all('/{REACTION:([^}]*)}/', $message, $matches);
				$reactions = $matches[1];
				$message = trim(preg_replace('/{REACTION:([^}]*)}/', '', $message));
			}

			$previous = $this->chatManager->postSampleMessage($room, $message, $replyTo);

			foreach ($reactions as $reaction) {
				$this->reactionManager->addReactionMessage($room, Attendee::ACTOR_GUESTS, Attendee::ACTOR_ID_SAMPLE, '', (int)$previous->getId(), $reaction);
			}
		}
	}

	protected function customSampleConversations(IUser $user, string $sampleDirectory): void {
		$iterator = $this->l10nFactory->getLanguageIterator($user);
		do {
			$lang = $iterator->current();
			if (file_exists($sampleDirectory . '/' . $lang)) {
				break;
			}
			$iterator->next();
		} while ($lang !== 'en' && $iterator->valid());

		if (!file_exists($sampleDirectory . '/' . $lang)) {
			return;
		}

		$directory = new \DirectoryIterator($sampleDirectory . '/' . $lang);
		foreach ($directory as $file) {
			if ($file->isDot() || $file->getExtension() !== 'md') {
				continue;
			}

			$this->createSampleFromFile($user, $file->getPathname());
		}
	}

	protected function createSampleFromFile(IUser $user, string $filePath): void {
		$content = file_get_contents($filePath);

		$messages = explode("\n---\n", $content);
		$detailsBlock = array_shift($messages);
		$details = explode("\n", $detailsBlock);

		$name = $emoji = $color = null;
		foreach ($details as $detail) {
			if (str_starts_with($detail, 'NAME:')) {
				$name = trim(substr($detail, strlen('NAME:')));
			}
			if (str_starts_with($detail, 'EMOJI:')) {
				$emoji = trim(substr($detail, strlen('EMOJI:')));
			}
			if (str_starts_with($detail, 'COLOR:')) {
				$color = substr(trim(substr($detail, strlen('COLOR:'))), 1);
			}
		}

		if ($name === null) {
			$this->logger->error('Sample conversation ' . $filePath . ' has no name defined');
			return;
		}

		$room = $this->roomService->createConversation(Room::TYPE_GROUP, $name, $user, Room::OBJECT_TYPE_SAMPLE, $user->getUID());
		if ($emoji !== null) {
			$this->avatarService->setAvatarFromEmoji($room, $emoji, $color);
		}

		if (isset($messages[0]) && str_starts_with($messages[0], 'DESCRIPTION:')) {
			$description = array_shift($messages);
			$this->roomService->setDescription($room, trim(substr($description, strlen('DESCRIPTION:'))));
		}

		$this->fillConversation($user, $room, $messages);
	}
}
