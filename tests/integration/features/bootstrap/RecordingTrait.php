<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Gherkin\Node\TableNode;
use Behat\Hook\AfterScenario;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use GuzzleHttp\Client;
use PHPUnit\Framework\Assert;

// The following attributes and methods are expected to be available in the
// class that uses this trait:
// - baseUrl
// - assertStatusCode()
// - sendRequest()
// - sendRequestFullUrl()
// - setAppConfig()
trait RecordingTrait {
	/** @var ?resource */
	private $recordingServerProcess = '';
	/** @var ?resource */
	private $signalingServerProcess = '';

	private string $recordingServerAddress = 'localhost';
	private int $recordingServerPort = 0;
	private string $signalingServerAddress = 'localhost';
	private int $signalingServerPort = 0;

	private function getRecordingServerAddress(): string {
		if (!str_contains($this->recordingServerAddress, ':')) {
			$port = $this->getOpenPort($this->recordingServerAddress);
			$this->recordingServerAddress = $this->recordingServerAddress . ':' . $port;
		}
		return $this->recordingServerAddress;
	}

	private function getSignalingServerAddress(): string {
		if (!str_contains($this->signalingServerAddress, ':')) {
			$port = $this->getOpenPort($this->signalingServerAddress);
			$this->signalingServerAddress = $this->signalingServerAddress . ':' . $port;
		}
		return $this->signalingServerAddress;
	}

	/**
	 * Get an open port
	 */
	private function getOpenPort(string $address): int {
		$sock = socket_create(AF_INET, SOCK_STREAM, 0);

		if (!socket_bind($sock, $address, 0)) {
			throw new \Exception('Failute to bind socket to host ' . $address);
		}

		socket_getsockname($sock, $ipAddress, $port);
		socket_close($sock);

		if ($port > 0) {
			return $port;
		}

		throw new \Exception('Impossible to find an open port');
	}

	#[Given('/^(recording|signaling) server is started$/')]
	public function recordingServerIsStarted(): void {
		if ($this->isRunning()) {
			return;
		}

		// "the … secret" is hardcoded in the fake recording server.
		$this->setAppConfig('spreed', new TableNode([['recording_servers', json_encode([
			'servers' => [
				[
					'server' => 'http://' . $this->getRecordingServerAddress()
				]
			],
			'secret' => 'the recording secret'
		])]]));
		$this->setAppConfig('spreed', new TableNode([['signaling_servers', json_encode([
			'servers' => [
				[
					'server' => 'http://' . $this->getSignalingServerAddress()
				]
			],
			'secret' => 'the signaling secret'
		])]]));

		$path = 'mocks/FakeRecordingServer.php';
		$this->recordingServerProcess = $this->startMockServer(
			$this->getRecordingServerAddress() . ' ' . $path
		);

		$path = 'mocks/FakeSignalingServer.php';
		$this->signalingServerProcess = $this->startMockServer(
			$this->getSignalingServerAddress() . ' ' . $path
		);

		$this->waitForMockServer();

		register_shutdown_function(function () {
			$this->recordingServerIsStopped();
		});
	}

	/**
	 * @return resource
	 */
	private function startMockServer(string $path) {
		$cmd = 'php -S ' . $path;
		$stdout = tempnam(sys_get_temp_dir(), 'mockserv-stdout-');

		// We need to prefix exec to get the correct process http://php.net/manual/ru/function.proc-get-status.php#93382
		$fullCmd = sprintf('exec %s > %s 2>&1',
			$cmd,
			$stdout
		);

		$pipes = [];
		$env = null;
		$cwd = null;

		$stdin = fopen('php://stdin', 'rb');
		$stdoutf = tempnam(sys_get_temp_dir(), 'MockWebServer.stdout');
		$stderrf = tempnam(sys_get_temp_dir(), 'MockWebServer.stderr');

		$descriptorSpec = [
			0 => $stdin,
			1 => [ 'file', $stdoutf, 'a' ],
			2 => [ 'file', $stderrf, 'a' ],
		];

		$process = proc_open($fullCmd, $descriptorSpec, $pipes, $cwd, $env, [
			'suppress_errors' => false,
			'bypass_shell' => true,
		]);

		if (is_resource($process)) {
			return $process;
		}

		throw new \Exception('Error starting server');
	}

	private function waitForMockServer(): void {
		[$host, $port] = explode(':', $this->getSignalingServerAddress());
		$mockServerIsUp = false;
		for ($i = 0; $i <= 20; $i++) {
			$open = @fsockopen($host, (int)$port);
			if (is_resource($open)) {
				fclose($open);
				$mockServerIsUp = true;
				break;
			}
			usleep(100000);
		}
		if (!$mockServerIsUp) {
			throw new \Exception('Failure to start mock server.');
		}
	}

