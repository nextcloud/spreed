<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2019 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Chat\Changelog;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Manager as RoomManager;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use OCP\PreConditionNotMetException;

class Manager {

	public function __construct(
		protected IConfig $config,
		protected IDBConnection $connection,
		protected RoomManager $roomManager,
		protected ChatManager $chatManager,
		protected ITimeFactory $timeFactory,
		protected IL10N $l,
	) {
	}

	public function getChangelogForUser(string $userId): int {
		return (int)$this->config->getUserValue($userId, 'spreed', 'changelog', '0');
	}

	public function updateChangelog(string $userId): void {
		$logs = $this->getChangelogs();
		$hasReceivedLog = $this->getChangelogForUser($userId);
		$shouldHaveReceived = count($logs);

		if ($hasReceivedLog === $shouldHaveReceived) {
			return;
		}

		try {
			$this->config->setUserValue($userId, 'spreed', 'changelog', (string)$shouldHaveReceived, (string)$hasReceivedLog);
		} catch (PreConditionNotMetException) {
			// Parallel request won the race
			return;
		}

		$room = $this->roomManager->getChangelogRoom($userId);

		foreach ($logs as $key => $changelog) {
			if ($key < $hasReceivedLog || $changelog === '') {
				continue;
			}
			$this->chatManager->addChangelogMessage($room, $changelog);
		}
	}

