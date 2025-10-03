<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Chat\Parser;

use OCA\DAV\CardDAV\PhotoCache;
use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Chat\Parser\SystemMessage;
use OCA\Talk\Exceptions\ParticipantNotFoundException;
use OCA\Talk\Federation\Authenticator;
use OCA\Talk\GuestManager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Message;
use OCA\Talk\Model\Session;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Share\Helper\FilesMetadataCache;
use OCA\Talk\Share\RoomShareProvider;
use OCP\AppFramework\Services\IAppConfig;
use OCP\Comments\IComment;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\Federation\ICloudId;
use OCP\Federation\ICloudIdManager;
use OCP\Files\Folder;
use OCP\Files\InvalidPathException;
use OCP\Files\IRootFolder;
use OCP\Files\Node;
use OCP\Files\NotFoundException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IPreview as IPreviewManager;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Share\Exceptions\ShareNotFound;
use OCP\Share\IShare;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

#[Group('DB')]
class SystemMessageTest extends TestCase {
	protected IAppConfig&MockObject $appConfig;
	protected IUserManager&MockObject $userManager;
	protected IGroupManager&MockObject $groupManager;
	protected GuestManager&MockObject $guestManager;
	protected ParticipantService&MockObject $participantService;
	protected IPreviewManager&MockObject $previewManager;
	protected RoomShareProvider&MockObject $shareProvider;
	protected PhotoCache&MockObject $photoCache;
	protected IRootFolder&MockObject $rootFolder;
	protected IURLGenerator&MockObject $url;
	protected ICloudIdManager&MockObject $cloudIdManager;
	protected FilesMetadataCache&MockObject $filesMetadataCache;
	protected Authenticator&MockObject $federationAuthenticator;
	protected IEventDispatcher&MockObject $dispatcher;
	protected IL10N&MockObject $l;

