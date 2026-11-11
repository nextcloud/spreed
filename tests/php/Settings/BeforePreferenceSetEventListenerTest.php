<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Settings;

use OCA\Talk\ConfigLexicon;
use OCA\Talk\Service\ParticipantService;
use OCA\Talk\Settings\BeforePreferenceSetEventListener;
use OCA\Talk\Settings\UserPreference;
use OCP\Files\IRootFolder;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BeforePreferenceSetEventListenerTest extends TestCase {
	public static function dataValidatePreference(): array {
		return [
			// yes/no
			[UserPreference::PLAY_SOUNDS, 'yes', true],
			[UserPreference::PLAY_SOUNDS, 'no', true],
			[UserPreference::PLAY_SOUNDS, 'on', false],
			[UserPreference::BLUR_VIRTUAL_BACKGROUND, 'yes', true],
			[UserPreference::BLUR_VIRTUAL_BACKGROUND, 'random', false],
			[UserPreference::CALLS_START_WITHOUT_MEDIA, 'no', true],
			[UserPreference::CALLS_START_WITHOUT_MEDIA, '1', true],
			// privacy
			[UserPreference::TYPING_PRIVACY, '0', true],
			[UserPreference::TYPING_PRIVACY, '1', true],
			[UserPreference::TYPING_PRIVACY, '5', false],
			// enums
			[UserPreference::CHAT_STYLE, UserPreference::CHAT_STYLE_SPLIT, true],
			[UserPreference::CHAT_STYLE, UserPreference::CHAT_STYLE_UNIFIED, true],
			[UserPreference::CHAT_STYLE, 'irc', false],
			[UserPreference::CONVERSATIONS_LIST_STYLE, UserPreference::CONVERSATIONS_LIST_STYLE_TWO_LINES, true],
			[UserPreference::CONVERSATIONS_LIST_STYLE, UserPreference::CONVERSATIONS_LIST_STYLE_COMPACT, true],
			[UserPreference::CONVERSATIONS_LIST_STYLE, 'wide', false],
			[UserPreference::CONVERSATIONS_SORT_ORDER, UserPreference::CONVERSATIONS_SORT_ORDER_ACTIVITY, true],
			[UserPreference::CONVERSATIONS_SORT_ORDER, UserPreference::CONVERSATIONS_SORT_ORDER_ALPHABETICAL, true],
			[UserPreference::CONVERSATIONS_SORT_ORDER, 'random', false],
			[UserPreference::CONVERSATIONS_GROUP_MODE, UserPreference::CONVERSATIONS_GROUP_MODE_NONE, true],
			[UserPreference::CONVERSATIONS_GROUP_MODE, UserPreference::CONVERSATIONS_GROUP_MODE_GROUP_FIRST, true],
			[UserPreference::CONVERSATIONS_GROUP_MODE, UserPreference::CONVERSATIONS_GROUP_MODE_PRIVATE_FIRST, true],
			[UserPreference::CONVERSATIONS_GROUP_MODE, 'x', false],
			[UserPreference::CONVERSATIONS_UNARCHIVE, UserPreference::CONVERSATIONS_UNARCHIVE_NEVER, true],
			[UserPreference::CONVERSATIONS_UNARCHIVE, UserPreference::CONVERSATIONS_UNARCHIVE_MENTION, true],
			[UserPreference::CONVERSATIONS_UNARCHIVE, UserPreference::CONVERSATIONS_UNARCHIVE_ALWAYS, true],
			[UserPreference::CONVERSATIONS_UNARCHIVE, 'x', false],
			[UserPreference::CONVERSATIONS_TAGS_SHOW_UNREAD, UserPreference::CONVERSATIONS_TAGS_SHOW_UNREAD_ALWAYS, true],
			[UserPreference::CONVERSATIONS_TAGS_SHOW_UNREAD, 'x', false],
			// any value
			[UserPreference::LIVE_TRANSCRIPTION_TARGET_LANGUAGE_ID, 'tlh', true],
			// internal or unknown keys
			[UserPreference::RECORDING_FOLDER, '/Recordings', false],
			[UserPreference::NOTE_TO_SELF, '42', false],
			[UserPreference::CHANGELOG, '36', false],
			[UserPreference::SAMPLES_CREATED, '1', false],
			[UserPreference::SIGNALING_TICKET_SECRET, '123456', false],
			['unknown_key', 'x', false],
		];
	}

	#[DataProvider('dataValidatePreference')]
	public function testValidatePreference(string $key, string $value, bool $expected): void {
		$listener = new BeforePreferenceSetEventListener(
			$this->createMock(IRootFolder::class),
			$this->createMock(ParticipantService::class),
			$this->createMock(LoggerInterface::class),
			new ConfigLexicon(),
		);

		$this->assertSame($expected, $listener->validatePreference('alice', $key, $value));
	}
}
