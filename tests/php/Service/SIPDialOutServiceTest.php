<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Service;

use OCA\Talk\Service\SIPDialOutService;
use OCA\Talk\Signaling\BackendNotifier;
use OCA\Talk\Signaling\Responses\DialOut;
use OCA\Talk\Signaling\Responses\DialOutError;
use OCA\Talk\Signaling\Responses\Response;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class SIPDialOutServiceTest extends TestCase {
	protected BackendNotifier&MockObject $backendNotifier;
	protected LoggerInterface&MockObject $logger;
	protected ?SIPDialOutService $service = null;

	public function setUp(): void {
		parent::setUp();

		$this->backendNotifier = $this->createMock(BackendNotifier::class);
		$this->logger = $this->createMock(LoggerInterface::class);
		$this->service = new SIPDialOutService(
			$this->backendNotifier,
			$this->logger,
		);
	}

	public function testValidateDialOutResponseSuccess(): void {
		$data = <<<JSON
{
	"type": "dialout",
	"dialout": {
		"callid": "the-call-id"
	}
}
JSON;

		/** @var Response $response */
		$response = self::invokePrivate($this->service, 'validateDialOutResponse', [$data]);

		$this->assertInstanceOf(Response::class, $response);
		$this->assertInstanceOf(DialOut::class, $response->dialOut);
		$this->assertSame('the-call-id', $response->dialOut->callId);
		$this->assertNull($response->dialOut->error);
	}

	public function testValidateDialOutResponseError(): void {
		$data = <<<JSON
{
  "type": "dialout",
  "dialout": {
    "error": {
      "code": "error-code",
      "message": "Human readable error."
    }
  }
}
JSON;

		/** @var Response $response */
		$response = self::invokePrivate($this->service, 'validateDialOutResponse', [$data]);

		$this->assertInstanceOf(Response::class, $response);
		$this->assertInstanceOf(DialOut::class, $response->dialOut);
		$this->assertInstanceOf(DialOutError::class, $response->dialOut->error);
		$this->assertNull($response->dialOut->callId);
		$this->assertSame('error-code', $response->dialOut->error->code);
		$this->assertSame('Human readable error.', $response->dialOut->error->message);
	}

	public function testValidateDialOutResponseErrorWithDetails(): void {
		$data = <<<JSON
{
  "type": "dialout",
  "dialout": {
    "error": {
      "code": "error-code",
      "message": "Human readable error.",
      "details": {
        "attendeeId": 32
      }
    }
  }
}
JSON;

		/** @var Response $response */
		$response = self::invokePrivate($this->service, 'validateDialOutResponse', [$data]);

		$this->assertInstanceOf(Response::class, $response);
		$this->assertInstanceOf(DialOut::class, $response->dialOut);
		$this->assertInstanceOf(DialOutError::class, $response->dialOut->error);
		$this->assertNull($response->dialOut->callId);
		$this->assertSame('error-code', $response->dialOut->error->code);
		$this->assertSame('Human readable error.', $response->dialOut->error->message);
	}
}
