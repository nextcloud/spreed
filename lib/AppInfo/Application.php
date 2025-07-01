<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\AppInfo;

use OCA\Circles\Events\AddingCircleMemberEvent;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\CircleEditedEvent;
use OCA\Circles\Events\EditingCircleEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Files\Event\LoadSidebar;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Talk\Activity\Listener as ActivityListener;
use OCA\Talk\Capabilities;
use OCA\Talk\Chat\Changelog\Listener as ChangelogListener;
use OCA\Talk\Chat\Listener as ChatListener;
use OCA\Talk\Chat\Parser\Changelog;
use OCA\Talk\Chat\Parser\ReactionParser;
use OCA\Talk\Chat\Parser\SystemMessage;
use OCA\Talk\Chat\Parser\UserMention;
use OCA\Talk\Chat\SystemMessage\Listener as SystemMessageListener;
use OCA\Talk\Collaboration\Collaborators\Listener as CollaboratorsListener;
use OCA\Talk\Collaboration\Reference\ReferenceInvalidationListener;
use OCA\Talk\Collaboration\Reference\TalkReferenceProvider;
use OCA\Talk\Collaboration\Resources\ConversationProvider;
use OCA\Talk\Collaboration\Resources\Listener as ResourceListener;
use OCA\Talk\Config;
use OCA\Talk\Dashboard\TalkWidget;
use OCA\Talk\Deck\DeckPluginLoader;
use OCA\Talk\Events\AttendeeRemovedEvent;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\BeforeAttendeeRemovedEvent;
use OCA\Talk\Events\BeforeAttendeesAddedEvent;
use OCA\Talk\Events\BeforeCallStartedEvent;
use OCA\Talk\Events\BeforeDuplicateShareSentEvent;
use OCA\Talk\Events\BeforeGuestJoinedRoomEvent;
use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Events\BeforeRoomDeletedEvent;
use OCA\Talk\Events\BeforeRoomsFetchEvent;
use OCA\Talk\Events\BeforeRoomSyncedEvent;
use OCA\Talk\Events\BeforeSessionLeftRoomEvent;
use OCA\Talk\Events\BeforeUserJoinedRoomEvent;
use OCA\Talk\Events\BotDisabledEvent;
use OCA\Talk\Events\BotEnabledEvent;
use OCA\Talk\Events\BotInstallEvent;
use OCA\Talk\Events\BotUninstallEvent;
use OCA\Talk\Events\CallEndedEvent;
use OCA\Talk\Events\CallEndedForEveryoneEvent;
use OCA\Talk\Events\CallNotificationSendEvent;
use OCA\Talk\Events\CallStartedEvent;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\EmailInvitationSentEvent;
use OCA\Talk\Events\GuestJoinedRoomEvent;
use OCA\Talk\Events\GuestsCleanedUpEvent;
use OCA\Talk\Events\LobbyModifiedEvent;
use OCA\Talk\Events\MessageParseEvent;
use OCA\Talk\Events\ParticipantModifiedEvent;
use OCA\Talk\Events\ReactionAddedEvent;
use OCA\Talk\Events\ReactionRemovedEvent;
use OCA\Talk\Events\RoomCreatedEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Events\RoomExtendedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Events\RoomSyncedEvent;
use OCA\Talk\Events\SessionLeftRoomEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Events\SystemMessagesMultipleSentEvent;
use OCA\Talk\Events\UserJoinedRoomEvent;
use OCA\Talk\Federation\CloudFederationProviderTalk;
use OCA\Talk\Federation\Proxy\TalkV1\Listener\ResourceTypeRegisterListener;
use OCA\Talk\Federation\Proxy\TalkV1\Notifier\BeforeRoomDeletedListener as TalkV1BeforeRoomDeletedListener;
use OCA\Talk\Federation\Proxy\TalkV1\Notifier\CancelRetryOCMListener as TalkV1CancelRetryOCMListener;
use OCA\Talk\Federation\Proxy\TalkV1\Notifier\MessageSentListener as TalkV1MessageSentListener;
use OCA\Talk\Federation\Proxy\TalkV1\Notifier\ParticipantModifiedListener as TalkV1ParticipantModifiedListener;
use OCA\Talk\Federation\Proxy\TalkV1\Notifier\RoomModifiedListener as TalkV1RoomModifiedListener;
use OCA\Talk\Files\Listener as FilesListener;
use OCA\Talk\Files\TemplateLoader as FilesTemplateLoader;
use OCA\Talk\Flow\RegisterOperationsListener;
use OCA\Talk\Listener\AddMissingIndicesListener;
use OCA\Talk\Listener\BeforeUserLoggedOutListener;
use OCA\Talk\Listener\BotListener;
use OCA\Talk\Listener\CalDavEventListener;
use OCA\Talk\Listener\CircleDeletedListener;
use OCA\Talk\Listener\CircleEditedListener;
use OCA\Talk\Listener\CircleMembershipListener;
use OCA\Talk\Listener\CSPListener;
use OCA\Talk\Listener\DisplayNameListener;
use OCA\Talk\Listener\FeaturePolicyListener;
use OCA\Talk\Listener\GroupDeletedListener;
use OCA\Talk\Listener\GroupMembershipListener;
use OCA\Talk\Listener\NoteToSelfListener;
use OCA\Talk\Listener\RestrictStartingCalls as RestrictStartingCallsListener;
use OCA\Talk\Listener\SampleConversationsListener;
use OCA\Talk\Listener\ThreadListener;
use OCA\Talk\Listener\UserDeletedListener;
use OCA\Talk\Maps\MapsPluginLoader;
use OCA\Talk\Middleware\CanUseTalkMiddleware;
use OCA\Talk\Middleware\InjectionMiddleware;
use OCA\Talk\Middleware\ParameterOutOfRangeMiddleware;
use OCA\Talk\Notification\Listener as NotificationListener;
use OCA\Talk\Notification\Notifier;
use OCA\Talk\OCP\TalkBackend;
use OCA\Talk\Profile\TalkAction;
use OCA\Talk\PublicShare\TemplateLoader as PublicShareTemplateLoader;
use OCA\Talk\PublicShareAuth\Listener as PublicShareAuthListener;
use OCA\Talk\PublicShareAuth\TemplateLoader as PublicShareAuthTemplateLoader;
use OCA\Talk\Recording\Listener as RecordingListener;
use OCA\Talk\Search\ConversationSearch;
use OCA\Talk\Search\CurrentMessageSearch;
use OCA\Talk\Search\MessageSearch;
use OCA\Talk\Search\UnifiedSearchCSSLoader;
use OCA\Talk\Search\UnifiedSearchFilterPlugin;
use OCA\Talk\Settings\BeforePreferenceSetEventListener;
use OCA\Talk\Settings\Personal;
use OCA\Talk\SetupCheck\BackgroundBlurLoading;
use OCA\Talk\SetupCheck\Configuration;
use OCA\Talk\SetupCheck\FederationLockCache;
use OCA\Talk\SetupCheck\HighPerformanceBackend;
use OCA\Talk\SetupCheck\NotifyPush;
use OCA\Talk\SetupCheck\RecordingBackend;
use OCA\Talk\SetupCheck\SIPConfiguration;
use OCA\Talk\Share\Listener as ShareListener;
use OCA\Talk\Signaling\Listener as SignalingListener;
use OCA\Talk\Status\Listener as StatusListener;
use OCA\Talk\Team\TalkTeamResourceProvider;
use OCP\App\IAppManager;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Calendar\Events\CalendarObjectCreatedEvent;
use OCP\Calendar\Events\CalendarObjectUpdatedEvent;
use OCP\Collaboration\AutoComplete\AutoCompleteFilterEvent;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;
use OCP\Config\BeforePreferenceSetEvent;
use OCP\DB\Events\AddMissingIndicesEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Group\Events\GroupChangedEvent;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IConfig;
use OCP\INavigationManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use OCP\OCM\Events\ResourceTypeRegisterEvent;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\Security\FeaturePolicy\AddFeaturePolicyEvent;
use OCP\Server;
use OCP\Settings\IManager;
use OCP\Share\Events\BeforeShareCreatedEvent;
use OCP\Share\Events\ShareCreatedEvent;
use OCP\Share\Events\VerifyMountPointEvent;
use OCP\TaskProcessing\Events\TaskFailedEvent;
use OCP\TaskProcessing\Events\TaskSuccessfulEvent;
use OCP\User\Events\BeforeUserLoggedOutEvent;
use OCP\User\Events\UserChangedEvent;
use OCP\User\Events\UserDeletedEvent;
use OCP\Util;
use OCP\WorkflowEngine\Events\RegisterOperationsEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'spreed';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	#[\Override]
	public function register(IRegistrationContext $context): void {
		$context->registerMiddleWare(CanUseTalkMiddleware::class);
		$context->registerMiddleWare(InjectionMiddleware::class);
		$context->registerMiddleWare(ParameterOutOfRangeMiddleware::class);
		$context->registerCapability(Capabilities::class);

		// Listeners to load the UI and integrate it into other apps
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, CSPListener::class);
		$context->registerEventListener(AddFeaturePolicyEvent::class, FeaturePolicyListener::class);
		$context->registerEventListener(\OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent::class, UnifiedSearchCSSLoader::class);
		$context->registerEventListener(\OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent::class, DeckPluginLoader::class);
		$context->registerEventListener(\OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent::class, MapsPluginLoader::class);
		$context->registerEventListener(\OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent::class, UnifiedSearchFilterPlugin::class);
		$context->registerEventListener(RegisterOperationsEvent::class, RegisterOperationsListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, PublicShareTemplateLoader::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, PublicShareAuthTemplateLoader::class);
		$context->registerEventListener(LoadSidebar::class, FilesTemplateLoader::class);
		$context->registerEventListener(BeforePreferenceSetEvent::class, BeforePreferenceSetEventListener::class);

		// Activity listeners
		$context->registerEventListener(AttendeesAddedEvent::class, ActivityListener::class);
		$context->registerEventListener(CallEndedEvent::class, ActivityListener::class);
		$context->registerEventListener(CallEndedForEveryoneEvent::class, ActivityListener::class);

		// Bot listeners
		$context->registerEventListener(BotDisabledEvent::class, BotListener::class);
		$context->registerEventListener(BotEnabledEvent::class, BotListener::class);
		$context->registerEventListener(BotInstallEvent::class, BotListener::class);
		$context->registerEventListener(BotUninstallEvent::class, BotListener::class);
		$context->registerEventListener(ChatMessageSentEvent::class, BotListener::class);
		$context->registerEventListener(ReactionAddedEvent::class, BotListener::class);
		$context->registerEventListener(ReactionRemovedEvent::class, BotListener::class);
		$context->registerEventListener(SystemMessageSentEvent::class, BotListener::class);

		// Chat listeners
		$context->registerEventListener(BeforeRoomsFetchEvent::class, ChangelogListener::class);
		$context->registerEventListener(RoomDeletedEvent::class, ChatListener::class);
		$context->registerEventListener(BeforeRoomsFetchEvent::class, NoteToSelfListener::class);
		$context->registerEventListener(BeforeRoomsFetchEvent::class, SampleConversationsListener::class);
		$context->registerEventListener(AttendeesAddedEvent::class, SystemMessageListener::class);
		$context->registerEventListener(AttendeeRemovedEvent::class, SystemMessageListener::class);
		$context->registerEventListener(AttendeesRemovedEvent::class, SystemMessageListener::class);
		$context->registerEventListener(BeforeDuplicateShareSentEvent::class, SystemMessageListener::class);
		$context->registerEventListener(BeforeParticipantModifiedEvent::class, SystemMessageListener::class);
		$context->registerEventListener(BeforeShareCreatedEvent::class, SystemMessageListener::class);
		$context->registerEventListener(LobbyModifiedEvent::class, SystemMessageListener::class);
		$context->registerEventListener(ParticipantModifiedEvent::class, SystemMessageListener::class, 100);
		$context->registerEventListener(RoomCreatedEvent::class, SystemMessageListener::class);
		$context->registerEventListener(RoomModifiedEvent::class, SystemMessageListener::class);
		$context->registerEventListener(ShareCreatedEvent::class, SystemMessageListener::class);

		// Chat parser
		$context->registerEventListener(MessageParseEvent::class, Changelog::class, -75);
		$context->registerEventListener(MessageParseEvent::class, ReactionParser::class);
		$context->registerEventListener(MessageParseEvent::class, SystemMessage::class);
		$context->registerEventListener(MessageParseEvent::class, SystemMessage::class, 9999);
		$context->registerEventListener(MessageParseEvent::class, UserMention::class, -100);

		// Calendar listeners
		$context->registerEventListener(CalendarObjectCreatedEvent::class, CalDavEventListener::class);
		$context->registerEventListener(CalendarObjectUpdatedEvent::class, CalDavEventListener::class);

		// Files integration listeners
		$context->registerEventListener(BeforeGuestJoinedRoomEvent::class, FilesListener::class);
		$context->registerEventListener(BeforeUserJoinedRoomEvent::class, FilesListener::class);

		// Collaborators / Auto complete listeners
		$context->registerEventListener(AutoCompleteFilterEvent::class, CollaboratorsListener::class);

		// Reference listeners
		$context->registerEventListener(AttendeesAddedEvent::class, ReferenceInvalidationListener::class);
		$context->registerEventListener(AttendeesRemovedEvent::class, ReferenceInvalidationListener::class);
		$context->registerEventListener(LobbyModifiedEvent::class, ReferenceInvalidationListener::class);
		$context->registerEventListener(RoomDeletedEvent::class, ReferenceInvalidationListener::class);
		$context->registerEventListener(RoomModifiedEvent::class, ReferenceInvalidationListener::class);

		// Resources listeners
		$context->registerEventListener(AttendeesAddedEvent::class, ResourceListener::class);
		$context->registerEventListener(AttendeesRemovedEvent::class, ResourceListener::class);
		$context->registerEventListener(EmailInvitationSentEvent::class, ResourceListener::class);
		$context->registerEventListener(RoomDeletedEvent::class, ResourceListener::class);
		$context->registerEventListener(RoomModifiedEvent::class, ResourceListener::class);

		// Sharing listeners
		$context->registerEventListener(BeforeShareCreatedEvent::class, ShareListener::class, 1000);
		$context->registerEventListener(VerifyMountPointEvent::class, ShareListener::class, 1000);
		$context->registerEventListener(RoomDeletedEvent::class, ShareListener::class);

		// Group and Circles listeners
		$context->registerEventListener(GroupDeletedEvent::class, GroupDeletedListener::class);
		$context->registerEventListener(GroupChangedEvent::class, DisplayNameListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(UserChangedEvent::class, DisplayNameListener::class);
		$context->registerEventListener(UserAddedEvent::class, GroupMembershipListener::class);
		$context->registerEventListener(UserRemovedEvent::class, GroupMembershipListener::class);
		$context->registerEventListener(CircleDestroyedEvent::class, CircleDeletedListener::class);
		$context->registerEventListener(EditingCircleEvent::class, CircleEditedListener::class);
		$context->registerEventListener(CircleEditedEvent::class, CircleEditedListener::class);
		$context->registerEventListener(AddingCircleMemberEvent::class, CircleMembershipListener::class);
		$context->registerEventListener(RemovingCircleMemberEvent::class, CircleMembershipListener::class);

		// Notification listeners
		$context->registerEventListener(AttendeesAddedEvent::class, NotificationListener::class);
		$context->registerEventListener(BeforeCallStartedEvent::class, NotificationListener::class);
		$context->registerEventListener(CallStartedEvent::class, NotificationListener::class);
		$context->registerEventListener(CallNotificationSendEvent::class, NotificationListener::class);
		$context->registerEventListener(ParticipantModifiedEvent::class, NotificationListener::class);
		$context->registerEventListener(UserJoinedRoomEvent::class, NotificationListener::class);

		// Call listeners
		$context->registerEventListener(BeforeUserLoggedOutEvent::class, BeforeUserLoggedOutListener::class);
		$context->registerEventListener(BeforeParticipantModifiedEvent::class, RestrictStartingCallsListener::class, 1000);
		$context->registerEventListener(BeforeParticipantModifiedEvent::class, StatusListener::class);
		$context->registerEventListener(CallEndedForEveryoneEvent::class, StatusListener::class);

		// Recording listeners
		$context->registerEventListener(RoomDeletedEvent::class, RecordingListener::class);
		$context->registerEventListener(CallEndedEvent::class, RecordingListener::class);
		$context->registerEventListener(CallEndedForEveryoneEvent::class, RecordingListener::class);
		$context->registerEventListener(TaskSuccessfulEvent::class, RecordingListener::class);
		$context->registerEventListener(TaskFailedEvent::class, RecordingListener::class);

		// Federation listeners
		$context->registerEventListener(BeforeRoomDeletedEvent::class, TalkV1BeforeRoomDeletedListener::class);
		$context->registerEventListener(ParticipantModifiedEvent::class, TalkV1ParticipantModifiedListener::class);
		$context->registerEventListener(CallEndedEvent::class, TalkV1RoomModifiedListener::class);
		$context->registerEventListener(CallEndedForEveryoneEvent::class, TalkV1RoomModifiedListener::class);
		$context->registerEventListener(CallStartedEvent::class, TalkV1RoomModifiedListener::class);
		$context->registerEventListener(LobbyModifiedEvent::class, TalkV1RoomModifiedListener::class);
		$context->registerEventListener(RoomModifiedEvent::class, TalkV1RoomModifiedListener::class);
		$context->registerEventListener(ChatMessageSentEvent::class, TalkV1MessageSentListener::class);
		$context->registerEventListener(SystemMessageSentEvent::class, TalkV1MessageSentListener::class);
		$context->registerEventListener(SystemMessagesMultipleSentEvent::class, TalkV1MessageSentListener::class);
		$context->registerEventListener(AttendeeRemovedEvent::class, TalkV1CancelRetryOCMListener::class);
		$context->registerEventListener(ResourceTypeRegisterEvent::class, ResourceTypeRegisterListener::class);

		// Signaling listeners (External)
		$context->registerEventListener(AttendeesAddedEvent::class, SignalingListener::class);
		$context->registerEventListener(AttendeeRemovedEvent::class, SignalingListener::class);
		$context->registerEventListener(AttendeesRemovedEvent::class, SignalingListener::class);
		$context->registerEventListener(SessionLeftRoomEvent::class, SignalingListener::class);

		$context->registerEventListener(CallEndedForEveryoneEvent::class, SignalingListener::class);
		$context->registerEventListener(GuestsCleanedUpEvent::class, SignalingListener::class);
		$context->registerEventListener(LobbyModifiedEvent::class, SignalingListener::class);
		$context->registerEventListener(BeforeRoomSyncedEvent::class, SignalingListener::class);
		$context->registerEventListener(RoomSyncedEvent::class, SignalingListener::class);
		$context->registerEventListener(RoomExtendedEvent::class, SignalingListener::class);

		$context->registerEventListener(ChatMessageSentEvent::class, SignalingListener::class);
		$context->registerEventListener(SystemMessageSentEvent::class, SignalingListener::class);
		$context->registerEventListener(SystemMessagesMultipleSentEvent::class, SignalingListener::class);

		// Signaling listeners (Both)
		$context->registerEventListener(BeforeRoomDeletedEvent::class, SignalingListener::class);
		$context->registerEventListener(ParticipantModifiedEvent::class, SignalingListener::class, 50);
		$context->registerEventListener(RoomModifiedEvent::class, SignalingListener::class);

		// Signaling listeners (Internal)
		$context->registerEventListener(BeforeSessionLeftRoomEvent::class, SignalingListener::class);
		$context->registerEventListener(BeforeAttendeeRemovedEvent::class, SignalingListener::class);
		$context->registerEventListener(GuestJoinedRoomEvent::class, SignalingListener::class);
		$context->registerEventListener(UserJoinedRoomEvent::class, SignalingListener::class);

		// Threads listeners
		$context->registerEventListener(AttendeesRemovedEvent::class, ThreadListener::class);

		// Video verification
		$context->registerEventListener(BeforeUserJoinedRoomEvent::class, PublicShareAuthListener::class);
		$context->registerEventListener(BeforeGuestJoinedRoomEvent::class, PublicShareAuthListener::class);
		$context->registerEventListener(BeforeAttendeesAddedEvent::class, PublicShareAuthListener::class);
		$context->registerEventListener(AttendeeRemovedEvent::class, PublicShareAuthListener::class);
		$context->registerEventListener(SessionLeftRoomEvent::class, PublicShareAuthListener::class);
		$context->registerEventListener(GuestsCleanedUpEvent::class, PublicShareAuthListener::class);

		// Register other integrations of Talk
		$context->registerSearchProvider(ConversationSearch::class);
		$context->registerSearchProvider(CurrentMessageSearch::class);
		$context->registerSearchProvider(MessageSearch::class);

		// Fix database issues
		$context->registerEventListener(AddMissingIndicesEvent::class, AddMissingIndicesListener::class);

		$context->registerDashboardWidget(TalkWidget::class);

		$context->registerNotifierService(Notifier::class);

		$context->registerProfileLinkAction(TalkAction::class);

		$context->registerReferenceProvider(TalkReferenceProvider::class);

		$context->registerTalkBackend(TalkBackend::class);

		$context->registerTeamResourceProvider(TalkTeamResourceProvider::class);

		$context->registerSetupCheck(Configuration::class);
		$context->registerSetupCheck(HighPerformanceBackend::class);
		$context->registerSetupCheck(FederationLockCache::class);
		$context->registerSetupCheck(NotifyPush::class);
		$context->registerSetupCheck(RecordingBackend::class);
		$context->registerSetupCheck(SIPConfiguration::class);
		$context->registerSetupCheck(BackgroundBlurLoading::class);
	}

	#[\Override]
	public function boot(IBootContext $context): void {
		$context->injectFn([$this, 'registerCollaborationResourceProvider']);
		$context->injectFn([$this, 'registerClientLinks']);
		$context->injectFn([$this, 'registerNavigationLink']);
		$context->injectFn([$this, 'registerCloudFederationProviderManager']);
	}

	public function registerCollaborationResourceProvider(IProviderManager $resourceManager, IEventDispatcher $dispatcher): void {
		$resourceManager->registerResourceProvider(ConversationProvider::class);
		$dispatcher->addListener(LoadAdditionalScriptsEvent::class, static function (): void {
			Util::addScript(self::APP_ID, 'talk-collections');
		});
	}

	public function registerClientLinks(IAppManager $appManager, IManager $settingManager): void {
		if ($appManager->isEnabledForUser('firstrunwizard')) {
			$settingManager->registerSetting('personal', Personal::class);
		}
	}

	public function registerNavigationLink(INavigationManager $navigationManager): void {
		$navigationManager->add(static function () {
			$config = Server::get(Config::class);
			$userSession = Server::get(IUserSession::class);
			$urlGenerator = Server::get(IURLGenerator::class);
			$l = Server::get(IFactory::class)->get(self::APP_ID);
			$user = $userSession->getUser();
			return [
				'id' => self::APP_ID,
				'name' => $l->t('Talk'),
				'href' => $urlGenerator->linkToRouteAbsolute('spreed.Page.index'),
				'icon' => $urlGenerator->imagePath(self::APP_ID, 'app.svg'),
				'order' => -5,
				'type' => $user instanceof IUser && !$config->isDisabledForUser($user) ? 'link' : 'hidden',
			];
		});
	}

	public function registerCloudFederationProviderManager(
		IConfig $config,
		ICloudFederationProviderManager $manager,
	): void {
		if ($config->getAppValue('spreed', 'federation_enabled', 'no') !== 'yes') {
			return;
		}

		$manager->addCloudFederationProvider(
			'talk-room',
			'Talk Federation',
			static fn (): ICloudFederationProvider => Server::get(CloudFederationProviderTalk::class)
		);
	}
}