	public function getChangelogs(): array {
		return [
			$this->l->t(
				"## Welcome to Nextcloud Talk!\n"
				. 'In this conversation you will be informed about new features available in Nextcloud Talk.'
			),
			$this->l->t('## New in Talk %s', ['6']),
			$this->l->t('- Microsoft Edge and Safari can now be used to participate in audio and video calls'),
			$this->l->t('- One-to-one conversations are now persistent and cannot be turned into group conversations by accident anymore. Also when one of the participants leaves the conversation, the conversation is not automatically deleted anymore. Only if both participants leave, the conversation is deleted from the server'),
			$this->l->t('- You can now notify all participants by posting "@all" into the chat'),
			$this->l->t('- With the "arrow-up" key you can repost your last message'),
			$this->l->t('- Talk can now have commands, send "/help" as a chat message to see if your administrator configured some'),
			$this->l->t('- With projects you can create quick links between conversations, files and other items'),
			$this->l->t('## New in Talk %s', ['7']),
			$this->l->t('- You can now mention guests in the chat'),
			$this->l->t('- Conversations can now have a lobby. This will allow moderators to join the chat and call already to prepare the meeting, while users and guests have to wait'),
			$this->l->t('## New in Talk %s', ['8']),
			$this->l->t('- You can now directly reply to messages giving the other users more context what your message is about'),
			$this->l->t('- Searching for conversations and participants will now also filter your existing conversations, making it much easier to find previous conversations'),
			$this->l->t('- You can now add custom user groups to conversations when the circles app is installed'),
			$this->l->t('## New in Talk %s', ['9']),
			$this->l->t('- Check out the new grid and call view'),
			$this->l->t('- You can now upload and drag\'n\'drop files directly from your device into the chat'),
			$this->l->t('- Shared files are now opened directly inside the chat view with the viewer apps'),
			$this->l->t('## New in Talk %s', ['10']),
			$this->l->t('- You can now search for chats and messages in the unified search in the top bar'),
			$this->l->t('- Spice up your messages with emojis from the emoji picker'),
			$this->l->t('- You can now change your camera and microphone while being in a call'),
			$this->l->t('## New in Talk %s', ['11']),
			$this->l->t('- Give your conversations some context with a description and open it up so logged in users can find it and join themselves'),
			$this->l->t('- See a read status and send failed messages again'),
			$this->l->t('- Raise your hand in a call with the R key'),
			$this->l->t('## New in Talk %s', ['12']),
			$this->l->t('- Join the same conversation and call from multiple devices'),
			$this->l->t('- Send voice messages, share your location or contact details'),
			$this->l->t('- Add groups to a conversation and new group members will automatically be added as participants'),
			$this->l->t('## New in Talk %s', ['13']),
			$this->l->t('- A preview of your audio and video is shown before joining a call'),
			$this->l->t('- You can now blur your background in the newly designed call view'),
			$this->l->t('- Moderators can now assign general and individual permissions to participants'),
			$this->l->t('## New in Talk %s', ['14']),
			$this->l->t('- You can now react to chat messages'),
			$this->l->t('- In the sidebar you can now find an overview of the latest shared items'),
			$this->l->t('## New in Talk %s', ['15']),
			$this->l->t('- Use a poll to collect the opinions of others or settle on a date'),
			$this->l->t('- Configure an expiration time for chat messages'),
			$this->l->t('- Start calls without notifying others in big conversations. You can send individual call notifications once the call has started.'),
			$this->l->t('- Send chat messages without notifying the recipients in case it is not urgent'),
			$this->l->t('## New in Talk %s', ['16']),
			$this->l->t('- Emojis can now be autocompleted by typing a ":"'),
			$this->l->t('- Link various items using the new smart-picker by typing a "/"'),
			$this->l->t('- Moderators can now create breakout rooms (requires the High-performance backend)'),
			$this->l->t('- Calls can now be recorded (requires the High-performance backend)'),
			$this->l->t('## New in Talk %s', ['17']) . "\n"
			. $this->l->t('- Conversations can now have an avatar or emoji as icon') . "\n"
			. $this->l->t('- Virtual backgrounds are now available in addition to the blurred background in video calls') . "\n"
			. $this->l->t('- Reactions are now available during calls') . "\n"
			. $this->l->t('- Typing indicators show which users are currently typing a message') . "\n"
			. $this->l->t('- Groups can now be mentioned in chats') . "\n"
			. $this->l->t('- Call recordings are automatically transcribed if a transcription provider app is registered') . "\n"
			. $this->l->t('- Chat messages can be translated if a translation provider app is registered'),
			$this->l->t('## New in Talk %s', ['17.1']) . "\n"
			. $this->l->t('- **Markdown** can now be used in _chat_ messages') . "\n"
			. $this->l->t('- Webhooks are now available to implement bots. See the documentation for more information https://nextcloud-talk.readthedocs.io/en/latest/bot-list/') . "\n"
			. $this->l->t('- Set a reminder on a chat message to be notified later again'),
			$this->l->t('## New in Talk %s', ['18']) . "\n"
			. $this->l->t('- Use the **Note to self** conversation to take notes and share information between your devices') . "\n"
			. $this->l->t('- Captions allow to send a message with a file at the same time') . "\n"
			. $this->l->t('- Video of the speaker is now visible while sharing the screen and call reactions are animated'),
			$this->l->t('## New in Talk %s', ['19']) . "\n"
			. $this->l->t('- Messages can now be edited by logged-in authors and moderators for 6 hours') . "\n"
			. $this->l->t('- Unsent message drafts are now saved in your browser') . "\n"
			. $this->l->t('- Text chatting can now be done in a federated way with other Talk servers'),
			$this->l->t('## New in Talk %s', ['20']) . "\n"
			. $this->l->t('- Moderators can now ban accounts and guests to prevent them from rejoining a conversation') . "\n"
			. $this->l->t('- Upcoming calls from linked calendar events and out-of-office replacements are now shown in conversations') . "\n"
			. $this->l->t('- Calls can now be done in a federated way with other Talk servers (requires the High-performance backend)'),
			$this->l->t('## New in Talk %s', ['20.1']) . "\n"
			. $this->l->t('- Introducing the Nextcloud Talk desktop client for Windows, macOS and Linux: %s', ['https://nextcloud.com/talk-desktop-install']) . "\n"
			. $this->l->t('- Summarize call recordings and unread messages in chats with the Nextcloud Assistant') . "\n"
			. $this->l->t('- Improved meetings with recognizing guests invited via their email address, import of participant lists, drafts for polls and downloading call participant lists') . "\n"
			. $this->l->t('- Archive conversations to stay focused'),
			$this->l->t('## New in Talk %s', ['21']) . "\n"
			. $this->l->t('- Schedule a meeting into your calendar from within a conversation') . "\n"
			. $this->l->t('- Search for messages of the current conversation directly in the right sidebar') . "\n"
			. $this->l->t('- See more conversations on a first glance with the new compact list (enable in the Talk settings)'),
			$this->l->t('## New in Talk %s', ['21.1']) . "\n"
			. $this->l->t('- Meeting conversations now sync the title and description from the calendar and are hidden with a search filter until they are close to the start') . "\n"
			. $this->l->t('- Mark conversations as sensitive in the notification settings, to hide the message content from the conversation list and notifications') . "\n"
			. $this->l->t('- To receive push notifications during "Do not disturb", mark conversations as important') . "\n"
			. $this->l->t('- Add other participants to a one-to-one call to create a new group call on the fly') . "\n",
			$this->l->t('## New in Talk %s', ['22']) . "\n"
			. $this->l->t('- Use threads to keep your chat and discussions organized') . "\n"
			. $this->l->t('- Live transcriptions now available during the call (requires the live-transcription ExApp and the High-performance backend)') . "\n",
		];
	}
}
