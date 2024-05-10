<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2018, Joas Schilling <coding@schilljs.com>
 *
 * @author Joas Schilling <coding@schilljs.com>
 * @author Kate Döen <kate.doeen@nextcloud.com>
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
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Capabilities\IPublicCapability;
use OCP\Comments\ICommentsManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use OCP\Translation\ITranslationManager;
use OCP\Util;

/**
 * @psalm-import-type TalkCapabilities from ResponseDefinitions
 */
class Capabilities implements IPublicCapability {
	public const FEATURES = [
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
		'breakout-rooms-v1',
		'recording-v1',
		'avatar',
		'chat-get-context',
		'single-conversation-status',
		'chat-keep-notifications',
		'typing-privacy',
		'remind-me-later',
		'bots-v1',
		'markdown-messages',
		'media-caption',
		'session-state',
		'note-to-self',
		'recording-consent',
		'sip-support-dialout',
		'delete-messages-unlimited',
		'edit-messages',
		'silent-send-state',
		'chat-read-last',
		'federation-v1',
	];

	public const LOCAL_FEATURES = [
		'favorites',
		'chat-read-status',
		'listable-rooms',
		'phonebook-search',
		'temp-user-avatar-api',
		'unified-search',
		'avatar',
		'remind-me-later',
		'note-to-self',
	];

	public const LOCAL_CONFIGS = [
		'attachments' => [
			'allowed',
			'folder',
		],
		'call' => [
			'predefined-backgrounds',
			'can-upload-background',
		],
		'chat' => [
			'read-privacy',
			'has-translation-providers',
			'typing-privacy',
		],
		'conversations' => [
			'can-create',
		],
		'federation' => [
			'enabled',
			'incoming-enabled',
			'outgoing-enabled',
			'only-trusted-servers',
		],
		'previews' => [
			'max-gif-size',
		],
		'signaling' => [
			'session-ping-limit',
			'hello-v2-token-key',
		],
	];

	protected ICache $talkCache;

	public function __construct(
		protected IConfig $serverConfig,
		protected Config $talkConfig,
		protected IAppConfig $appConfig,
		protected ICommentsManager $commentsManager,
		protected IUserSession $userSession,
		protected IAppManager $appManager,
		protected ITranslationManager $translationManager,
		ICacheFactory $cacheFactory,
	) {
		$this->talkCache = $cacheFactory->createLocal('talk::');
	}

