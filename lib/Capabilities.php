<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Service\LiveTranscriptionService;
use OCP\App\IAppManager;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Capabilities\IPublicCapability;
use OCP\Comments\ICommentsManager;
use OCP\ICache;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IUser;
use OCP\IUserSession;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\TaskTypes\TextToTextSummary;
use OCP\TaskProcessing\TaskTypes\TextToTextTranslate;
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
		'federation-v2',
		'ban-v1',
		'chat-reference-id',
		'mention-permissions',
		'edit-messages-note-to-self',
		'archived-conversations-v2',
		'talk-polls-drafts',
		'download-call-participants',
		'email-csv-import',
		'conversation-creation-password',
		'call-notification-state-api',
		'schedule-meeting',
		'edit-draft-poll',
		'conversation-creation-all',
		'important-conversations',
		'unbind-conversation',
		'sip-direct-dialin',
		'dashboard-event-rooms',
		'mutual-calendar-events',
		'upcoming-reminders',
		'sensitive-conversations',
		'threads',
	];

	public const CONDITIONAL_FEATURES = [
		'message-expiration',
		'reactions',
		'chat-summary-api',
		'call-end-to-end-encryption',
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
		'archived-conversations-v2',
		'chat-summary-api',
		'call-notification-state-api',
		'schedule-meeting',
		'conversation-creation-all',
		'important-conversations',
		'sip-direct-dialin',
		'dashboard-event-rooms',
		'mutual-calendar-events',
		'upcoming-reminders',
		'sensitive-conversations',
	];

	public const LOCAL_CONFIGS = [
		'attachments' => [
			'allowed',
			'folder',
		],
		'call' => [
			'predefined-backgrounds',
			'predefined-backgrounds-v2',
			'can-upload-background',
			'start-without-media',
			'blur-virtual-background',
		],
		'chat' => [
			'read-privacy',
			'has-translation-providers',
			'has-translation-task-providers',
			'typing-privacy',
			'summary-threshold',
		],
		'conversations' => [
			'can-create',
			'list-style',
			'description-length',
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
		'experiments' => [
			'enabled',
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
		protected ITaskProcessingManager $taskProcessingManager,
		protected LiveTranscriptionService $liveTranscriptionService,
		ICacheFactory $cacheFactory,
	) {
		$this->talkCache = $cacheFactory->createLocal('talk::');
	}

	/**
	 * @return array{
	 *      spreed?: TalkCapabilities,
	 * }
	 */
	#[\Override]
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
					'enabled' => ((int)$this->serverConfig->getAppValue('spreed', 'start_calls', (string)Room::START_CALL_EVERYONE)) !== Room::START_CALL_NOONE,
					'breakout-rooms' => $this->talkConfig->isBreakoutRoomsEnabled(),
					'recording' => $this->talkConfig->isRecordingEnabled(),
					'recording-consent' => $this->talkConfig->recordingConsentRequired(),
					'supported-reactions' => ['❤️', '🎉', '👏', '👋', '👍', '👎', '🔥', '😂', '🤩', '🤔', '😲', '😥'],
					// 'predefined-backgrounds' => list<string>,
					// 'predefined-backgrounds-v2' => list<string>,
					'can-upload-background' => false,
					'sip-enabled' => $this->talkConfig->isSIPConfigured(),
					'sip-dialout-enabled' => $this->talkConfig->isSIPDialOutEnabled(),
					'can-enable-sip' => false,
					'start-without-media' => $this->talkConfig->getCallsStartWithoutMedia($user?->getUID()),
					'max-duration' => $this->appConfig->getAppValueInt('max_call_duration'),
					'blur-virtual-background' => $this->talkConfig->getBlurVirtualBackground($user?->getUID()),
					'end-to-end-encryption' => $this->talkConfig->isCallEndToEndEncryptionEnabled(),
					'live-transcription' => $this->talkConfig->getSignalingMode() === Config::SIGNALING_EXTERNAL
						&& $this->liveTranscriptionService->isLiveTranscriptionAppEnabled(),
				],
				'chat' => [
					'max-length' => ChatManager::MAX_CHAT_LENGTH,
					'read-privacy' => Participant::PRIVACY_PUBLIC,
					'has-translation-providers' => $this->translationManager->hasProviders(),
					'has-translation-task-providers' => false,
					'typing-privacy' => Participant::PRIVACY_PUBLIC,
					'summary-threshold' => max(1, $this->appConfig->getAppValueInt('summary_threshold', 100)),
				],
				'conversations' => [
					'can-create' => $user instanceof IUser && !$this->talkConfig->isNotAllowedToCreateConversations($user),
					'force-passwords' => $this->talkConfig->isPasswordEnforced(),
					'list-style' => $this->talkConfig->getConversationsListStyle($user?->getUID()),
					'description-length' => Room::DESCRIPTION_MAXIMUM_LENGTH,
					'retention-event' => max(0, $this->appConfig->getAppValueInt('retention_event_rooms', 28)),
					'retention-phone' => max(0, $this->appConfig->getAppValueInt('retention_phone_rooms', 7)),
					'retention-instant-meetings' => max(0, $this->appConfig->getAppValueInt('retention_instant_meetings', 1)),
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
				'experiments' => [
					'enabled' => max(0, $this->appConfig->getAppValueInt($user instanceof IUser ? 'experiments_users' : 'experiments_guests')),
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
			$capabilities['config']['call']['blur-virtual-background'] = $this->talkConfig->getBlurVirtualBackground($user->getUID());
		}

		$pubKey = $this->talkConfig->getSignalingTokenPublicKey();
		if ($pubKey) {
			$capabilities['config']['signaling']['hello-v2-token-key'] = $pubKey;
		}

		$includeBrandedBackgrounds = $user instanceof IUser || $this->appConfig->getAppValueBool('backgrounds_branded_for_guests');
		$includeDefaultBackgrounds = !$user instanceof IUser || $this->appConfig->getAppValueBool('backgrounds_default_for_users', true);

		$predefinedBackgrounds = [];
		$defaultBackgrounds = $this->getBackgroundsFromDirectory(__DIR__ . '/../img/backgrounds', '_default');
		if ($includeBrandedBackgrounds) {
			$predefinedBackgrounds = $this->getBackgroundsFromDirectory(\OC::$SERVERROOT . '/themes/talk-backgrounds', '_branded');
			$predefinedBackgrounds = array_map(static fn ($fileName) => '/themes/talk-backgrounds/' . $fileName, $predefinedBackgrounds);
		}

		if ($includeDefaultBackgrounds) {
			$spreedWebPath = $this->appManager->getAppWebPath('spreed');
			$prefixedDefaultBackgrounds = array_map(static fn ($fileName) => $spreedWebPath . '/img/backgrounds/' . $fileName, $defaultBackgrounds);
			$predefinedBackgrounds = array_merge($predefinedBackgrounds, $prefixedDefaultBackgrounds);
		}

		$capabilities['config']['call']['predefined-backgrounds'] = $defaultBackgrounds;
		$capabilities['config']['call']['predefined-backgrounds-v2'] = array_values($predefinedBackgrounds);

		if ($user instanceof IUser) {
			$userAllowedToUpload = $this->appConfig->getAppValueBool('backgrounds_upload_users', true);
			if ($userAllowedToUpload) {
				$quota = $user->getQuota();
				if ($quota !== 'none') {
					$quota = Util::computerFileSize($quota);
				}
				$capabilities['config']['call']['can-upload-background'] = $quota === 'none' || $quota > 0;
			}
			$capabilities['config']['call']['can-enable-sip'] = $this->talkConfig->canUserEnableSIP($user);
		}

		$supportedTaskTypeIds = $this->taskProcessingManager->getAvailableTaskTypeIds();
		if (in_array(TextToTextSummary::ID, $supportedTaskTypeIds, true)) {
			$capabilities['features'][] = 'chat-summary-api';
		}
		if (in_array(TextToTextTranslate::ID, $supportedTaskTypeIds, true)) {
			$capabilities['config']['chat']['has-translation-task-providers'] = true;
		}

		if ($this->talkConfig->getSignalingMode() === Config::SIGNALING_EXTERNAL) {
			$capabilities['features'][] = 'call-end-to-end-encryption';
		}

		return [
			'spreed' => $capabilities,
		];
	}

	/**
	 * @return list<string>
	 */
	protected function getBackgroundsFromDirectory(string $directory, string $cacheSuffix): array {
		$cacheKey = 'predefined_backgrounds' . $cacheSuffix;

		/** @var ?list<string> $predefinedBackgrounds */
		$predefinedBackgrounds = null;
		$cachedPredefinedBackgrounds = $this->talkCache->get($cacheKey);
		if ($cachedPredefinedBackgrounds !== null) {
			// Try using cached value
			/** @var list<string>|null $predefinedBackgrounds */
			$predefinedBackgrounds = json_decode($cachedPredefinedBackgrounds, true);
		}

		if (!is_array($predefinedBackgrounds)) {
			if (file_exists($directory) && is_dir($directory)) {
				$directoryIterator = new \DirectoryIterator($directory);
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

			$this->talkCache->set($cacheKey, json_encode($predefinedBackgrounds), 300);
		}

		return $predefinedBackgrounds ?? [];
	}
}
