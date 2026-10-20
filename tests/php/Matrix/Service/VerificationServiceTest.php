<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Tests\php\Matrix\Service;

use OCA\Talk\Matrix\Client\Client;
use OCA\Talk\Matrix\Client\Crypto\Base64;
use OCA\Talk\Matrix\Client\Crypto\Keys;
use OCA\Talk\Matrix\Client\Crypto\Verification\SasVerification;
use OCA\Talk\Matrix\Model\Account;
use OCA\Talk\Matrix\Model\AccountMapper;
use OCA\Talk\Matrix\Service\ExclusiveLock;
use OCA\Talk\Matrix\Service\VerificationService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Lock\ILockingProvider;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

class VerificationServiceTest extends TestCase {
	private Client&MockObject $client;
	private ITimeFactory&MockObject $timeFactory;
	private Account $account;
	private ?string $stored = null;
	/** @var list<array{type: string, messages: array}> */
	private array $sent = [];
	private string $ourKey;
	private string $theirKey;
	private VerificationService $service;

	protected function setUp(): void {
		parent::setUp();
		if (!function_exists('sodium_crypto_sign_verify_detached')) {
			$this->markTestSkipped('The sodium extension is not available');
		}

		$this->account = Account::fromRow(['id' => '7', 'mxid' => '@alice:hs', 'device_id' => 'TALK']);
		$this->ourKey = Base64::encode(Keys::ed25519KeyPair()['public']);
		$this->theirKey = Base64::encode(Keys::ed25519KeyPair()['public']);

		$accountMapper = $this->createMock(AccountMapper::class);
		$accountMapper->method('getVerification')->with('7')->willReturnCallback(fn (): ?string => $this->stored);
		$accountMapper->method('setVerification')->willReturnCallback(function (string $id, ?string $verification): void {
			$this->stored = $verification;
		});
		$this->client = $this->createMock(Client::class);
		$this->client->method('sendToDevice')->willReturnCallback(function (string $type, array $messages): void {
			$this->sent[] = ['type' => $type, 'messages' => $messages];
		});
		$crypto = $this->createMock(ICrypto::class);
		$crypto->method('encrypt')->willReturnCallback(static fn (string $plain): string => 'encrypted:' . $plain);
		$crypto->method('decrypt')->willReturnCallback(static fn (string $cipher): string => substr($cipher, strlen('encrypted:')));
		$this->timeFactory = $this->createMock(ITimeFactory::class);
		$secureRandom = $this->createMock(ISecureRandom::class);
		$secureRandom->method('generate')->willReturn('txn');

		$this->service = new VerificationService(
			$accountMapper,
			$crypto,
			new ExclusiveLock($this->createMock(ILockingProvider::class)),
			$this->timeFactory,
			$secureRandom,
		);
	}

	private function handle(string $type, array $content): void {
		$this->service->handle($this->account, $this->client, $type, $content, fn (string $deviceId): ?string => $deviceId === 'PHONE' ? $this->theirKey : null);
	}

	/**
	 * @return array<string, mixed> Content of the last sent event, which went to the device
	 */
	private function lastSentTo(string $deviceId, string $type): array {
		$last = end($this->sent);
		self::assertSame($type, $last['type']);
		return $last['messages']['@alice:hs'][$deviceId];
	}

	public function testVerificationWithOtherDevice(): void {
		$this->timeFactory->method('getTime')->willReturn(1000);
		$this->service->start($this->account, $this->client, $this->ourKey);
		$this->lastSentTo('*', 'm.key.verification.request');

		// The phone of the user answers, Talk starts the emoji verification
		$phone = new SasVerification('txn', '@alice:hs', 'PHONE', $this->theirKey, 1000);
		$phone->state = SasVerification::STATE_READY;
		$lookupOfPhone = fn (string $deviceId): ?string => $deviceId === 'TALK' ? $this->ourKey : null;
		$this->handle('m.key.verification.ready', ['from_device' => 'PHONE', 'methods' => ['m.sas.v1'], 'transaction_id' => 'txn']);
		$phone->handle('m.key.verification.start', $this->lastSentTo('PHONE', 'm.key.verification.start'), $lookupOfPhone);
		$this->handle('m.key.verification.accept', $phone->takeOutgoing()[0]['content']);
		$phone->handle('m.key.verification.key', $this->lastSentTo('PHONE', 'm.key.verification.key'), $lookupOfPhone);
		$this->handle('m.key.verification.key', $phone->takeOutgoing()[0]['content']);

		$verification = $this->service->get($this->account);
		self::assertSame(SasVerification::STATE_KEYS_EXCHANGED, $verification?->state);
		self::assertSame($phone->emoji(), $verification->emoji());

		// Both users confirm the emoji
		$this->service->confirm($this->account, $this->client);
		$phone->handle('m.key.verification.mac', $this->lastSentTo('PHONE', 'm.key.verification.mac'), $lookupOfPhone);
		$phone->confirm();
		[$mac] = $phone->takeOutgoing();
		$this->handle('m.key.verification.mac', $mac['content']);

		self::assertSame(SasVerification::STATE_DONE, $this->service->get($this->account)?->state);
		$this->lastSentTo('PHONE', 'm.key.verification.done');
		self::assertSame(SasVerification::STATE_DONE, $phone->state);
	}

	public function testEventsOfOtherTransactionsAreIgnored(): void {
		$this->timeFactory->method('getTime')->willReturn(1000);
		$this->service->start($this->account, $this->client, $this->ourKey);

		$this->handle('m.key.verification.ready', ['from_device' => 'PHONE', 'methods' => ['m.sas.v1'], 'transaction_id' => 'other']);

		self::assertSame(SasVerification::STATE_REQUESTED, $this->service->get($this->account)?->state);
		self::assertCount(1, $this->sent);
	}

	public function testTimeout(): void {
		$this->timeFactory->method('getTime')->willReturnOnConsecutiveCalls(1000, 1000 + VerificationService::TIMEOUT + 1);
		$this->service->start($this->account, $this->client, $this->ourKey);

		$verification = $this->service->get($this->account);
		self::assertSame(SasVerification::STATE_CANCELLED, $verification?->state);
		self::assertSame('Timed out', $verification->cancelReason);
	}

	public function testCancel(): void {
		$this->timeFactory->method('getTime')->willReturn(1000);
		$this->service->start($this->account, $this->client, $this->ourKey);

		$this->service->cancel($this->account, $this->client);

		self::assertSame(SasVerification::STATE_CANCELLED, $this->service->get($this->account)?->state);
		self::assertSame('m.user', $this->lastSentTo('*', 'm.key.verification.cancel')['code'], 'Nobody answered, so all devices are told');
	}
}
