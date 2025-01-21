<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\TalkWebhookDemo\Controller;

use OCA\TalkWebhookDemo\Model\Bot;
use OCA\TalkWebhookDemo\Model\LogEntry;
use OCA\TalkWebhookDemo\Model\LogEntryMapper;
use OCA\TalkWebhookDemo\Service\SummaryService;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\BruteForceProtection;
use OCP\AppFramework\Http\Attribute\PublicPage;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Http\Client\IClientService;
use OCP\ICertificateManager;
use OCP\IConfig;
use OCP\IRequest;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;

class BotController extends OCSController {

	protected bool $legacySecret = false;

	public function __construct(
		string $appName,
		IRequest $request,
		protected IClientService $clientService,
		protected ITimeFactory $timeFactory,
		protected IFactory $l10nFactory,
		protected LogEntryMapper $logEntryMapper,
		protected SummaryService $summaryService,
		protected IConfig $config,
		protected LoggerInterface $logger,
		protected ICertificateManager $certificateManager,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Return the body of the POST request
	 */
	protected function getInputStream(): string {
		return file_get_contents('php://input');
	}

	#[BruteForceProtection(action: 'webhook')]
	#[PublicPage]
	public function receiveWebhook(string $lang): DataResponse {
		if (!in_array($lang, Bot::SUPPORTED_LANGUAGES, true)) {
			$this->logger->warning('Request for unsupported language was sent');
			$response = new DataResponse([], Http::STATUS_BAD_REQUEST);
			$response->throttle(['action' => 'webhook']);
			return $response;
		}

		$signature = $this->request->getHeader('X_NEXTCLOUD_TALK_SIGNATURE');
		$random = $this->request->getHeader('X_NEXTCLOUD_TALK_RANDOM');
		$server = rtrim($this->request->getHeader('X_NEXTCLOUD_TALK_BACKEND'), '/') . '/';

		$secretData = $this->config->getAppValue('talk_webhook_demo', 'secret_' . sha1($server));
		if ($secretData === '') {
			$this->logger->warning('No matching secret found for server: ' . $server);
			$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
			$response->throttle(['action' => 'webhook']);
			return $response;
		}

		try {
			$config = json_decode($secretData, true, 512, JSON_THROW_ON_ERROR);
		} catch (\JsonException) {
			$this->logger->error('Could not json_decode config');
			return new DataResponse([], Http::STATUS_INTERNAL_SERVER_ERROR);
		}

		$body = $this->getInputStream();
		$secret = $config['secret'] . str_replace('_', '', $lang);
		$generatedDigest = hash_hmac('sha256', $random . $body, $secret);

		if (!hash_equals($generatedDigest, strtolower($signature))) {
			$generatedLegacyDigest = hash_hmac('sha256', $random . $body, $config['secret']);
			if (!hash_equals($generatedLegacyDigest, strtolower($signature))) {
				$this->logger->warning('Message signature could not be verified');
				$response = new DataResponse([], Http::STATUS_UNAUTHORIZED);
				$response->throttle(['action' => 'webhook']);
				return $response;
			}
			// Installed before final release, when the secret was not unique
			$secret = $config['secret'];
			$this->legacySecret = true;
		}

		$this->logger->debug($body);
		$data = json_decode($body, true);

		if ($data['type'] === 'Create' && $data['object']['name'] === 'message') {
			$messageData = json_decode($data['object']['content'], true);
			$message = $messageData['message'];

			if (!$this->logEntryMapper->hasActiveCall($server, $data['target']['id'])) {
				$agendaDetected = $this->summaryService->readAgendaFromMessage($message, $messageData, $server, $data);

				if ($agendaDetected) {
					// React with thumbs up as we detected an agenda item
					$this->sendReaction($server, $secret, $data);
				}
				return new DataResponse();
			}

			$taskDetected = $this->summaryService->readTasksFromMessage($message, $messageData, $server, $data);

			if ($taskDetected) {
				// React with thumbs up as we detected a task
				$this->sendReaction($server, $secret, $data);
				// Sample: $this->removeReaction($server, $secret, $data);
			}
		} elseif ($data['type'] === 'Activity') {
			if ($data['object']['name'] === 'call_joined' || $data['object']['name'] === 'call_started') {
				if ($data['object']['name'] === 'call_started') {
					$this->postAgenda($server, $secret, $random, $data, $lang);

					$logEntry = new LogEntry();
					$logEntry->setServer($server);
					$logEntry->setToken($data['target']['id']);
					$logEntry->setType(LogEntry::TYPE_START);
					$logEntry->setDetails((string)$this->timeFactory->now()->getTimestamp());
					$this->logEntryMapper->insert($logEntry);

					$logEntry = new LogEntry();
					$logEntry->setServer($server);
					$logEntry->setToken($data['target']['id']);
					$logEntry->setType(LogEntry::TYPE_ELEVATOR);
					$logEntry->setDetails((string)$data['object']['id']);
					$this->logEntryMapper->insert($logEntry);
				}

				$logEntry = new LogEntry();
				$logEntry->setServer($server);
				$logEntry->setToken($data['target']['id']);
				$logEntry->setType(LogEntry::TYPE_ATTENDEE);

				$displayName = $data['actor']['name'];
				if (str_starts_with($data['actor']['id'], 'guests/') || str_starts_with($data['actor']['id'], 'emails/')) {
					if ($displayName === '') {
						return new DataResponse();
					}
					$l = $this->l10nFactory->get('talk_webhook_demo', $lang);
					$displayName = $l->t('%s (guest)', $displayName);
				} elseif (str_starts_with($data['actor']['id'], 'federated_users/')) {
					$cloudIdServer = explode('@', $data['actor']['id']);
					$displayName .= ' (' . array_pop($cloudIdServer) . ')';
				}

				$logEntry->setDetails($displayName);
				if ($logEntry->getDetails()) {
					// Only store when not empty
					$this->logEntryMapper->insert($logEntry);
				}
			} elseif ($data['object']['name'] === 'call_ended' || $data['object']['name'] === 'call_ended_everyone') {
				$summary = $this->summaryService->summarize($server, $data['target']['id'], $data['target']['name'], $lang);
				if ($summary !== null) {
					$body = [
						'message' => $summary['summary'],
						'referenceId' => sha1($random),
					];

					if (!empty($summary['elevator'])) {
						$body['replyTo'] = $summary['elevator'];
					}

					// Generate and post summary
					$this->sendResponse($server, $secret, $body, $data);
				}
			}
		}
		return new DataResponse();
	}

	protected function postAgenda(string $server, string $secret, string $random, array $data, string $lang): void {
		$agenda = $this->summaryService->agenda($server, $data['target']['id'], $lang);
		if ($agenda !== null) {
			$body = [
				'message' => $agenda,
				'referenceId' => sha1($random),
			];

			// Generate and post summary
			$this->sendResponse($server, $secret, $body, $data);
		}
	}

	protected function sendResponse(string $server, string $secret, array $body, array $data): void {
		$jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

		$random = bin2hex(random_bytes(32));
		$hash = hash_hmac('sha256', $random . $body['message'], $secret);
		$this->logger->debug('Reply: Random ' . $random);
		$this->logger->debug('Reply: Hash ' . $hash);

		try {
			$options = [
				'headers' => [
					'OCS-APIRequest' => 'true',
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
					'X-Nextcloud-Talk-Bot-Random' => $random,
					'X-Nextcloud-Talk-Bot-Signature' => $hash,
					'User-Agent' => 'nextcloud-call-summary-bot/1.0',
				],
				'body' => $jsonBody,
				'verify' => $this->certificateManager->getAbsoluteBundlePath(),
				'nextcloud' => [
					'allow_local_address' => true,
				],
			];

			$client = $this->clientService->newClient();
			$response = $client->post(rtrim($server, '/') . '/ocs/v2.php/apps/spreed/api/v1/bot/' . $data['target']['id'] . '/message', $options);
			$this->logger->info('Response: ' . $response->getBody());
		} catch (\Exception $exception) {
			$this->logger->info($exception::class . ': ' . $exception->getMessage());
		}
	}

	protected function sendReaction(string $server, string $secret, array $data): void {
		$body = [
			'reaction' => '👍',
		];
		$jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

		$random = bin2hex(random_bytes(32));
		$hash = hash_hmac('sha256', $random . $body['reaction'], $secret);
		$this->logger->debug('Reaction: Random ' . $random);
		$this->logger->debug('Reaction: Hash ' . $hash);

		try {
			$options = [
				'headers' => [
					'OCS-APIRequest' => 'true',
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
					'X-Nextcloud-Talk-Bot-Random' => $random,
					'X-Nextcloud-Talk-Bot-Signature' => $hash,
					'User-Agent' => 'nextcloud-call-summary-bot/1.0',
				],
				'body' => $jsonBody,
				'verify' => $this->certificateManager->getAbsoluteBundlePath(),
				'nextcloud' => [
					'allow_local_address' => true,
				],
			];

			$client = $this->clientService->newClient();
			$response = $client->post(rtrim($server, '/') . '/ocs/v2.php/apps/spreed/api/v1/bot/' . $data['target']['id'] . '/reaction/' . $data['object']['id'], $options);
			$this->logger->info('Response: ' . $response->getBody());
		} catch (\Exception $exception) {
			$this->logger->info($exception::class . ': ' . $exception->getMessage());
		}
	}

	protected function removeReaction(string $server, string $secret, array $data): void {
		$body = [
			'reaction' => '👍',
		];
		$jsonBody = json_encode($body, JSON_THROW_ON_ERROR);

		$random = bin2hex(random_bytes(32));
		$hash = hash_hmac('sha256', $random . $body['reaction'], $secret);
		$this->logger->debug('RemoveReaction: Random ' . $random);
		$this->logger->debug('RemoveReaction: Hash ' . $hash);

		try {
			$options = [
				'headers' => [
					'OCS-APIRequest' => 'true',
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
					'X-Nextcloud-Talk-Bot-Random' => $random,
					'X-Nextcloud-Talk-Bot-Signature' => $hash,
					'User-Agent' => 'nextcloud-call-summary-bot/1.0',
				],
				'body' => $jsonBody,
				'verify' => $this->certificateManager->getAbsoluteBundlePath(),
				'nextcloud' => [
					'allow_local_address' => true,
				],
			];

			$client = $this->clientService->newClient();
			$response = $client->delete(rtrim($server, '/') . '/ocs/v2.php/apps/spreed/api/v1/bot/' . $data['target']['id'] . '/reaction/' . $data['object']['id'], $options);
			$this->logger->info('Response: ' . $response->getBody());
		} catch (\Exception $exception) {
			$this->logger->info($exception::class . ': ' . $exception->getMessage());
		}
	}
}
