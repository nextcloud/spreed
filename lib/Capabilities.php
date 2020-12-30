<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018, Joas Schilling <coding@schilljs.com>
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

namespace OCA\Talk;

use OCA\Talk\Chat\ChatManager;
use OCP\Capabilities\IPublicCapability;
use OCP\Comments\ICommentsManager;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use OCP\App\IAppManager;

class Capabilities implements IPublicCapability {
	protected IConfig $serverConfig;
	protected Config $talkConfig;
	protected ICommentsManager $commentsManager;
	protected IUserSession $userSession;
	private IAppManager $appManager;

	public function __construct(IConfig $serverConfig,
								Config $talkConfig,
								ICommentsManager $commentsManager,
								IUserSession $userSession,
								IAppManager $appManager) {
		$this->serverConfig = $serverConfig;
		$this->talkConfig = $talkConfig;
		$this->commentsManager = $commentsManager;
		$this->userSession = $userSession;
		$this->appManager = $appManager;
	}

	public function getCapabilities(): array {
		$user = $this->userSession->getUser();
		if ($user instanceof IUser && $this->talkConfig->isDisabledForUser($user)) {
			return [];
		}

		$capabilities = [
			'features' => [
				'audio',
				'video',
				'chat-v2',
				'conversation-v4',
				'guest-signaling',
				'empty-group-room',
				'guest-display-names',
				'multi-room-users',
				'favorites',
				'last-room-activity',
				'no-ping',
				'system-messages',
				'delete-messages',
				'mention-flag',
				'in-call-flags',
				'conversation-call-flags',
				'notification-levels',
				'invite-groups-and-mails',
				'locked-one-to-one-rooms',
				'read-only-rooms',
				'listable-rooms',
				'chat-read-marker',
				'chat-unread',
				'webinary-lobby',
				'start-call-flag',
				'chat-replies',
				'circles-support',
				'force-mute',
				'sip-support',
				'sip-support-nopin',
				'chat-read-status',
				'phonebook-search',
				'raise-hand',
				'room-description',
				'rich-object-sharing',
				'temp-user-avatar-api',
				'geo-location-sharing',
				'voice-message-sharing',
				'signaling-v3',
				'publishing-permissions',
				'clear-history',
				'direct-mention-flag',
				'notification-calls',
				'conversation-permissions',
				'rich-object-list-media',
				'rich-object-delete',
				'unified-search',
				'chat-permission',
				'silent-send',
				'silent-call',
				'send-call-notification',
				'talk-polls',
				'room-avatar',
			],
			'config' => [
				'attachments' => [
					'allowed' => $user instanceof IUser,
				],
				'call' => [
					'enabled' => ((int) $this->serverConfig->getAppValue('spreed', 'start_calls', Room::START_CALL_EVERYONE)) !== Room::START_CALL_NOONE,
				],
				'chat' => [
					'max-length' => ChatManager::MAX_CHAT_LENGTH,
					'read-privacy' => Participant::PRIVACY_PUBLIC,
				],
				'conversations' => [],
				'previews' => [
					'max-gif-size' => (int)$this->serverConfig->getAppValue('spreed', 'max-gif-size', '3145728'),
				],
				'signaling' => [
					'session-ping-limit' => max(0, (int)$this->serverConfig->getAppValue('spreed', 'session-ping-limit', '200')),
				],
			],
			'version' => $this->appManager->getAppVersion('spreed'),
		];

		if ($this->serverConfig->getAppValue('core', 'backgroundjobs_mode', 'ajax') === 'cron') {
			$capabilities['features'][] = 'message-expiration';
		}

		if ($this->commentsManager->supportReactions()) {
			$capabilities['features'][] = 'reactions';
		}

		if ($user instanceof IUser) {
			$capabilities['config']['attachments']['folder'] = $this->talkConfig->getAttachmentFolder($user->getUID());
			$capabilities['config']['chat']['read-privacy'] = $this->talkConfig->getUserReadPrivacy($user->getUID());
		}

		$capabilities['config']['conversations']['can-create'] = $user instanceof IUser && !$this->talkConfig->isNotAllowedToCreateConversations($user);

		$pubKey = $this->talkConfig->getSignalingTokenPublicKey();
		if ($pubKey) {
			$capabilities['config']['signaling']['hello-v2-token-key'] = $pubKey;
		}

		if ($this->serverConfig->getAppValue('spreed', 'has_reference_id', 'no') === 'yes') {
			$capabilities['features'][] = 'chat-reference-id';
		}

		return [
			'spreed' => $capabilities,
		];
	}
}
