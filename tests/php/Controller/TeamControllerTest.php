<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Controller;

use OCA\Talk\Controller\TeamController;
use OCA\Talk\Service\ConversationFolderService;
use OCP\AppFramework\Http;
use OCP\Files\Folder;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserSession;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class TeamControllerTest extends TestCase {
	protected ConversationFolderService&MockObject $conversationFolderService;
	protected TeamController $controller;

	public function setUp(): void {
		parent::setUp();

		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('alice');
		$userSession = $this->createMock(IUserSession::class);
		$userSession->method('getUser')->willReturn($user);
		$this->conversationFolderService = $this->createMock(ConversationFolderService::class);

		$this->controller = new TeamController(
			'spreed',
			$this->createMock(IRequest::class),
			$userSession,
			$this->conversationFolderService,
		);
	}

	public function testFolderOfTheTeamIsReturned(): void {
		$folder = $this->createMock(Folder::class);
		$this->conversationFolderService->method('getTeamFolder')->with('alice', 'team1')->willReturn($folder);
		$this->conversationFolderService->method('getRelativePath')->with('alice', $folder)->willReturn('Team A');

		$response = $this->controller->getTeamFolder('team1');

		$this->assertSame(Http::STATUS_OK, $response->getStatus());
		$this->assertSame(['path' => 'Team A', 'attachmentFolder' => ConversationFolderService::TEAM_ATTACHMENT_FOLDER], $response->getData());
	}

	public function testNoFolderWithoutAccess(): void {
		// No folder, no folder provider, or not a member of the team
		$this->conversationFolderService->method('getTeamFolder')->willReturn(null);

		$response = $this->controller->getTeamFolder('team1');

		$this->assertSame(Http::STATUS_NOT_FOUND, $response->getStatus());
		$this->assertSame(['error' => 'team'], $response->getData());
	}
}
