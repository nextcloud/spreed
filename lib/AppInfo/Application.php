<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2022 Informatyka Boguslawski sp. z o.o. sp.k., http://www.ib.pl/
 *
 * @author Joachim Bauch <mail@joachim-bauch.de>
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

namespace OCA\Talk\AppInfo;

use OCA\Circles\Events\AddingCircleMemberEvent;
use OCA\Circles\Events\CircleDestroyedEvent;
use OCA\Circles\Events\RemovingCircleMemberEvent;
use OCA\Files_Sharing\Event\BeforeTemplateRenderedEvent;
use OCA\Talk\Activity\Listener as ActivityListener;
use OCA\Talk\Capabilities;
use OCA\Talk\Chat\Changelog\Listener as ChangelogListener;
use OCA\Talk\Chat\Command\Listener as CommandListener;
use OCA\Talk\Chat\Listener as ChatListener;
use OCA\Talk\Chat\Parser\Listener as ParserListener;
use OCA\Talk\Chat\SystemMessage\Listener as SystemMessageListener;
use OCA\Talk\Collaboration\Collaborators\Listener as CollaboratorsListener;
use OCA\Talk\Collaboration\Reference\ReferenceInvalidationListener;
use OCA\Talk\Collaboration\Reference\TalkReferenceProvider;
use OCA\Talk\Collaboration\Resources\ConversationProvider;
use OCA\Talk\Collaboration\Resources\Listener as ResourceListener;
use OCA\Talk\Config;
use OCA\Talk\Dashboard\TalkWidget;
use OCA\Talk\Deck\DeckPluginLoader;
use OCA\Talk\Events\AttendeesAddedEvent;
use OCA\Talk\Events\AttendeesRemovedEvent;
use OCA\Talk\Events\BeforeParticipantModifiedEvent;
use OCA\Talk\Events\BeforeRoomsFetchEvent;
use OCA\Talk\Events\BotInstallEvent;
use OCA\Talk\Events\BotUninstallEvent;
use OCA\Talk\Events\CallEndedForEveryoneEvent;
use OCA\Talk\Events\CallNotificationSendEvent;
use OCA\Talk\Events\ChatMessageSentEvent;
use OCA\Talk\Events\RoomDeletedEvent;
use OCA\Talk\Events\RoomModifiedEvent;
use OCA\Talk\Events\SystemMessageSentEvent;
use OCA\Talk\Federation\CloudFederationProviderTalk;
use OCA\Talk\Files\Listener as FilesListener;
use OCA\Talk\Files\TemplateLoader as FilesTemplateLoader;
use OCA\Talk\Flow\RegisterOperationsListener;
use OCA\Talk\Listener\BeforeUserLoggedOutListener;
use OCA\Talk\Listener\BotListener;
use OCA\Talk\Listener\CircleDeletedListener;
use OCA\Talk\Listener\CircleMembershipListener;
use OCA\Talk\Listener\CSPListener;
use OCA\Talk\Listener\DisplayNameListener;
use OCA\Talk\Listener\FeaturePolicyListener;
use OCA\Talk\Listener\GroupDeletedListener;
use OCA\Talk\Listener\GroupMembershipListener;
use OCA\Talk\Listener\NoteToSelfListener;
use OCA\Talk\Listener\RestrictStartingCalls as RestrictStartingCallsListener;
use OCA\Talk\Listener\UserDeletedListener;
use OCA\Talk\Maps\MapsPluginLoader;
use OCA\Talk\Middleware\CanUseTalkMiddleware;
use OCA\Talk\Middleware\InjectionMiddleware;
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
use OCA\Talk\Settings\Personal;
use OCA\Talk\Share\Listener as ShareListener;
use OCA\Talk\Share\RoomShareProvider;
use OCA\Talk\Signaling\Listener as SignalingListener;
use OCA\Talk\Status\Listener as StatusListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\IAppContainer;
use OCP\Collaboration\Resources\IProviderManager;
use OCP\Collaboration\Resources\LoadAdditionalScriptsEvent;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudFederationProvider;
use OCP\Federation\ICloudFederationProviderManager;
use OCP\Group\Events\GroupChangedEvent;
use OCP\Group\Events\GroupDeletedEvent;
use OCP\Group\Events\UserAddedEvent;
use OCP\Group\Events\UserRemovedEvent;
use OCP\IConfig;
use OCP\IServerContainer;
use OCP\IUser;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;
use OCP\Security\FeaturePolicy\AddFeaturePolicyEvent;
use OCP\Settings\IManager;
use OCP\SpeechToText\Events\TranscriptionFailedEvent;
use OCP\SpeechToText\Events\TranscriptionSuccessfulEvent;
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

	public function register(IRegistrationContext $context): void {
		$context->registerMiddleWare(CanUseTalkMiddleware::class);
		$context->registerMiddleWare(InjectionMiddleware::class);
		$context->registerCapability(Capabilities::class);

		// Listeners to load the UI and integrate it into other apps
		$context->registerEventListener(AddContentSecurityPolicyEvent::class, CSPListener::class);
		$context->registerEventListener(AddFeaturePolicyEvent::class, FeaturePolicyListener::class);
		$context->registerEventListener(\OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent::class, UnifiedSearchCSSLoader::class);
		$context->registerEventListener(\OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent::class, DeckPluginLoader::class);
		$context->registerEventListener(\OCP\AppFramework\Http\Events\BeforeTemplateRenderedEvent::class, MapsPluginLoader::class);
		$context->registerEventListener(RegisterOperationsEvent::class, RegisterOperationsListener::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, PublicShareTemplateLoader::class);
		$context->registerEventListener(BeforeTemplateRenderedEvent::class, PublicShareAuthTemplateLoader::class);

		// Bot listeners
		$context->registerEventListener(BotInstallEvent::class, BotListener::class);
		$context->registerEventListener(BotUninstallEvent::class, BotListener::class);
		$context->registerEventListener(ChatMessageSentEvent::class, BotListener::class);
		$context->registerEventListener(SystemMessageSentEvent::class, BotListener::class);

		// Chat listeners
		$context->registerEventListener(BeforeRoomsFetchEvent::class, ChangelogListener::class);
		$context->registerEventListener(RoomDeletedEvent::class, ChatListener::class);
		$context->registerEventListener(BeforeRoomsFetchEvent::class, NoteToSelfListener::class);
		$context->registerEventListener(AttendeesAddedEvent::class, SystemMessageListener::class);
		$context->registerEventListener(AttendeesRemovedEvent::class, SystemMessageListener::class);

		// Group and Circles listeners
		$context->registerEventListener(GroupDeletedEvent::class, GroupDeletedListener::class);
		$context->registerEventListener(GroupChangedEvent::class, DisplayNameListener::class);
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);
		$context->registerEventListener(UserChangedEvent::class, DisplayNameListener::class);
		$context->registerEventListener(UserAddedEvent::class, GroupMembershipListener::class);
		$context->registerEventListener(UserRemovedEvent::class, GroupMembershipListener::class);
		$context->registerEventListener(CircleDestroyedEvent::class, CircleDeletedListener::class);
		$context->registerEventListener(AddingCircleMemberEvent::class, CircleMembershipListener::class);
		$context->registerEventListener(RemovingCircleMemberEvent::class, CircleMembershipListener::class);

		// Call listeners
		$context->registerEventListener(BeforeUserLoggedOutEvent::class, BeforeUserLoggedOutListener::class);
		$context->registerEventListener(CallNotificationSendEvent::class, NotificationListener::class);
		$context->registerEventListener(BeforeParticipantModifiedEvent::class, RestrictStartingCallsListener::class, 1000);
		$context->registerEventListener(BeforeParticipantModifiedEvent::class, StatusListener::class);
		$context->registerEventListener(CallEndedForEveryoneEvent::class, StatusListener::class);

		// Recording listeners
		$context->registerEventListener(RoomDeletedEvent::class, RecordingListener::class);
		$context->registerEventListener(TranscriptionSuccessfulEvent::class, RecordingListener::class);
		$context->registerEventListener(TranscriptionFailedEvent::class, RecordingListener::class);

		// Signaling listeners
		$context->registerEventListener(RoomModifiedEvent::class, SignalingListener::class);

		// Register other integrations of Talk
		$context->registerSearchProvider(ConversationSearch::class);
		$context->registerSearchProvider(CurrentMessageSearch::class);
		$context->registerSearchProvider(MessageSearch::class);

		$context->registerDashboardWidget(TalkWidget::class);

		$context->registerNotifierService(Notifier::class);

		$context->registerProfileLinkAction(TalkAction::class);

		$context->registerReferenceProvider(TalkReferenceProvider::class);

		$context->registerTalkBackend(TalkBackend::class);
	}

	public function boot(IBootContext $context): void {
		$server = $context->getServerContainer();

		$this->registerCollaborationResourceProvider($server);
		$this->registerClientLinks($server);
		$this->registerNavigationLink($server);

		/** @var IEventDispatcher $dispatcher */
		$dispatcher = $server->get(IEventDispatcher::class);

		ActivityListener::register($dispatcher);
		NotificationListener::register($dispatcher);
		SystemMessageListener::register($dispatcher);
		ParserListener::register($dispatcher);
		PublicShareAuthListener::register($dispatcher);
		FilesListener::register($dispatcher);
		FilesTemplateLoader::register($dispatcher);
		RoomShareProvider::register($dispatcher);
		SignalingListener::register($dispatcher);
		CommandListener::register($dispatcher);
		CollaboratorsListener::register($dispatcher);
		ResourceListener::register($dispatcher);
		ReferenceInvalidationListener::register($dispatcher);
		ShareListener::register($dispatcher);

		$context->injectFn(\Closure::fromCallable([$this, 'registerCloudFederationProviderManager']));
	}

	protected function registerCollaborationResourceProvider(IServerContainer $server): void {
		/** @var IProviderManager $resourceManager */
		$resourceManager = $server->get(IProviderManager::class);
		$resourceManager->registerResourceProvider(ConversationProvider::class);
		$server->get(IEventDispatcher::class)->addListener(LoadAdditionalScriptsEvent::class, static function () {
			Util::addScript(self::APP_ID, 'talk-collections');
		});
	}

	protected function registerClientLinks(IServerContainer $server): void {
		if ($server->getAppManager()->isEnabledForUser('firstrunwizard')) {
			/** @var IManager $settingManager */
			$settingManager = $server->getSettingsManager();
			$settingManager->registerSetting('personal', Personal::class);
		}
	}

	protected function registerNavigationLink(IServerContainer $server): void {
		$server->getNavigationManager()->add(static function () use ($server) {
			/** @var Config $config */
			$config = $server->get(Config::class);
			$user = $server->getUserSession()->getUser();
			return [
				'id' => self::APP_ID,
				'name' => $server->getL10N(self::APP_ID)->t('Talk'),
				'href' => $server->getURLGenerator()->linkToRouteAbsolute('spreed.Page.index'),
				'icon' => $server->getURLGenerator()->imagePath(self::APP_ID, 'app.svg'),
				'order' => 3,
				'type' => $user instanceof IUser && !$config->isDisabledForUser($user) ? 'link' : 'hidden',
			];
		});
	}

	protected function registerCloudFederationProviderManager(
		IConfig $config,
		ICloudFederationProviderManager $manager,
		IAppContainer $appContainer): void {
		if ($config->getAppValue('spreed', 'federation_enabled', 'no') !== 'yes') {
			return;
		}

		$manager->addCloudFederationProvider(
			'talk-room',
			'Talk Federation',
			static function () use ($appContainer): ICloudFederationProvider {
				return $appContainer->get(CloudFederationProviderTalk::class);
			}
		);
	}
}