	/**
	 * Is the Web Server currently running?
	 */
	public function isRunning(): bool {
		if (!is_resource($this->recordingServerProcess)) {
			return false;
		}

		$processStatus = proc_get_status($this->recordingServerProcess);

		if (!$processStatus) {
			return false;
		}

		return $processStatus['running'];
	}

	#[AfterScenario]
	#[Given('/^(recording|signaling) server is stopped$/')]
	public function recordingServerIsStopped(): void {
		if (gettype($this->recordingServerProcess) === 'resource') {
			$this->stop($this->recordingServerProcess);
			$this->recordingServerProcess = null;
		}
		if (gettype($this->signalingServerProcess) === 'resource') {
			$this->stop($this->signalingServerProcess);
			$this->signalingServerProcess = null;
		}
	}

	private function stop($process): void {
		proc_terminate($process);

		$attempts = 0;
		while ($this->isRunning()) {
			if (++$attempts > 1000) {
				throw new \Exception('Failed to stop server.');
			}

			usleep(10000);
		}
	}

	#[When('/^recording server sent started request for "(audio|video)" recording in room "([^"]*)" as "([^"]*)" with (\d+)(?: \((v1)\))?$/')]
	public function recordingServerSentStartedRequestForRecordingInRoomAsWith(string $recordingType, string $identifier, string $user, int $statusCode, string $apiVersion = 'v1'): void {
		$recordingTypes = [
			'video' => 1,
			'audio' => 2,
		];

		$data = [
			'type' => 'started',
			'started' => [
				'token' => FeatureContext::getTokenForIdentifier($identifier),
				'status' => $recordingTypes[$recordingType],
				'actor' => [
					'type' => 'users',
					'id' => $user,
				],
			],
		];

		$this->sendBackendRequestFromRecordingServer($data, $statusCode, $apiVersion);
	}

	#[When('/^recording server sent stopped request for recording in room "([^"]*)" with (\d+)(?: \((v1)\))?$/')]
	public function recordingServerSentStoppedRequestForRecordingInRoomWith(string $identifier, int $statusCode, string $apiVersion = 'v1'): void {
		$this->recordingServerSentStoppedRequestForRecordingInRoomAsWith($identifier, null, $statusCode, $apiVersion);
	}

	#[When('/^recording server sent stopped request for recording in room "([^"]*)" as "([^"]*)" with (\d+)(?: \((v1)\))?$/')]
	public function recordingServerSentStoppedRequestForRecordingInRoomAsWith(string $identifier, ?string $user, int $statusCode, string $apiVersion = 'v1'): void {
		$data = [
			'type' => 'stopped',
			'stopped' => [
				'token' => FeatureContext::getTokenForIdentifier($identifier),
			],
		];

		if ($user !== null) {
			$data['stopped']['actor'] = [
				'type' => 'users',
				'id' => $user,
			];
		}

		$this->sendBackendRequestFromRecordingServer($data, $statusCode, $apiVersion);
	}

	#[When('/^recording server sent failed request for recording in room "([^"]*)" with (\d+)(?: \((v1)\))?$/')]
	public function recordingServerSentFailedRequestForRecordingInRoomWith(string $identifier, int $statusCode, string $apiVersion = 'v1'): void {
		$data = [
			'type' => 'failed',
			'failed' => [
				'token' => FeatureContext::getTokenForIdentifier($identifier),
			],
		];

		$this->sendBackendRequestFromRecordingServer($data, $statusCode, $apiVersion);
	}

	private function sendBackendRequestFromRecordingServer(array $data, int $statusCode, string $apiVersion = 'v1'): void {
		$body = json_encode($data);

		$random = md5((string)rand());
		$checksum = hash_hmac('sha256', $random . $body, 'the recording secret');

		$headers = [
			'Backend-Url' => $this->baseUrl . 'ocs/v2.php/apps/spreed/api/' . $apiVersion . '/recording/backend',
			'Talk-Recording-Random' => $random,
			'Talk-Recording-Checksum' => $checksum,
		];

		$this->sendRequestFullUrl('POST', 'http://' . $this->getRecordingServerAddress() . '/fake/send-backend-request', $body, $headers);
		$this->assertStatusCode($this->response, $statusCode);
	}

