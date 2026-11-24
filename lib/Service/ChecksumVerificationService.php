<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2022 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use OCA\Talk\CachePrefix;
use OCA\Talk\Config;
use OCA\Talk\Exceptions\UnauthorizedException;
use OCA\Talk\Vendor\Firebase\JWT\JWT;
use OCA\Talk\Vendor\Firebase\JWT\Key;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\ICacheFactory;
use OCP\IMemcache;
use OCP\IRequest;
use SensitiveParameter;

class ChecksumVerificationService {
	/** Seconds a signed external call service request stays valid (in both directions to allow clock skew) */
	public const EXTERNAL_CALL_SERVICE_MAX_AGE = 60;

	public function __construct(
		private readonly Config $talkConfig,
		private readonly IRequest $request,
		private readonly ICacheFactory $cacheFactory,
		private readonly ITimeFactory $timeFactory,
	) {
	}

	/**
	 * Check if the current request is coming from an allowed backend.
	 *
	 * The backend servers are sending custom headers "Talk-{{FEATURE}}-Random"
	 * containing at least 32 bytes random data, and the header
	 * "Talk-{{FEATURE}}-Checksum", which is the SHA256-HMAC of the random data
	 * and the body of the request, calculated with the shared secret from the
	 * configuration.
	 *
	 * @param string $random
	 * @param string $checksum
	 * @param string $secret
	 * @param string $data
	 * @return bool True if the request is from the backend and valid, false if not from SIP bridge
	 * @throws UnauthorizedException when the request tried to authenticate as backend but is not valid
	 */
	public function validateRequest(string $random, #[SensitiveParameter] string $checksum, #[SensitiveParameter] string $secret, string $data): bool {
		if ($random === '' && $checksum === '') {
			return false;
		}

		if (strlen($random) < 32) {
			throw new UnauthorizedException('Invalid random provided');
		}

		if ($checksum === '') {
			throw new UnauthorizedException('Invalid checksum provided');
		}

		if ($secret === '') {
			throw new UnauthorizedException('No secret provided');
		}

		$hash = hash_hmac('sha256', $random . $data, $secret);

		if (hash_equals($hash, strtolower($checksum))) {
			return true;
		}

		throw new UnauthorizedException('Invalid HMAC provided');
	}

	/**
	 * Check if the current request is coming from the configured external call service
	 *
	 * The service sends the header "x-nextcloud-talk-external-service-jwt",
	 * an ES256 JWT signed with the private key matching the configured public key, with the claims:
	 * iat, exp (at most 60 seconds after iat), jti (at least 32 characters, never reused),
	 * method, uri (request URI with query string) and body (SHA256 hex of the raw body).
	 *
	 * @return bool True if the request is from the external call service, valid and not a replay
	 */
	public function validateExternalCallServiceRequest(): bool {
		if (!$this->talkConfig->isExternalCallServiceConfigured()) {
			return false;
		}

		$body = $this->getInputStream();
		$jwt = $this->request->getHeader('x-nextcloud-talk-external-service-jwt');
		if ($body === '' || $jwt === '') {
			return false;
		}

		$now = $this->timeFactory->getTime();
		JWT::$timestamp = $now;
		// The time window is checked below, the leeway only allows clock skew for iat
		JWT::$leeway = self::EXTERNAL_CALL_SERVICE_MAX_AGE;
		try {
			$claims = JWT::decode($jwt, new Key($this->talkConfig->getExternalCallServicePublicKey(), 'ES256'));
		} catch (\Throwable) {
			return false;
		} finally {
			JWT::$timestamp = null;
			JWT::$leeway = 0;
		}

		$iat = $claims->iat ?? null;
		$exp = $claims->exp ?? null;
		$jti = $claims->jti ?? null;
		$method = $claims->method ?? null;
		$uri = $claims->uri ?? null;
		$bodyHash = $claims->body ?? null;
		if (!is_int($iat) || !is_int($exp)
			|| $exp <= $iat
			|| $exp - $iat > self::EXTERNAL_CALL_SERVICE_MAX_AGE
			|| abs($now - $iat) > self::EXTERNAL_CALL_SERVICE_MAX_AGE
			|| !is_string($jti) || strlen($jti) < 32
			|| !is_string($method) || !hash_equals($this->request->getMethod(), $method)
			|| !is_string($uri) || !hash_equals($this->request->getRequestUri(), $uri)
			|| !is_string($bodyHash) || !hash_equals(hash('sha256', $body), strtolower($bodyHash))) {
			return false;
		}

		// Only remembered after a valid signature, so unauthenticated requests can not fill the cache
		/** @var IMemcache $cache */
		$cache = $this->cacheFactory->createDistributed(CachePrefix::EXTERNAL_CALL_SERVICE_NONCE);
		return $cache->add(hash('sha256', $jti), true, 2 * self::EXTERNAL_CALL_SERVICE_MAX_AGE);
	}

	/**
	 * Return the raw body of the request. This can be overridden in tests.
	 */
	protected function getInputStream(): string {
		return (string)file_get_contents('php://input');
	}
}
