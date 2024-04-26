<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Talk\Service;

use Psr\Log\LoggerInterface;

class CertificateService {

	public function __construct(
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Parse a url and only returns the host and optionally the port
	 *
	 * @param string $host The url to parse (e.g. 'https://hostname:port/directory')
	 * @return string|null null if the url has a non-tls scheme, otherwise the host and optionally the port (e.g. 'hostname:port')
	 */
	public function getParsedTlsHost(string $host): ?string {
		$parsedUrl = parse_url($host);

		// parse_url failed, $host is a seriously malformed URL
		if ($parsedUrl === false) {
			return null;
		}

		if (isset($parsedUrl['scheme'])) {
			$scheme = strtolower($parsedUrl['scheme']);

			// When we have a scheme specified which is different than https/wss, there's no tls host
			if ($scheme !== 'https' && $scheme !== 'wss') {
				return null;
			}
		}

		// When we are unable to retrieve a host from the URL, just return the original host
		if (!isset($parsedUrl['host'])) {
			return $host;
		}

		$parsedHost = $parsedUrl['host'];

		if (isset($parsedUrl['port'])) {
			$parsedHost .= ':' . $parsedUrl['port'];
		}

		return $parsedHost;
	}

	/**
	 * Retrieve the hosts certificate expiration in days
	 *
	 * @param string $host The host to check the certificate of without scheme
	 * @return int|null Days until the certificate expires (negative when it's already expired)
	 */
	public function getCertificateExpirationInDays(string $host): ?int {
		$parsedHost = $this->getParsedTlsHost($host);

		if ($parsedHost === null) {
			// Unable to parse the specified host
			$this->logger->debug('Ignoring certificate check of non-tls host ' . $host);
			return null;
		}

		// We need to disable verification here to also get an expired certificate
		$streamContext = stream_context_create([
			'ssl' => [
				'capture_peer_cert' => true,
				'verify_peer' => false,
				'verify_peer_name' => false,
				'allow_self_signed' => true,
			],
		]);

		// In case no port was specified, use port 443 for the check
		if (!str_contains($parsedHost, ':')) {
			$parsedHost .= ':443';
		}

		$this->logger->debug('Checking certificate of ' . $parsedHost);

		$streamClient = stream_socket_client('ssl://' . $parsedHost, $errorNumber, $errorString, 30, STREAM_CLIENT_CONNECT, $streamContext);

		if ($streamClient === false || $errorNumber !== 0) {
			// Unable to connect or invalid server address
			$this->logger->debug('Unable to check certificate of ' . $parsedHost);
			return null;
		}

		$streamCertificate = stream_context_get_params($streamClient);
		$certificateInfo = openssl_x509_parse($streamCertificate['options']['ssl']['peer_certificate']);
		$certificateValidTo = new \DateTime('@' . $certificateInfo['validTo_time_t']);

		$now = new \DateTime();
		$diff = $now->diff($certificateValidTo);
		$days = $diff->days;

		if ($days === false) {
			return null;
		}

		// $days will always be positive -> invert it, when the end date of the certificate is in the past
		if ($diff->invert) {
			$days *= -1;
		}

		return $days;
	}
}