	#[Then('/^(recording|signaling) server received the following requests$/')]
	public function fakeServerReceivedTheFollowingRequests(string $server, ?TableNode $formData = null): void {
		if ($server === 'recording') {
			$requests = $this->getRecordingServerReceivedRequests();
		} else {
			$requests = $this->getSignalingServerReceivedRequests();
		}

		if ($formData === null) {
			Assert::assertEmpty($requests);
			return;
		}

		if ($requests === null) {
			Assert::fail('No received requests');
			return;
		}

		$count = count($formData->getHash());
		Assert::assertCount($count, $requests, 'Request count does not match' . "\n" . json_encode($requests));

		$requests = array_map(static function (array $actual) {
			$actualDataJson = json_decode($actual['data'], true);
			$write = false;

			// Fix sorting of postgres
			if (isset($actualDataJson['update']['userids'])) {
				sort($actualDataJson['update']['userids']);
				$write = true;
			}
			if (isset($actualDataJson['participants']['users'])) {
				usort($actualDataJson['participants']['users'], static fn (array $u1, array $u2) => $u1['userId'] <=> $u2['userId']);
				$write = true;
			}

			if ($write) {
				$actual['data'] = json_encode($actualDataJson);
			}

			return $actual;
		}, $requests);

		$expected = array_map(static function (array $request, array $actual) {
			$identifier = $request['token'];
			$request['token'] = FeatureContext::getTokenForIdentifier($identifier);

			$matched = preg_match('/ROOM\(([^)]+)\)/', $request['data'], $matches);
			if ($matched) {
				$request['data'] = str_replace(
					'ROOM(' . $matches[1] . ')',
					FeatureContext::getTokenForIdentifier($matches[1]),
					$request['data']
				);
			}

			$matched = preg_match('/PHONE\((\+\d+)\)/', $request['data'], $matches);
			if ($matched) {
				$request['data'] = str_replace(
					'PHONE(' . $matches[1] . ')',
					FeatureContext::getActorIdForPhoneNumber($matches[1]),
					$request['data']
				);
			}

			$matched = preg_match('/PHONEATTENDEE\((\+\d+)\)/', $request['data'], $matches);
			if ($matched) {
				$request['data'] = str_replace(
					'PHONEATTENDEE(' . $matches[1] . ')',
					(string)FeatureContext::getAttendeeIdForPhoneNumber($identifier, $matches[1]),
					$request['data']
				);
			}

			$matched = preg_match('/SESSION\(([^)]+)\)/', $request['data'], $matches);
			if ($matched) {
				$request['data'] = str_replace(
					'SESSION(' . $matches[1] . ')',
					str_replace('/', '\/', FeatureContext::getSessionIdForUser($matches[1])),
					$request['data']
				);
			}

			$matched = preg_match('/"lastPing":LAST_PING\(\)/', $request['data'], $matches);
			if ($matched) {
				$matched = preg_match('/"lastPing":(\d+)/', $actual['data'], $matches);
				if ($matched) {
					$request['data'] = str_replace(
						'"lastPing":LAST_PING()',
						$matches[0],
						$request['data']
					);
				}
			}

			$matched = preg_match('/"active-since":\{"date":"ACTIVE_SINCE\(\)","timezone_type":3,"timezone":"UTC"}/', $request['data'], $matches);
			if ($matched) {
				$matched = preg_match('/"active-since":\{"date":"([\d\- :.]+)","timezone_type":3,"timezone":"UTC"}/', $actual['data'], $matches);
				if ($matched) {
					$request['data'] = str_replace(
						'ACTIVE_SINCE()',
						$matches[1],
						$request['data']
					);
				}
			}

			return $request;
		}, $formData->getHash(), $requests);

		Assert::assertEquals($expected, $requests);
	}

	#[Then('/^signaling server will respond with$/')]
	public function nextSignalingServerResponseIs(TableNode $formData): void {
		$data = $formData->getRowsHash();
		$nextResponseFile = sys_get_temp_dir() . '/fake-nextcloud-talk-signaling-response';
		file_put_contents($nextResponseFile, $data['response']);
	}

	#[Then('/^reset (recording|signaling) server requests$/')]
	public function resetSignalingServerRequests(string $server): void {
		if ($server === 'recording') {
			$this->getRecordingServerReceivedRequests();
		} else {
			$this->getSignalingServerReceivedRequests();
		}
	}

	private function getRecordingServerReceivedRequests(): ?array {
		$url = 'http://' . $this->getRecordingServerAddress() . '/fake/requests';
		$client = new Client();
		$response = $client->get($url);

		return json_decode($response->getBody()->getContents(), true);
	}

	private function getSignalingServerReceivedRequests(): ?array {
		$url = 'http://' . $this->getSignalingServerAddress() . '/fake/requests';
		$client = new Client();
		$response = $client->get($url);

		return json_decode($response->getBody()->getContents(), true);
	}
}
