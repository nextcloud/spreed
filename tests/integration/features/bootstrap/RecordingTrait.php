<?php
/**
 * @copyright Copyright (c) 2023 Daniel Calviño Sánchez <danxuliu@gmail.com>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 */
use Behat\Gherkin\Node\TableNode;
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
	private string $recordingServerPid = '';
	private string $signalingServerPid = '';

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

	/**
	 * @Given /^recording server is started$/
	 */
	public function recordingServerIsStarted() {
		if ($this->recordingServerPid !== '') {
			return;
		}

		// "the secret" is hardcoded in the fake recording server.
		$this->setAppConfig('spreed', new TableNode([['recording_servers', json_encode([
			'servers' => [
				[
					'server' => 'http://' . $this->getRecordingServerAddress()
				]
			],
			'secret' => 'the secret'
		])]]));
		$this->setAppConfig('spreed', new TableNode([['signaling_servers', json_encode([
			'servers' => [
				[
					'server' => 'http://' . $this->getSignalingServerAddress()
				]
			],
			'secret' => 'the secret'
		])]]));

		$path = 'features/bootstrap/FakeRecordingServer.php';
		$this->recordingServerPid = exec(
			'php -S ' . $this->getRecordingServerAddress() . ' ' . $path . ' >/dev/null & echo $!'
		);

		$path = 'features/bootstrap/FakeSignalingServer.php';
		$this->signalingServerPid = exec(
			'php -S ' . $this->getSignalingServerAddress() . ' ' . $path . ' >/dev/null & echo $!'
		);

		$this->waitForMockServer();

		register_shutdown_function(function () {
			if ($this->recordingServerPid !== '') {
				exec('kill ' . $this->recordingServerPid);
				exec('kill ' . $this->signalingServerPid);
			}
		});
	}

	private function waitForMockServer(): void {
		[$host, $port] = explode(':', $this->getSignalingServerAddress());
		$mockServerIsUp = false;
		for ($i = 0; $i <= 20; $i++) {
			usleep(100000);

			$open = @fsockopen($host, $port);
			if (is_resource($open)) {
				fclose($open);
				$mockServerIsUp = true;
				break;
			}
		}
		if (!$mockServerIsUp) {
			throw new \Exception('Failure to start mock server.');
		}
	}

	/**
	 * @AfterScenario
	 *
	 * @When /^recording server is stopped$/
	 */
	public function recordingServerIsStopped() {
		if ($this->recordingServerPid === '') {
			return;
		}

		exec('kill ' . $this->recordingServerPid);
		exec('kill ' . $this->signalingServerPid);

		$this->recordingServerPid = '';
		$this->signalingServerPid = '';
	}

	/**
	 * @When /^recording server sent started request for "(audio|video)" recording in room "([^"]*)" as "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function recordingServerSentStartedRequestForRecordingInRoomAsWith(string $recordingType, string $identifier, string $user, int $statusCode, string $apiVersion = 'v1') {
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

	/**
	 * @When /^recording server sent stopped request for recording in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function recordingServerSentStoppedRequestForRecordingInRoomWith(string $identifier, int $statusCode, string $apiVersion = 'v1') {
		$this->recordingServerSentStoppedRequestForRecordingInRoomAsWith($identifier, null, $statusCode, $apiVersion);
	}

	/**
	 * @When /^recording server sent stopped request for recording in room "([^"]*)" as "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function recordingServerSentStoppedRequestForRecordingInRoomAsWith(string $identifier, ?string $user, int $statusCode, string $apiVersion = 'v1') {
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

	/**
	 * @When /^recording server sent failed request for recording in room "([^"]*)" with (\d+)(?: \((v1)\))?$/
	 */
	public function recordingServerSentFailedRequestForRecordingInRoomWith(string $identifier, int $statusCode, string $apiVersion = 'v1') {
		$data = [
			'type' => 'failed',
			'failed' => [
				'token' => FeatureContext::getTokenForIdentifier($identifier),
			],
		];

		$this->sendBackendRequestFromRecordingServer($data, $statusCode, $apiVersion);
	}

	private function sendBackendRequestFromRecordingServer(array $data, int $statusCode, string $apiVersion = 'v1') {
		$body = json_encode($data);

		$random = md5((string) rand());
		$checksum = hash_hmac('sha256', $random . $body, "the secret");

		$headers = [
			'Backend-Url' => $this->baseUrl . 'ocs/v2.php/apps/spreed/api/' . $apiVersion . '/recording/backend',
			'Talk-Recording-Random' => $random,
			'Talk-Recording-Checksum' => $checksum,
		];

		$this->sendRequestFullUrl('POST', 'http://' . $this->getRecordingServerAddress() . '/fake/send-backend-request', $body, $headers);
		$this->assertStatusCode($this->response, $statusCode);
	}

	/**
	 * @Then /^recording server received the following requests$/
	 */
	public function recordingServerReceivedTheFollowingRequests(TableNode $formData = null) {
		$requests = $this->getRecordingServerReceivedRequests();

		if ($formData === null) {
			Assert::assertEmpty($requests);
			return;
		}

		if ($requests === null) {
			Assert::fail('No received requests');
			return;
		}

		$expected = array_map(static function (array $request) {
			$request['token'] = FeatureContext::getTokenForIdentifier($request['token']);
			return $request;
		}, $formData->getHash());

		$count = count($expected);
		Assert::assertCount($count, $requests, 'Request count does not match');

		Assert::assertEquals($expected, $requests);
	}

	private function getRecordingServerReceivedRequests() {
		$url = 'http://' . $this->getRecordingServerAddress() . '/fake/requests';
		$client = new Client();
		$response = $client->get($url);

		return json_decode($response->getBody()->getContents(), true);
	}
}