	public function setUp(): void {
		parent::setUp();

		$this->appConfig = $this->createMock(IAppConfig::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->guestManager = $this->createMock(GuestManager::class);
		$this->participantService = $this->createMock(ParticipantService::class);
		$this->previewManager = $this->createMock(IPreviewManager::class);
		$this->shareProvider = $this->createMock(RoomShareProvider::class);
		$this->photoCache = $this->createMock(PhotoCache::class);
		$this->rootFolder = $this->createMock(IRootFolder::class);
		$this->url = $this->createMock(IURLGenerator::class);
		$this->cloudIdManager = $this->createMock(ICloudIdManager::class);
		$this->filesMetadataCache = $this->createMock(FilesMetadataCache::class);
		$this->federationAuthenticator = $this->createMock(Authenticator::class);
		$this->dispatcher = $this->createMock(IEventDispatcher::class);
		$this->l = $this->createMock(IL10N::class);
		$this->l->method('t')
			->willReturnCallback(function ($text, $parameters = []) {
				return vsprintf($text, $parameters);
			});
		$this->l->method('n')
			->willReturnCallback(function (string $singular, string $plural, int $count, array $parameters = []) {
				$text = $count === 1 ? $singular : $plural;
				return vsprintf(str_replace('%n', (string)$count, $text), $parameters);
			});
	}

	/**
	 * @param array $methods
	 * @return MockObject|SystemMessage
	 */
	protected function getParser(array $methods = []): SystemMessage {
		if (!empty($methods)) {
			$mock = $this->getMockBuilder(SystemMessage::class)
				->setConstructorArgs([
					$this->appConfig,
					$this->userManager,
					$this->groupManager,
					$this->guestManager,
					$this->participantService,
					$this->previewManager,
					$this->shareProvider,
					$this->photoCache,
					$this->rootFolder,
					$this->cloudIdManager,
					$this->url,
					$this->filesMetadataCache,
					$this->federationAuthenticator,
					$this->dispatcher,
				])
				->onlyMethods($methods)
				->getMock();
			self::invokePrivate($mock, 'l', [$this->l]);
			return $mock;
		}
		return new SystemMessage(
			$this->appConfig,
			$this->userManager,
			$this->groupManager,
			$this->guestManager,
			$this->participantService,
			$this->previewManager,
			$this->shareProvider,
			$this->photoCache,
			$this->rootFolder,
			$this->cloudIdManager,
			$this->url,
			$this->filesMetadataCache,
			$this->federationAuthenticator,
			$this->dispatcher,
		);
	}

	public static function dataParseMessage(): array {
		return [
			['conversation_created', [], 'recipient',
				'{actor} created the conversation',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['conversation_created', [], 'actor',
				'You created the conversation',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['conversation_renamed', ['oldName' => 'old', 'newName' => 'new'], 'recipient',
				'{actor} renamed the conversation from "old" to "new"',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['conversation_renamed', ['oldName' => 'old', 'newName' => 'new'], 'actor',
				'You renamed the conversation from "old" to "new"',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['description_set', ['newDescription' => 'New description'], 'recipient',
				'{actor} set the description',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['description_set', ['newDescription' => 'New description'], 'actor',
				'You set the description',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['description_removed', [], 'recipient',
				'{actor} removed the description',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['description_removed', [], 'actor',
				'You removed the description',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['call_started', [], 'recipient',
				'{actor} started a call',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['call_started', [], 'actor',
				'You started a call',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['call_joined', [], 'recipient',
				'{actor} joined the call',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['call_joined', [], 'actor',
				'You joined the call',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['call_left', [], 'recipient',
				'{actor} left the call',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['call_left', [], 'actor',
				'You left the call',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['call_ended', [], 'recipient',
				'tested by testParsecall', []
			],
			['call_ended', [], 'actor',
				'tested by testParsecall', []
			],
			['guests_allowed', [], 'recipient',
				'{actor} allowed guests',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['guests_allowed', [], 'actor',
				'You allowed guests',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['guests_disallowed', [], 'recipient',
				'{actor} disallowed guests',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['guests_disallowed', [], 'actor',
				'You disallowed guests',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['password_set', [], 'recipient',
				'{actor} set a password',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['password_set', [], 'actor',
				'You set a password',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['password_removed', [], 'recipient',
				'{actor} removed the password',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['password_removed', [], 'actor',
				'You removed the password',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['user_added', ['user' => 'user'], 'recipient',
				'{actor} added {user}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['user_added', ['user' => 'user'], 'user',
				'{actor} added you',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['user_added', ['user' => 'user'], 'actor',
				'You added {user}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['user_removed', ['user' => 'user'], 'recipient',
				'{actor} removed {user}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['user_removed', ['user' => 'actor'], 'actor',
				'You left the conversation',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'actor', 'type' => 'user']],
			],
			['user_removed', ['user' => 'user'], 'user',
				'{actor} removed you',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['user_removed', ['user' => 'user'], 'actor',
				'You removed {user}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['user_added', ['user' => 'user'], null,
				'{actor} added {user}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['user_removed', ['user' => 'user'], null,
				'{actor} removed {user}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['group_added', ['group' => 'g1'], 'recipient',
				'{actor} added group {group}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'group' => ['id' => 'g1', 'type' => 'group']],
			],
			['group_added', ['group' => 'g1'], 'actor',
				'You added group {group}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'group' => ['id' => 'g1', 'type' => 'group']],
			],
			['group_removed', ['group' => 'g1'], 'recipient',
				'{actor} removed group {group}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'group' => ['id' => 'g1', 'type' => 'group']],
			],
			['group_removed', ['group' => 'g1'], 'actor',
				'You removed group {group}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'group' => ['id' => 'g1', 'type' => 'group']],
			],
			['moderator_promoted', ['user' => 'user'], 'recipient',
				'{actor} promoted {user} to moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['moderator_promoted', ['user' => 'user'], 'user',
				'{actor} promoted you to moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['moderator_promoted', ['user' => 'user'], 'actor',
				'You promoted {user} to moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['moderator_demoted', ['user' => 'user'], 'recipient',
				'{actor} demoted {user} from moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['moderator_demoted', ['user' => 'user'], 'user',
				'{actor} demoted you from moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['moderator_demoted', ['user' => 'user'], 'actor',
				'You demoted {user} from moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['guest_moderator_promoted', ['session' => 'moderator'], 'recipient',
				'{actor} promoted {user} to moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'moderator', 'type' => 'guest']],
			],
			['guest_moderator_promoted', ['session' => 'moderator'], 'guest::user',
				'{actor} promoted {user} to moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'moderator', 'type' => 'guest']],
			],
			['guest_moderator_promoted', ['session' => 'moderator'], 'guest::moderator',
				'{actor} promoted you to moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'moderator', 'type' => 'guest']],
			],
			['guest_moderator_promoted', ['session' => 'moderator'], 'actor',
				'You promoted {user} to moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'moderator', 'type' => 'guest']],
			],
			['guest_moderator_demoted', ['session' => 'moderator'], 'recipient',
				'{actor} demoted {user} from moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'moderator', 'type' => 'guest']],
			],
			['guest_moderator_demoted', ['session' => 'moderator'], 'guest::user',
				'{actor} demoted {user} from moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'moderator', 'type' => 'guest']],
			],
			['guest_moderator_demoted', ['session' => 'moderator'], 'guest::moderator',
				'{actor} demoted you from moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'moderator', 'type' => 'guest']],
			],
			['guest_moderator_demoted', ['session' => 'moderator'], 'actor',
				'You demoted {user} from moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'moderator', 'type' => 'guest']],
			],
			['moderator_promoted', ['user' => 'user'], null,
				'{actor} promoted {user} to moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['moderator_demoted', ['user' => 'user'], null,
				'{actor} demoted {user} from moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'user', 'type' => 'user']],
			],
			['guest_moderator_promoted', ['session' => 'moderator'], null,
				'{actor} promoted {user} to moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'moderator', 'type' => 'guest']],
			],
			['guest_moderator_demoted', ['session' => 'moderator'], null,
				'{actor} demoted {user} from moderator',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'user' => ['id' => 'moderator', 'type' => 'guest']],
			],
			['file_shared', ['share' => '42'], 'recipient',
				'{file}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'file' => ['id' => 'file-from-share']],
			],
			['file_shared', ['share' => '42'], 'actor',
				'{file}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'file' => ['id' => 'file-from-share']],
			],
			['file_shared', ['share' => InvalidPathException::class], 'recipient',
				'*{actor} shared a file which is no longer available*',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['file_shared', ['share' => NotFoundException::class], 'actor',
				'*You shared a file which is no longer available*',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['read_only', [], 'recipient',
				'{actor} locked the conversation',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['read_only', [], 'actor',
				'You locked the conversation',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['read_only_off', [], 'recipient',
				'{actor} unlocked the conversation',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['read_only_off', [], 'actor',
				'You unlocked the conversation',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['listable_none', [], 'recipient',
				'{actor} limited the conversation to the current participants',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['listable_none', [], 'actor',
				'You limited the conversation to the current participants',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['listable_users', [], 'recipient',
				'{actor} opened the conversation to registered users',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['listable_users', [], 'actor',
				'You opened the conversation to registered users',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['listable_all', [], 'recipient',
				'{actor} opened the conversation to registered users and users created with the Guests app',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['listable_all', [], 'actor',
				'You opened the conversation to registered users and users created with the Guests app',
				['actor' => ['id' => 'actor', 'type' => 'user']],
			],
			['object_shared', ['metaData' => ['id' => 'geo:52.5450511,13.3741463', 'type' => 'geo-location', 'name' => 'Nextcloud Berlin Office']], 'actor',
				'{object}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'object' => ['id' => 'geo:52.5450511,13.3741463', 'type' => 'geo-location', 'name' => 'Nextcloud Berlin Office']],
			],
			['object_shared', ['metaData' => ['id' => 'geo:48,15', 'type' => 'geo-location', 'name' => 'Coarse coordinate']], 'actor',
				'{object}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'object' => ['id' => 'geo:48,15', 'type' => 'geo-location', 'name' => 'Coarse coordinate']],
			],
			['object_shared', ['metaData' => ['id' => 'geo:-48.15,-16.23,-42.108', 'type' => 'geo-location', 'name' => 'Negative coordinate with altitude']], 'actor',
				'{object}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'object' => ['id' => 'geo:-48.15,-16.23,-42.108', 'type' => 'geo-location', 'name' => 'Negative coordinate with altitude']],
			],
			['object_shared', ['metaData' => ['id' => 'geo:52.5450511,13.3741463;crs=wgs84', 'type' => 'geo-location', 'name' => 'Coordinate with reference system']], 'actor',
				'{object}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'object' => ['id' => 'geo:52.5450511,13.3741463;crs=wgs84', 'type' => 'geo-location', 'name' => 'Coordinate with reference system']],
			],
			['object_shared', ['metaData' => ['id' => 'geo:52.5450511,13.3741463;u=42', 'type' => 'geo-location', 'name' => 'Coordinate with uncertainty']], 'actor',
				'{object}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'object' => ['id' => 'geo:52.5450511,13.3741463;u=42', 'type' => 'geo-location', 'name' => 'Coordinate with uncertainty']],
			],
			['object_shared', ['metaData' => ['id' => 'geo:52.5450511,13.3741463;crs=wgs84;u=42', 'type' => 'geo-location', 'name' => 'Coordinate with reference system and uncertainty']], 'actor',
				'{object}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'object' => ['id' => 'geo:52.5450511,13.3741463;crs=wgs84;u=42', 'type' => 'geo-location', 'name' => 'Coordinate with reference system and uncertainty']],
			],
			['object_shared', ['metaData' => ['id' => 'https://nextcloud.com', 'type' => 'geo-location', 'name' => 'Link instead of geo location']], 'actor',
				'The shared location is malformed',
				[],
			],
			['federated_user_added', ['federated_user' => 'actor@federated.tld'], 'actor',
				'You invited {federated_user}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'federated_user' => ['type' => 'user', 'id' => 'actor', 'server' => 'federated.tld']],
			],
			['federated_user_added', ['federated_user' => 'actor@federated.tld'], 'user',
				'{actor} invited {federated_user}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'federated_user' => ['type' => 'user', 'id' => 'actor', 'server' => 'federated.tld']],
			],
			['federated_user_added', ['federated_user' => 'actor@federated.tld'], 'fed::actor@federated.tld',
				'{actor} invited you',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'federated_user' => ['type' => 'user', 'id' => 'actor', 'server' => 'federated.tld']],
			],
			['federated_user_added', ['federated_user' => 'actor@federated.tld'], 'fed::actor@federated.tld',
				'You accepted the invitation',
				['actor' => ['id' => 'actor', 'type' => 'user', 'server' => 'federated.tld'], 'federated_user' => ['type' => 'user', 'id' => 'actor', 'server' => 'federated.tld']],
				$federatedActor = true,
			],
			['federated_user_removed', ['federated_user' => 'actor@federated.tld'], 'actor',
				'You removed {federated_user}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'federated_user' => ['type' => 'user', 'id' => 'actor', 'server' => 'federated.tld']],
			],
			['federated_user_removed', ['federated_user' => 'actor@federated.tld'], 'user',
				'{actor} removed {federated_user}',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'federated_user' => ['type' => 'user', 'id' => 'actor', 'server' => 'federated.tld']],
			],
			['federated_user_removed', ['federated_user' => 'actor@federated.tld'], 'fed::actor@federated.tld',
				'{actor} removed you',
				['actor' => ['id' => 'actor', 'type' => 'user'], 'federated_user' => ['type' => 'user', 'id' => 'actor', 'server' => 'federated.tld']],
			],
			['federated_user_removed', ['federated_user' => 'actor@federated.tld'], 'fed::actor@federated.tld',
				'You declined the invitation',
				['actor' => ['id' => 'actor', 'type' => 'user', 'server' => 'federated.tld'], 'federated_user' => ['type' => 'user', 'id' => 'actor', 'server' => 'federated.tld']],
				$federatedActor = true,
			],
		];
	}

	#[DataProvider('dataParseMessage')]
	public function testParseMessage(string $message, array $parameters, ?string $recipientId, string $expectedMessage, array $expectedParameters, bool $federatedActor = false): void {
		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		if ($recipientId === null) {
			$participant = null;
		} elseif ($recipientId && str_starts_with($recipientId, 'guest::')) {
			$attendee = Attendee::fromRow([
				'actor_type' => 'guests',
				'actor_id' => substr($recipientId, strlen('guest::')),
			]);
			$session = Session::fromRow([
				'session_id' => substr($recipientId, strlen('guest::')),
			]);
			$participant->expects($this->atLeastOnce())
				->method('isGuest')
				->willReturn(true);
			$participant->expects($this->any())
				->method('getAttendee')
				->willReturn($attendee);
			$participant->expects($this->any())
				->method('getSession')
				->willReturn($session);
		} elseif ($recipientId && str_starts_with($recipientId, 'fed::')) {
			$attendee = Attendee::fromRow([
				'actor_type' => 'federated_users',
				'actor_id' => substr($recipientId, strlen('fed::')),
			]);
			$session = Session::fromRow([
				'session_id' => substr($recipientId, strlen('fed::')),
			]);
			$participant->method('isGuest')
				->willReturn(false);
			$participant->method('getAttendee')
				->willReturn($attendee);
			$participant->method('getSession')
				->willReturn($session);

			$this->federationAuthenticator->method('isFederationRequest')
				->willReturn(true);
			$this->federationAuthenticator->method('getCloudId')
				->willReturn(substr($recipientId, strlen('fed::')));
		} else {
			$participant->expects($this->atLeastOnce())
				->method('isGuest')
				->willReturn(false);
			$attendee = Attendee::fromRow([
				'actor_type' => 'users',
				'actor_id' => $recipientId,
			]);
			$session = null;
			$participant->expects($this->any())
				->method('getAttendee')
				->willReturn($attendee);
			$participant->expects($this->any())
				->method('getSession')
				->willReturn($session);
		}

		/** @var IComment&MockObject $comment */
		$comment = $this->createMock(IComment::class);
		if ($recipientId && str_starts_with($recipientId, 'guest::')) {
			$comment->method('getActorType')
				->willReturn('guests');
			$comment->method('getActorId')
				->willReturn(substr($recipientId, strlen('guest::')));
		} elseif ($recipientId && str_starts_with($recipientId, 'fed::')) {
			$comment->method('getActorType')
				->willReturn('federated_users');
			$comment->method('getActorId')
				->willReturn(substr($recipientId, strlen('fed::')));
		} else {
			$comment->method('getActorType')
				->willReturn('users');
			$comment->method('getActorId')
				->willReturn($recipientId);
		}

		$this->cloudIdManager->method('resolveCloudId')
			->willReturnCallback(function (string $id): ICloudId {
				[$user, $remote] = explode('@', $id);
				$cloudId = $this->createMock(ICloudId::class);
				$cloudId->method('getUser')
					->willReturn($user);
				$cloudId->method('getRemote')
					->willReturn($remote);
				$cloudId->method('getDisplayId')
					->willReturn($id);
				return $cloudId;
			});

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);

		$parser = $this->getParser(['getActorFromComment', 'getUser', 'getRemoteUser', 'getGroup', 'getGuest', 'parseCall', 'getFileFromShare']);
		$parser->expects($this->once())
			->method('getActorFromComment')
			->with($room, $comment)
			->willReturnCallback(function () use ($federatedActor): array {
				if ($federatedActor) {
					return ['id' => 'actor', 'type' => 'user', 'server' => 'federated.tld'];
				}
				return ['id' => 'actor', 'type' => 'user'];
			});
		$parser->expects($this->any())
			->method('getUser')
			->with($parameters['user'] ?? 'user')
			->willReturn(['id' => $parameters['user'] ?? 'user', 'type' => 'user']);
		$parser->method('getRemoteUser')
			->with($room, $parameters['federated_user'] ?? 'federated_user@federation.tld')
			->willReturnCallback(function (Room $room, string $id): array {
				[$user, $remote] = explode('@', $id);
				return ['type' => 'user', 'id' => $user, 'server' => $remote];
			});
		$parser->expects($this->any())
			->method('getGroup')
			->with($parameters['group'] ?? 'group')
			->willReturn(['id' => $parameters['group'] ?? 'group', 'type' => 'group']);
		$parser->expects($this->any())
			->method('getGuest')
			->with($room, Attendee::ACTOR_GUESTS, $parameters['session'] ?? 'guest')
			->willReturn(['id' => $parameters['session'] ?? 'guest', 'type' => 'guest']);

		if ($message === 'call_ended') {
			$parser->expects($this->once())
				->method('parseCall')
				->with($room, $message, $parameters, ['actor' => ['id' => 'actor', 'type' => 'user']])
				->willReturn([$expectedMessage, $expectedParameters]);
		} else {
			$parser->expects($this->never())
				->method('parseCall');
		}

		if ($message === 'file_shared') {
			if (is_subclass_of($parameters['share'], \Exception::class)) {
				$parser->expects($this->once())
					->method('getFileFromShare')
					->with($room, $participant, $parameters['share'])
					->willThrowException(new $parameters['share']());
			} else {
				$parser->expects($this->once())
					->method('getFileFromShare')
					->with($room, $participant, $parameters['share'])
					->willReturn(['id' => 'file-from-share']);
			}
		} else {
			$parser->expects($this->never())
				->method('getFileFromShare');
		}

		$chatMessage = new Message($room, $participant, $comment, $this->l);
		$chatMessage->setMessage(json_encode([
			'message' => $message,
			'parameters' => $parameters,
		]), [], $message);

		self::invokePrivate($parser, 'parseMessage', [$chatMessage, false]);

		$this->assertSame($expectedMessage, $chatMessage->getMessage());
		$this->assertSame($expectedParameters, $chatMessage->getMessageParameters());

		if ($message === 'file_shared' && !is_subclass_of($parameters['share'], \Exception::class)) {
			$this->assertSame(ChatManager::VERB_MESSAGE, $chatMessage->getMessageType());
		}
	}

	public static function dataParseMessageThrows(): array {
		return [
			['not json'],
			[json_encode('not a json array')],
			[json_encode(['message' => 'unkown_subject', 'parameters' => []])],
		];
	}

	#[DataProvider('dataParseMessageThrows')]
	public function testParseMessageThrows(?string $return): void {
		/** @var IComment&MockObject $comment */
		$comment = $this->createMock(IComment::class);

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);

		$parser = $this->getParser(['getActorFromComment']);
		$parser->expects($this->any())
			->method('getActorFromComment')
			->with($room, $comment)
			->willReturn(['id' => 'actor', 'type' => 'user']);

		/** @var Participant&MockObject $participant */
		$participant = $this->createMock(Participant::class);
		$chatMessage = new Message($room, $participant, $comment, $this->l);
		$chatMessage->setMessage($return, []);

		$this->expectException(\OutOfBoundsException::class);
		self::invokePrivate($parser, 'parseMessage', [$chatMessage, false]);
	}

	public function testGetFileFromShareForGuest(): void {
		$room = $this->createMock(Room::class);
		$node = $this->createMock(Node::class);
		$node->expects($this->once())
			->method('getId')
			->willReturn(54);
		$node->expects($this->once())
			->method('getName')
			->willReturn('name');
		$node->expects($this->atLeastOnce())
			->method('getMimeType')
			->willReturn('image/png');
		$node->expects($this->once())
			->method('getSize')
			->willReturn(65530);
		$node->expects($this->once())
			->method('getEtag')
			->willReturn(md5('etag'));
		$node->expects($this->once())
			->method('getPermissions')
			->willReturn(27);

		$share = $this->createMock(IShare::class);
		$share->expects($this->once())
			->method('getNode')
			->willReturn($node);
		$share->expects($this->once())
			->method('getToken')
			->willReturn('token');

		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willReturn($share);

		$this->url->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('files_sharing.sharecontroller.showShare', [
				'token' => 'token',
			])
			->willReturn('absolute-link');

		$this->previewManager->expects($this->once())
			->method('isAvailable')
			->with($node)
			->willReturn(true);

		$this->filesMetadataCache->expects($this->once())
			->method('getImageMetadataForFileId')
			->with(54)
			->willReturn(['width' => 1234, 'height' => 4567]);

		$participant = $this->createMock(Participant::class);

		$parser = $this->getParser();
		$this->assertSame([
			'type' => 'file',
			'id' => '54',
			'name' => 'name',
			'size' => '65530',
			'path' => 'name',
			'link' => 'absolute-link',
			'etag' => '1872ade88f3013edeb33decd74a4f947',
			'permissions' => '27',
			'mimetype' => 'image/png',
			'preview-available' => 'yes',
			'hide-download' => 'no',
			'width' => '1234',
			'height' => '4567',
		], self::invokePrivate($parser, 'getFileFromShare', [$room, $participant, '23', false]));
	}

	public function testGetFileFromShareForGuestWithBlurhash(): void {
		$room = $this->createMock(Room::class);
		$node = $this->createMock(Node::class);
		$node->expects($this->once())
			->method('getId')
			->willReturn(54);
		$node->expects($this->once())
			->method('getName')
			->willReturn('name');
		$node->expects($this->atLeastOnce())
			->method('getMimeType')
			->willReturn('image/png');
		$node->expects($this->once())
			->method('getSize')
			->willReturn(65530);
		$node->expects($this->once())
			->method('getEtag')
			->willReturn(md5('etag'));
		$node->expects($this->once())
			->method('getPermissions')
			->willReturn(27);

		$share = $this->createMock(IShare::class);
		$share->expects($this->once())
			->method('getNode')
			->willReturn($node);
		$share->expects($this->once())
			->method('getToken')
			->willReturn('token');

		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willReturn($share);

		$this->url->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('files_sharing.sharecontroller.showShare', [
				'token' => 'token',
			])
			->willReturn('absolute-link');

		$this->previewManager->expects($this->once())
			->method('isAvailable')
			->with($node)
			->willReturn(true);

		$this->filesMetadataCache->expects($this->once())
			->method('getImageMetadataForFileId')
			->with(54)
			->willReturn([
				'width' => 1234,
				'height' => 4567,
				'blurhash' => 'LEHV9uae2yk8pyo0adR*.7kCMdnj'
			]);

		$participant = $this->createMock(Participant::class);

		$parser = $this->getParser();

		$this->assertSame([
			'type' => 'file',
			'id' => '54',
			'name' => 'name',
			'size' => '65530',
			'path' => 'name',
			'link' => 'absolute-link',
			'etag' => '1872ade88f3013edeb33decd74a4f947',
			'permissions' => '27',
			'mimetype' => 'image/png',
			'preview-available' => 'yes',
			'hide-download' => 'no',
			'width' => '1234',
			'height' => '4567',
			'blurhash' => 'LEHV9uae2yk8pyo0adR*.7kCMdnj',
		], self::invokePrivate($parser, 'getFileFromShare', [$room, $participant, '23', false]));
	}

	public function testGetFileFromShareForOwner(): void {
		$room = $this->createMock(Room::class);
		$node = $this->createMock(Node::class);
		$node->expects($this->exactly(2))
			->method('getId')
			->willReturn(54);
		$node->expects($this->once())
			->method('getName')
			->willReturn('name');
		$node->expects($this->once())
			->method('getPath')
			->willReturn('/owner/files/path/to/file/name');
		$node->expects($this->atLeastOnce())
			->method('getMimeType')
			->willReturn('httpd/unix-directory');
		$node->expects($this->once())
			->method('getSize')
			->willReturn(65520);
		$node->expects($this->once())
			->method('getEtag')
			->willReturn(md5('etag'));
		$node->expects($this->once())
			->method('getPermissions')
			->willReturn(27);

		$share = $this->createMock(IShare::class);
		$share->expects($this->once())
			->method('getNode')
			->willReturn($node);
		$share->expects($this->once())
			->method('getShareOwner')
			->willReturn('owner');

		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willReturn($share);

		$this->previewManager->expects($this->once())
			->method('isAvailable')
			->with($node)
			->willReturn(false);

		$this->url->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('files.viewcontroller.showFile', [
				'fileid' => '54',
			])
			->willReturn('absolute-link-owner');

		$this->filesMetadataCache->expects($this->never())
			->method('getImageMetadataForFileId');

		$participant = $this->createMock(Participant::class);
		$attendee = Attendee::fromRow([
			'actor_type' => 'users',
			'actor_id' => 'owner',
		]);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);

		$parser = $this->getParser();
		$this->assertSame([
			'type' => 'file',
			'id' => '54',
			'name' => 'name',
			'size' => '65520',
			'path' => 'path/to/file/name',
			'link' => 'absolute-link-owner',
			'etag' => '1872ade88f3013edeb33decd74a4f947',
			'permissions' => '27',
			'mimetype' => 'httpd/unix-directory',
			'preview-available' => 'no',
			'hide-download' => 'no',
		], self::invokePrivate($parser, 'getFileFromShare', [$room, $participant, '23', false]));
	}

	public function testGetFileFromShareForRecipient(): void {
		$room = $this->createMock(Room::class);
		$share = $this->createMock(IShare::class);
		$share->expects($this->any())
			->method('getNodeId')
			->willReturn(54);

		$participant = $this->createMock(Participant::class);
		$attendee = Attendee::fromRow([
			'actor_type' => 'users',
			'actor_id' => 'user',
		]);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);

		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willReturn($share);

		$file = $this->createMock(Node::class);
		$file->expects($this->any())
			->method('getId')
			->willReturn(54);
		$file->expects($this->once())
			->method('getName')
			->willReturn('different');
		$file->expects($this->once())
			->method('getPath')
			->willReturn('/user/files/Shared/different');
		$file->expects($this->once())
			->method('getSize')
			->willReturn(65515);
		$file->expects($this->once())
			->method('getEtag')
			->willReturn(md5('etag'));
		$file->expects($this->once())
			->method('getPermissions')
			->willReturn(27);
		$file->expects($this->atLeastOnce())
			->method('getMimeType')
			->willReturn('application/octet-stream');

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('getById')
			->with('54')
			->willReturn([$file]);

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);

		$this->previewManager->expects($this->once())
			->method('isAvailable')
			->with($file)
			->willReturn(false);

		$this->filesMetadataCache->expects($this->never())
			->method('getImageMetadataForFileId');

		$this->url->expects($this->once())
			->method('linkToRouteAbsolute')
			->with('files.viewcontroller.showFile', [
				'fileid' => '54',
			])
			->willReturn('absolute-link-owner');

		$parser = $this->getParser();
		$this->assertSame([
			'type' => 'file',
			'id' => '54',
			'name' => 'different',
			'size' => '65515',
			'path' => 'Shared/different',
			'link' => 'absolute-link-owner',
			'etag' => '1872ade88f3013edeb33decd74a4f947',
			'permissions' => '27',
			'mimetype' => 'application/octet-stream',
			'preview-available' => 'no',
			'hide-download' => 'no',
		], self::invokePrivate($parser, 'getFileFromShare', [$room, $participant, '23', false]));
	}

	public function testGetFileFromShareForRecipientThrows(): void {
		$room = $this->createMock(Room::class);
		$share = $this->createMock(IShare::class);
		$share->expects($this->any())
			->method('getNodeId')
			->willReturn(54);

		$participant = $this->createMock(Participant::class);
		$attendee = Attendee::fromRow([
			'actor_type' => 'users',
			'actor_id' => 'user',
		]);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);

		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willReturn($share);

		$userFolder = $this->createMock(Folder::class);
		$userFolder->expects($this->once())
			->method('getFirstNodeById')
			->with('54')
			->willReturn(null);
		$userFolder->expects($this->once())
			->method('getById')
			->with('54')
			->willReturn([]);

		$this->rootFolder->expects($this->once())
			->method('getUserFolder')
			->with('user')
			->willReturn($userFolder);

		$this->url->expects($this->never())
			->method('linkToRouteAbsolute');

		$parser = $this->getParser();
		$this->expectException(NotFoundException::class);
		self::invokePrivate($parser, 'getFileFromShare', [$room, $participant, '23', false]);
	}

	public function testGetFileFromShareThrows(): void {
		$room = $this->createMock(Room::class);
		$this->shareProvider->expects($this->once())
			->method('getShareById')
			->with('23')
			->willThrowException(new ShareNotFound());

		$participant = $this->createMock(Participant::class);
		$attendee = Attendee::fromRow([
			'actor_type' => 'users',
			'actor_id' => 'user',
		]);
		$participant->expects($this->any())
			->method('getAttendee')
			->willReturn($attendee);
		$parser = $this->getParser();
		$this->expectException(ShareNotFound::class);
		self::invokePrivate($parser, 'getFileFromShare', [$room, $participant, '23', false]);
	}

	public static function dataGetActor(): array {
		return [
			['users', [], ['user'], ['user']],
			['guests', ['guest'], [], ['guest']],
		];
	}

	#[DataProvider('dataGetActor')]
	public function testGetActor(string $actorType, array $guestData, array $userData, array $expected): void {
		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);

		$chatMessage = $this->createMock(IComment::class);
		$chatMessage->expects($this->once())
			->method('getActorType')
			->willReturn($actorType);
		$chatMessage->expects($this->once())
			->method('getActorId')
			->willReturn('author-id');

		$parser = $this->getParser(['getGuest', 'getUser']);
		if (empty($guestData)) {
			$parser->expects($this->never())
				->method('getGuest');
		} else {
			$parser->expects($this->once())
				->method('getGuest')
				->with($room, Attendee::ACTOR_GUESTS, 'author-id')
				->willReturn($guestData);
		}

		if (empty($userData)) {
			$parser->expects($this->never())
				->method('getUser');
		} else {
			$parser->expects($this->once())
				->method('getUser')
				->with('author-id')
				->willReturn($userData);
		}

		$this->assertSame($expected, self::invokePrivate($parser, 'getActorFromComment', [$room, $chatMessage]));
	}

	public static function dataGetUser(): array {
		return [
			['test', [], false, 'Test'],
			['foo', ['admin' => 'Admin'], false, 'Bar'],
			['admin', ['admin' => 'Administrator'], true, 'Administrator'],
		];
	}

	#[DataProvider('dataGetUser')]
	public function testGetUser(string $uid, array $cache, bool $cacheHit, string $name): void {
		$parser = $this->getParser(['getDisplayName']);

		self::invokePrivate($parser, 'displayNames', [$cache]);

		if (!$cacheHit) {
			$parser->expects($this->once())
				->method('getDisplayName')
				->with($uid)
				->willReturn($name);
		} else {
			$parser->expects($this->never())
				->method('getDisplayName');
		}

		$result = self::invokePrivate($parser, 'getUser', [$uid]);
		$this->assertSame('user', $result['type']);
		$this->assertSame($uid, $result['id']);
		$this->assertSame($name, $result['name']);
	}

	public static function dataGetDisplayName(): array {
		return [
			['test', true, 'Test'],
			['foo', false, 'foo'],
		];
	}

	#[DataProvider('dataGetDisplayName')]
	public function testGetDisplayName(string $uid, bool $validUser, string $name): void {
		$parser = $this->getParser();

		if ($validUser) {
			$this->userManager->expects($this->once())
				->method('getDisplayName')
				->with($uid)
				->willReturn($name);
		} else {
			$this->userManager->expects($this->once())
				->method('getDisplayName')
				->with($uid)
				->willReturn(null);
			$this->expectException(ParticipantNotFoundException::class);
		}

		$this->assertSame($name, self::invokePrivate($parser, 'getDisplayName', [$uid]));
	}

	public static function dataGetGroup(): array {
		return [
			['test', [], false, 'Test'],
			['foo', ['admin' => 'Admin'], false, 'Bar'],
			['admin', ['admin' => 'Administrator'], true, 'Administrator'],
		];
	}

	#[DataProvider('dataGetGroup')]
	public function testGetGroup(string $gid, array $cache, bool $cacheHit, string $name): void {
		$parser = $this->getParser(['getDisplayNameGroup']);

		self::invokePrivate($parser, 'groupNames', [$cache]);

		if (!$cacheHit) {
			$parser->expects(self::once())
				->method('getDisplayNameGroup')
				->with($gid)
				->willReturn($name);
		} else {
			$parser->expects(self::never())
				->method('getDisplayNameGroup');
		}

		$result = self::invokePrivate($parser, 'getGroup', [$gid]);
		self::assertSame('group', $result['type']);
		self::assertSame($gid, $result['id']);
		self::assertSame($name, $result['name']);
	}

	public static function dataGetDisplayNameGroup(): array {
		return [
			['test', true, 'Test'],
			['foo', false, 'foo'],
		];
	}

	#[DataProvider('dataGetDisplayNameGroup')]
	public function testGetDisplayNameGroup(string $gid, bool $validGroup, string $name): void {
		$parser = $this->getParser();

		if ($validGroup) {
			$group = $this->createMock(IGroup::class);
			$group->expects(self::once())
				->method('getDisplayName')
				->willReturn($name);
			$this->groupManager->expects(self::once())
				->method('get')
				->with($gid)
				->willReturn($group);
		} else {
			$this->groupManager->expects(self::once())
				->method('get')
				->with($gid)
				->willReturn(null);
		}

		self::assertSame($name, self::invokePrivate($parser, 'getDisplayNameGroup', [$gid]));
	}

	public static function dataGetGuest(): array {
		return [
			[Attendee::ACTOR_GUESTS, sha1('name'), 'guest/' . sha1('name')],
			[Attendee::ACTOR_EMAILS, hash('sha256', 'test@test.tld'), 'email/' . hash('sha256', 'test@test.tld')],
		];
	}

	#[DataProvider('dataGetGuest')]
	public function testGetGuest(string $attendeeType, string $actorId, string $expected): void {
		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);

		$parser = $this->getParser(['getGuestName']);
		$parser->expects($this->once())
			->method('getGuestName')
			->with($room, $attendeeType, $actorId)
			->willReturn('name');

		$this->assertSame([
			'type' => 'guest',
			'id' => $expected,
			'name' => 'name',
			'mention-id' => $expected,
		], self::invokePrivate($parser, 'getGuest', [$room, $attendeeType, $actorId]));

		// Cached call: no call to getGuestName() again
		$this->assertSame([
			'type' => 'guest',
			'id' => $expected,
			'name' => 'name',
			'mention-id' => $expected,
		], self::invokePrivate($parser, 'getGuest', [$room, $attendeeType, $actorId]));
	}

	public static function dataGetGuestName(): array {
		return [
			[Attendee::ACTOR_GUESTS, sha1('name'), 'name', 'name (guest)'],
			[Attendee::ACTOR_GUESTS, sha1('name'), '', 'Guest'],
			[Attendee::ACTOR_EMAILS, 'test@test.tld', 'name', 'name (guest)'],
			[Attendee::ACTOR_EMAILS, 'test@test.tld', '', 'test@test.tld (guest)'],
		];
	}

	#[DataProvider('dataGetGuestName')]
	public function testGetGuestName(string $actorType, string $actorId, string $attendeeName, string $expected): void {
		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);

		$attendee = Attendee::fromParams([
			'displayName' => $attendeeName,
		]);

		/** @var Participant&MockObject $room */
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')
			->willReturn($attendee);

		$this->participantService->method('getParticipantByActor')
			->with($room, $actorType, $actorId)
			->willReturn($participant);

		$parser = $this->getParser();
		self::invokePrivate($parser, 'l', [$this->l]);
		$this->assertSame($expected, self::invokePrivate($parser, 'getGuestName', [$room, $actorType, $actorId]));
	}

	public function testGetGuestNameThrows(): void {
		$actorId = sha1('name');

		/** @var Room&MockObject $room */
		$room = $this->createMock(Room::class);

		$this->participantService->method('getParticipantByActor')
			->with($room, Attendee::ACTOR_GUESTS, $actorId)
			->willThrowException(new ParticipantNotFoundException());

		$parser = $this->getParser();
		self::invokePrivate($parser, 'l', [$this->l]);
		$this->assertSame('Guest', self::invokePrivate($parser, 'getGuestName', [$room, Attendee::ACTOR_GUESTS, $actorId]));
	}

	public static function dataParseCall(): array {
		return [
			'1 user' => [
				'call_ended',
				['users' => ['user1'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1} ended (Duration "duration")',
					['user1' => ['data' => 'user1']],
				],
			],
			'1 user + guests' => [
				'call_ended',
				['users' => ['user1'], 'guests' => 3, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1} and 3 guests ended (Duration "duration")',
					['user1' => ['data' => 'user1']],
				],
			],
			'2 users' => [
				'call_ended',
				['users' => ['user1', 'user2'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1} and {user2} ended (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2']],
				],
			],
			'2 users + guests' => [
				'call_ended',
				['users' => ['user1', 'user2'], 'guests' => 1, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1}, {user2} and 1 guest ended (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2']],
				],
			],
			'3 users' => [
				'call_ended',
				['users' => ['user1', 'user2', 'user3'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1}, {user2} and {user3} ended (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3']],
				],
			],
			'3 users + guests' => [
				'call_ended',
				['users' => ['user1', 'user2', 'user3'], 'guests' => 22, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1}, {user2}, {user3} and 22 guests ended (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3']],
				],
			],
			'4 users' => [
				'call_ended',
				['users' => ['user1', 'user2', 'user3', 'user4'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1}, {user2}, {user3} and {user4} ended (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],
			'4 users + guests' => [
				'call_ended',
				['users' => ['user1', 'user2', 'user3', 'user4'], 'guests' => 4, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and 4 guests ended (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],
			'5 users' => [
				'call_ended',
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and {user5} ended (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4'], 'user5' => ['data' => 'user5']],
				],
			],
			'5 users + guests' => [
				'call_ended',
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5'], 'guests' => 1, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and 2 others ended (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],
			'6 users' => [
				'call_ended',
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5', 'user6'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and 2 others ended (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],
			'6 users + guests' => [
				'call_ended',
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5', 'user6'], 'guests' => 2, 'duration' => 42],
				['type' => 'user', 'id' => 'admin', 'name' => 'Admin'],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and 4 others ended (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],

			// Meetings
			'meeting guests only' => [
				'call_ended_everyone',
				['users' => [], 'guests' => 33, 'duration' => 42],
				['type' => 'guest', 'id' => sha1('guest1'), 'name' => 'guest1'],
				[
					'{actor} ended the call with 32 guests (Duration "duration")',
					[],
				],
			],
			'meeting 2 user' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2'], 'guests' => 3, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1} and 3 guests (Duration "duration")',
					['user1' => ['data' => 'user2']],
				],
			],
			'meeting 1 user' => [
				'call_ended_everyone',
				['users' => ['user1'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call (Duration "duration")',
					[],
				],
			],
			'meeting 1 user + guests' => [
				'call_ended_everyone',
				['users' => ['user1'], 'guests' => 3, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with 3 guests (Duration "duration")',
					[],
				],
			],
			'meeting 3 users' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2', 'user3'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1} and {user2} (Duration "duration")',
					['user1' => ['data' => 'user2'], 'user2' => ['data' => 'user3']],
				],
			],
			'meeting 2 users + guests' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2'], 'guests' => 1, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1} and 1 guest (Duration "duration")',
					['user1' => ['data' => 'user2']],
				],
			],
			'meeting 4 users' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2', 'user3', 'user4'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1}, {user2} and {user3} (Duration "duration")',
					['user1' => ['data' => 'user2'], 'user2' => ['data' => 'user3'], 'user3' => ['data' => 'user4']],
				],
			],
			'meeting 3 users + guests' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2', 'user3'], 'guests' => 22, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1}, {user2} and 22 guests (Duration "duration")',
					['user1' => ['data' => 'user2'], 'user2' => ['data' => 'user3']],
				],
			],
			'meeting 5 users' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1}, {user2}, {user3} and {user4} (Duration "duration")',
					['user1' => ['data' => 'user2'], 'user2' => ['data' => 'user3'], 'user3' => ['data' => 'user4'], 'user4' => ['data' => 'user5']],
				],
			],
			'meeting 4 users + guests' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2', 'user3', 'user4'], 'guests' => 4, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1}, {user2}, {user3} and 4 guests (Duration "duration")',
					['user1' => ['data' => 'user2'], 'user2' => ['data' => 'user3'], 'user3' => ['data' => 'user4']],
				],
			],
			'meeting 6 users' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5', 'user6'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1}, {user2}, {user3}, {user4} and {user5} (Duration "duration")',
					['user1' => ['data' => 'user2'], 'user2' => ['data' => 'user3'], 'user3' => ['data' => 'user4'], 'user4' => ['data' => 'user5'], 'user5' => ['data' => 'user6']],
				],
			],
			'meeting 5 users + guests' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5'], 'guests' => 1, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1}, {user2}, {user3}, {user4} and 1 guest (Duration "duration")',
					['user1' => ['data' => 'user2'], 'user2' => ['data' => 'user3'], 'user3' => ['data' => 'user4'], 'user4' => ['data' => 'user5']],
				],
			],
			'meeting 7 users' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5', 'user6', 'user7'], 'guests' => 0, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1}, {user2}, {user3}, {user4} and 2 others (Duration "duration")',
					['user1' => ['data' => 'user2'], 'user2' => ['data' => 'user3'], 'user3' => ['data' => 'user4'], 'user4' => ['data' => 'user5']],
				],
			],
			'meeting 6 users + guests' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2', 'user3', 'user4', 'user5', 'user6'], 'guests' => 2, 'duration' => 42],
				['type' => 'user', 'id' => 'user1', 'name' => 'user1'],
				[
					'{actor} ended the call with {user1}, {user2}, {user3}, {user4} and 3 others (Duration "duration")',
					['user1' => ['data' => 'user2'], 'user2' => ['data' => 'user3'], 'user3' => ['data' => 'user4'], 'user4' => ['data' => 'user5']],
				],
			],
			'numeric users only' => [
				'call_ended_everyone',
				['users' => ['123', '234', '345', '456', '576', '678'], 'guests' => 2, 'duration' => 42],
				['type' => 'user', 'id' => '123', 'name' => '123'],
				[
					'{actor} ended the call with {user1}, {user2}, {user3}, {user4} and 3 others (Duration "duration")',
					['user1' => ['data' => '234'], 'user2' => ['data' => '345'], 'user3' => ['data' => '456'], 'user4' => ['data' => '576']],
				],
			],

			// Automatically ended by background job (max_call_duration reached)
			'max_call_duration cleanup' => [
				'call_ended_everyone',
				['users' => ['user1', 'user2', 'user3', 'user4'], 'guests' => 4, 'duration' => 90],
				['type' => 'guest', 'id' => 'guest/system', 'name' => 'system'],
				[
					'Call with {user1}, {user2}, {user3}, {user4} and 4 guests was ended, as it reached the maximum call duration (Duration "duration")',
					['user1' => ['data' => 'user1'], 'user2' => ['data' => 'user2'], 'user3' => ['data' => 'user3'], 'user4' => ['data' => 'user4']],
				],
			],
		];
	}

	#[DataProvider('dataParseCall')]
	public function testParseCall(string $message, array $parameters, array $actor, array $expected): void {
		$room = $this->createMock(Room::class);
		$parser = $this->getParser(['getDuration', 'getUser']);
		$parser->expects($this->once())
			->method('getDuration')
			->with($parameters['duration'])
			->willReturn('"duration"');

		$this->appConfig->method('getAppValueInt')
			->with('max_call_duration')
			->willReturn(60);

		$parser->expects($this->any())
			->method('getUser')
			->willReturnCallback(function ($user) {
				return ['data' => $user];
			});

		// Prepend the actor
		$expected[1]['actor'] = $actor;

		$this->assertEquals($expected, self::invokePrivate($parser, 'parseCall', [$room, $message, $parameters, ['actor' => $actor]]));
	}

	public static function dataGetDuration(): array {
		return [
			[30, '0:30'],
			[140, '2:20'],
			[5421, '1:30:21'],
			[7221, '2:00:21'],
		];
	}

	#[DataProvider('dataGetDuration')]
	public function testGetDuration(int $seconds, string $expected): void {
		$parser = $this->getParser();
		$this->assertSame($expected, self::invokePrivate($parser, 'getDuration', [$seconds]));
	}
}