	/**
	 * @return array{
	 *      spreed: TalkCapabilities,
	 * }|array<empty>
	 */
	public function getCapabilities(): array {
		$user = $this->userSession->getUser();
		if ($user instanceof IUser && $this->talkConfig->isDisabledForUser($user)) {
			return [];
		}

		$capabilities = [
			'features' => self::FEATURES,
			'features-local' => self::LOCAL_FEATURES,
			'config' => [
				'attachments' => [
					'allowed' => $user instanceof IUser,
					// 'folder' => string,
				],
				'call' => [
					'enabled' => ((int) $this->serverConfig->getAppValue('spreed', 'start_calls', (string) Room::START_CALL_EVERYONE)) !== Room::START_CALL_NOONE,
					'breakout-rooms' => $this->talkConfig->isBreakoutRoomsEnabled(),
					'recording' => $this->talkConfig->isRecordingEnabled(),
					'recording-consent' => $this->talkConfig->recordingConsentRequired(),
					'supported-reactions' => ['❤️', '🎉', '👏', '👍', '👎', '😂', '🤩', '🤔', '😲', '😥'],
					// 'predefined-backgrounds' => list<string>,
					'can-upload-background' => false,
					'sip-enabled' => $this->talkConfig->isSIPConfigured(),
					'sip-dialout-enabled' => $this->talkConfig->isSIPDialOutEnabled(),
					'can-enable-sip' => false,
				],
				'chat' => [
					'max-length' => ChatManager::MAX_CHAT_LENGTH,
					'read-privacy' => Participant::PRIVACY_PUBLIC,
					'has-translation-providers' => $this->translationManager->hasProviders(),
					'typing-privacy' => Participant::PRIVACY_PUBLIC,
				],
				'conversations' => [
					'can-create' => $user instanceof IUser && !$this->talkConfig->isNotAllowedToCreateConversations($user)
				],
				'federation' => [
					'enabled' => false,
					'incoming-enabled' => false,
					'outgoing-enabled' => false,
					'only-trusted-servers' => true,
				],
				'previews' => [
					'max-gif-size' => (int)$this->serverConfig->getAppValue('spreed', 'max-gif-size', '3145728'),
				],
				'signaling' => [
					'session-ping-limit' => max(0, (int)$this->serverConfig->getAppValue('spreed', 'session-ping-limit', '200')),
					// 'hello-v2-token-key' => string,
				],
			],
			'config-local' => self::LOCAL_CONFIGS,
			'version' => $this->appManager->getAppVersion('spreed'),
		];


		if ($this->serverConfig->getAppValue('core', 'backgroundjobs_mode', 'ajax') === 'cron') {
			$capabilities['features'][] = 'message-expiration';
		}

		if ($this->commentsManager->supportReactions()) {
			$capabilities['features'][] = 'reactions';
		}

		if ($user instanceof IUser) {
			if ($this->talkConfig->isFederationEnabled() && $this->talkConfig->isFederationEnabledForUserId($user)) {
				$capabilities['config']['federation'] = [
					'enabled' => true,
					'incoming-enabled' => $this->appConfig->getAppValueBool('federation_incoming_enabled', true),
					'outgoing-enabled' => $this->appConfig->getAppValueBool('federation_outgoing_enabled', true),
					'only-trusted-servers' => $this->appConfig->getAppValueBool('federation_only_trusted_servers'),
				];
			}

			$capabilities['config']['attachments']['folder'] = $this->talkConfig->getAttachmentFolder($user->getUID());
			$capabilities['config']['chat']['read-privacy'] = $this->talkConfig->getUserReadPrivacy($user->getUID());
			$capabilities['config']['chat']['typing-privacy'] = $this->talkConfig->getUserTypingPrivacy($user->getUID());
		}

		$pubKey = $this->talkConfig->getSignalingTokenPublicKey();
		if ($pubKey) {
			$capabilities['config']['signaling']['hello-v2-token-key'] = $pubKey;
		}

		if ($this->serverConfig->getAppValue('spreed', 'has_reference_id', 'no') === 'yes') {
			$capabilities['features'][] = 'chat-reference-id';
		}

		/** @var ?string[] $predefinedBackgrounds */
		$predefinedBackgrounds = null;
		$cachedPredefinedBackgrounds = $this->talkCache->get('predefined_backgrounds');
		if ($cachedPredefinedBackgrounds !== null) {
			// Try using cached value
			/** @var string[]|null $predefinedBackgrounds */
			$predefinedBackgrounds = json_decode($cachedPredefinedBackgrounds, true);
		}

		if (!is_array($predefinedBackgrounds)) {
			// Cache was empty or invalid, regenerate
			/** @var string[] $predefinedBackgrounds */
			$predefinedBackgrounds = [];
			if (file_exists(__DIR__ . '/../img/backgrounds')) {
				$directoryIterator = new \DirectoryIterator(__DIR__ . '/../img/backgrounds');
				foreach ($directoryIterator as $file) {
					if (!$file->isFile()) {
						continue;
					}
					if ($file->isDot()) {
						continue;
					}
					if ($file->getFilename() === 'COPYING') {
						continue;
					}
					$predefinedBackgrounds[] = $file->getFilename();
				}
				sort($predefinedBackgrounds);
			}

			$this->talkCache->set('predefined_backgrounds', json_encode($predefinedBackgrounds), 300);
		}

		$capabilities['config']['call']['predefined-backgrounds'] = $predefinedBackgrounds;
		if ($user instanceof IUser) {
			$quota = $user->getQuota();
			if ($quota !== 'none') {
				$quota = Util::computerFileSize($quota);
			}
			$capabilities['config']['call']['can-upload-background'] = $quota === 'none' || $quota > 0;
			$capabilities['config']['call']['can-enable-sip'] = $this->talkConfig->canUserEnableSIP($user);
		}

		return [
			'spreed' => $capabilities,
		];
	}
}
