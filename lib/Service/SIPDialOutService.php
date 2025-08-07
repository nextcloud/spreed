<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\Model\Attendee;
use OCA\Talk\Room;
use OCA\Talk\Signaling\BackendNotifier;
use OCA\Talk\Signaling\Responses\Response;
use OCA\Talk\Vendor\CuyZ\Valinor\Mapper\MappingError;
use OCA\Talk\Vendor\CuyZ\Valinor\Mapper\Source\Source;
use OCA\Talk\Vendor\CuyZ\Valinor\MapperBuilder;
use Psr\Log\LoggerInterface;

class SIPDialOutService {

	public function __construct(
		protected BackendNotifier $backendNotifier,
		protected LoggerInterface $logger,
	) {
	}

	public function sendDialOutRequestToBackend(Room $room, Attendee $attendee, string|bool $callerNumber): ?Response {
		if ($attendee->getActorType() !== Attendee::ACTOR_PHONES) {
			return null;
		}

		$response = $this->backendNotifier->dialOutToAttendee($room, $attendee, $callerNumber);
		if ($response === null) {
			$this->logger->error('Received no response from signaling server on dialout request');
			return null;
		}
		try {
			return $this->validateDialOutResponse($response);
		} catch (\InvalidArgumentException $e) {
			$this->logger->error($e->getMessage(), ['exception' => $e]);
			return null;
		}
	}

	/**
	 * @param string $response
	 * @return Response
	 * @throws \InvalidArgumentException
	 */
	protected function validateDialOutResponse(string $response): Response {
		try {
			$dialOutResponse = (new MapperBuilder())
				->mapper()
				->map(
					Response::class,
					Source::json($response)
						->map([
							'dialout' => 'dialOut',
							'dialout.callid' => 'callId',
						])
				);
		} catch (MappingError $e) {
			throw new \InvalidArgumentException('Not a valid dial-out response', 0, $e);
		}

		if ($dialOutResponse->dialOut === null) {
			throw new \InvalidArgumentException('Not a valid dial-out response', 1);
		}

		return $dialOutResponse;
	}
}
