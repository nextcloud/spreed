<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Federation\Proxy\TalkV1\Controller;

use OCA\Talk\Federation\Proxy\TalkV1\Controller\PollController;
use OCA\Talk\Federation\Proxy\TalkV1\ProxyRequest;
use OCA\Talk\Federation\Proxy\TalkV1\UserConverter;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCP\AppFramework\Http;
use OCP\Http\Client\IResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class PollControllerTest extends TestCase {

	public static function visibilityRequests(): array {
		$cases = [];
		foreach (range(0, 15) as $mode) {
			foreach ([false, true] as $updateDraft) {
				$cases[] = [$updateDraft, $mode];
			}
		}
		return $cases;
	}

	#[DataProvider('visibilityRequests')]
	public function testForwardsResultMode(bool $updateDraft, int $mode): void {
		$attendee = new Attendee();
		$attendee->setInvitedCloudId('alice@example.com');
		$attendee->setAccessToken('access-token');
		$participant = $this->createMock(Participant::class);
		$participant->method('getAttendee')->willReturn($attendee);
		$room = $this->createMock(Room::class);
		$room->method('getRemoteServer')->willReturn('https://example.com');
		$room->method('getRemoteToken')->willReturn('remote');
		$parameters = [
			'question' => 'Lunch?',
			'options' => ['Pizza', 'Pasta'],
			'resultMode' => $mode,
			'maxVotes' => 1,
		];
		if (!$updateDraft) {
			$parameters['draft'] = true;
		}
		$response = $this->createMock(IResponse::class);
		$response->method('getStatusCode')->willReturn(Http::STATUS_OK);
		$proxy = $this->createMock(ProxyRequest::class);
		$proxy->expects($this->once())->method('post')->with(
			'alice@example.com',
			'access-token',
			'https://example.com/ocs/v2.php/apps/spreed/api/v1/poll/remote' . ($updateDraft ? '/draft/1' : ''),
			$parameters,
		)->willReturn($response);
		$data = ['id' => 1, 'resultMode' => $mode];
		$proxy->method('getOCSData')->willReturn($data);
		$converter = $this->createMock(UserConverter::class);
		$converter->method('convertPoll')->willReturnArgument(1);
		$controller = new PollController($proxy, $converter, $this->createMock(LoggerInterface::class));

		$result = $updateDraft
			? $controller->updateDraftPoll(1, $room, $participant, 'Lunch?', ['Pizza', 'Pasta'], $mode, 1)
			: $controller->createPoll($room, $participant, 'Lunch?', ['Pizza', 'Pasta'], $mode, 1, true);
		$this->assertSame($data, $result->getData());
	}
}
