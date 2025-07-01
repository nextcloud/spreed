<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

class SharingContext implements Context {
	private string $baseUrl;
	private string $currentServer;
	private ?ResponseInterface $response = null;
	private ?string $responseBody = null;
	private string $currentUser = '';
	private array $adminUser;
	private string $regularUserPassword;
	private ?\SimpleXMLElement $lastCreatedShareData = null;

	public function __construct(string $baseUrl, array $admin, string $regularUserPassword) {
		$this->baseUrl = $baseUrl;
		$this->adminUser = $admin;
		$this->regularUserPassword = $regularUserPassword;

		// in case of ci deployment we take the server url from the environment
		$testServerUrl = getenv('TEST_SERVER_URL');
		if ($testServerUrl !== false) {
			$this->baseUrl = $testServerUrl;
		}
	}

	public function setCurrentServer(string $currentServer, string $baseUrl): void {
		$this->currentServer = $currentServer;
		$this->baseUrl = $baseUrl;
	}

	#[Given('user :user creates folder :destination')]
	public function userCreatesFolder(string $user, string $destination): void {
		$this->currentUser = $user;

		$url = "/$user/$destination/";

		$this->sendingToDav('MKCOL', $url);

		$this->theHTTPStatusCodeShouldBe(201);
	}

	#[Given('user :user moves file :source to :destination')]
	public function userMovesFileTo(string $user, string $source, string $destination): void {
		$this->currentUser = $user;

		$url = "/$user/$source";

		$headers = [];
		$headers['Destination'] = $this->baseUrl . "remote.php/dav/files/$user/" . $destination;

		$this->sendingToDav('MOVE', $url, $headers);
	}

	#[Given('user :user moves file :source to :destination with :statusCode')]
	public function userMovesFileToWith(string $user, string $source, string $destination, int $statusCode): void {
		$this->userMovesFileTo($user, $source, $destination);
		$this->theHTTPStatusCodeShouldBe($statusCode);
	}

	#[Given('user :user deletes file :file')]
	public function userDeletesFile(string $user, string $file): void {
		$this->currentUser = $user;

		$url = "/$user/$file";

		$this->sendingToDav('DELETE', $url);

		$this->theHTTPStatusCodeShouldBe(204);
	}

	#[When('user :user shares :path with user :sharee')]
	public function userSharesWithUser(string $user, string $path, string $sharee, ?TableNode $body = null): void {
		$this->userSharesWith($user, $path, 0 /*IShare::TYPE_USER*/, $sharee, $body);
	}

	#[When('user :user shares :path with user :sharee with OCS :statusCode')]
	public function userSharesWithUserWithOcs(string $user, string $path, string $sharee, int $statusCode): void {
		$this->userSharesWithUser($user, $path, $sharee);
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	#[When('user :user shares :path with group :sharee')]
	public function userSharesWithGroup(string $user, string $path, string $sharee, ?TableNode $body = null): void {
		$this->userSharesWith($user, $path, 1 /*IShare::TYPE_GROUP*/, $sharee, $body);
	}

	#[When('user :user shares :path with group :sharee with OCS :statusCode')]
	public function userSharesWithGroupWithOcs(string $user, string $path, string $sharee, int $statusCode): void {
		$this->userSharesWithGroup($user, $path, $sharee);
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	#[When('user :user shares :path with team :sharee')]
	public function userSharesWithTeam(string $user, string $path, string $sharee, ?TableNode $body = null): void {
		$this->userSharesWith($user, $path, 7 /*IShare::TYPE_CIRCLE*/, $sharee, $body);
	}

	#[When('user :user shares :path with team :sharee with OCS :statusCode')]
	public function userSharesWithTeamWithOcs(string $user, string $path, string $sharee, int $statusCode): void {
		$this->userSharesWithTeam($user, $path, FeatureContext::getTeamIdForLabel($this->currentServer, $sharee));
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	#[When('user :user shares :path with room :room')]
	public function userSharesWithRoom(string $user, string $path, string $room, ?TableNode $body = null): void {
		$this->userSharesWith($user, $path, 10 /*IShare::TYPE_ROOM*/, FeatureContext::getTokenForIdentifier($room), $body);
	}

	#[When('user :user shares :path with :amount rooms')]
	public function userSharesWithManyRooms(string $user, string $path, int $amount, ?TableNode $body = null): void {
		for ($i = 1; $i <= $amount; $i++) {
			$identifier = 'room' . $i;
			$this->userSharesWith($user, $path, 10 /*IShare::TYPE_ROOM*/, FeatureContext::getTokenForIdentifier($identifier), $body);
		}
	}

	#[When('user :user shares :path with room :room with OCS :statusCode')]
	public function userSharesWithRoomWithOcs(string $user, string $path, string $room, int $statusCode): void {
		$this->userSharesWithRoom($user, $path, $room);
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	#[When('user :user shares :path by link')]
	public function userSharesByLink(string $user, string $path, ?TableNode $body = null): void {
		$this->userSharesWith($user, $path, 3 /*IShare::TYPE_LINK*/, '', $body);
	}

	#[When('user :user shares :path by link with OCS :statusCode')]
	public function userSharesByLinkWithOcs(string $user, string $path, int $statusCode, ?TableNode $body = null): void {
		$this->userSharesByLink($user, $path, $body);
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	#[When('user :user updates last share with')]
	public function userUpdatesLastShareWith(string $user, TableNode $body): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares/' . $this->getLastShareId();

		$this->sendingTo('PUT', $url, $body);
	}

	#[When('user :user deletes last share')]
	public function userDeletesLastShare(string $user): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares/' . $this->getLastShareId();

		$this->sendingTo('DELETE', $url);
	}

	#[When('user :user deletes last share with OCS :statusCode')]
	public function userDeletesLastShareWithOcs(string $user, int $statusCode): void {
		$this->userDeletesLastShare($user);
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	#[When('user :user restores last share')]
	public function userRestoresLastShareWithOcs(string $user): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/deletedshares/ocRoomShare:' . $this->getLastShareId();

		$this->sendingTo('POST', $url);
	}

	#[When('user :user gets last share')]
	public function userGetsLastShare(string $user): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares/' . $this->getLastShareId();

		$this->sendingTo('GET', $url);
	}

	#[When('user :user accepts last share')]
	public function userAcceptsLastShare(string $user): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares/pending/' . $this->getLastShareId();

		$this->sendingTo('POST', $url);

		$this->theHTTPStatusCodeShouldBe(200);
		$this->theOCSStatusCodeShouldBe(100);
	}

	#[When('user :user gets all shares')]
	public function userGetsAllShares(string $user): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares';

		$this->sendingTo('GET', $url);
	}

	#[When('user :user gets all shares and reshares')]
	public function userGetsAllSharesAndReshares(string $user): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?reshares=true';

		$this->sendingTo('GET', $url);
	}

	#[When('user :user gets all shares for :path')]
	public function userGetsAllSharesFor(string $user, string $path): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?path=' . $path;

		$this->sendingTo('GET', $url);
	}

	#[When('user :user gets all shares and reshares for :path')]
	public function userGetsAllSharesAndResharesFor(string $user, string $path): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?reshares=true&path=' . $path;

		$this->sendingTo('GET', $url);
	}

	#[When('user :user gets all shares for :path and its subfiles')]
	public function userGetsAllSharesForAndItsSubfiles(string $user, string $path): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?subfiles=true&path=' . $path;

		$this->sendingTo('GET', $url);
	}

	#[When('user :user gets all received shares')]
	public function userGetsAllReceivedShares(string $user): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?shared_with_me=true';

		$this->sendingTo('GET', $url);
	}

	#[When('user :user gets all received shares for :path')]
	public function userGetsAllReceivedSharesFor(string $user, string $path): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?shared_with_me=true&path=' . $path;

		$this->sendingTo('GET', $url);
	}

	#[When('user :user gets deleted shares')]
	public function userGetsDeletedShares(string $user): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/deletedshares';

		$this->sendingTo('GET', $url);
	}

	#[When('/^user "([^"]*)" gets sharees for$/')]
	public function userGetsShareesFor(string $user, TableNode $body): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/sharees';

		$parameters = [];
		$parameters[] = 'shareType=10'; // IShare::TYPE_ROOM,
		$parameters[] = 'itemType=file';
		foreach ($body->getRowsHash() as $key => $value) {
			$parameters[] = $key . '=' . $value;
		}

		$url .= '?' . implode('&', $parameters);

		$this->sendingTo('GET', $url);

		\PHPUnit\Framework\Assert::assertEquals(200, $this->response->getStatusCode());
	}

	#[When('user :user gets the DAV properties for :path')]
	public function userGetsTheDavPropertiesFor(string $user, string $path): void {
		$this->currentUser = $user;

		$url = "/$user/$path";

		$this->sendingToDav('PROPFIND', $url);

		$this->theHTTPStatusCodeShouldBe(207);
	}

	#[When('user :user gets the share-type DAV property for :path')]
	public function userGetsTheShareTypeDavPropertyFor(string $user, string $path): void {
		$this->currentUser = $user;

		$url = "/$user/$path";

		$headers = null;

		$body = '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">'
				. '	<d:prop>'
				. '		<oc:share-types/>'
				. '	</d:prop>'
				. '</d:propfind>';

		$this->sendingToDav('PROPFIND', $url, $headers, $body);

		$this->theHTTPStatusCodeShouldBe(207);
	}

	#[When('user :user gets recent files')]
	public function userGetsRecentFiles(string $user): void {
		// Recents endpoint is not an OCS endpoint, so a request token must be
		// provided.
		[$requestToken, $cookieJar] = $this->loggingInUsingWebAs($user);

		$url = '/index.php/apps/files/api/v1/recent';

		$this->sendingToWithRequestToken('GET', $url, $requestToken, $cookieJar);
	}

	#[When('transfering ownership from :user1 to :user2')]
	public function transferingOwnershipFromTo(string $user1, string $user2): void {
		$args = ['files:transfer-ownership', $user1, $user2];

		$args = array_map(function ($arg) {
			return escapeshellarg($arg);
		}, $args);
		$args[] = '--no-ansi';
		$args = implode(' ', $args);

		$descriptor = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		];
		$process = proc_open('php console.php ' . $args, $descriptor, $pipes, '../../../../');
		$lastStdOut = stream_get_contents($pipes[1]);
		$lastStdErr = stream_get_contents($pipes[2]);
		$lastCode = proc_close($process);

		// Clean opcode cache
		$client = new GuzzleHttp\Client();
		$client->request('GET', $this->baseUrl . '/apps/testing/clean_opcode_cache.php');

		\PHPUnit\Framework\Assert::assertEquals(0, $lastCode);
	}

	#[Then('the OCS status code should be :statusCode')]
	public function theOCSStatusCodeShouldBe(int $statusCode): void {
		$meta = $this->getXmlResponse()->meta[0];

		\PHPUnit\Framework\Assert::assertEquals($statusCode, (int)$meta->statuscode, 'Response message: ' . (string)$meta->message);
	}

	#[Then('the HTTP status code should be :statusCode')]
	public function theHTTPStatusCodeShouldBe(int $statusCode): void {
		\PHPUnit\Framework\Assert::assertEquals($statusCode, $this->response->getStatusCode());
	}

	#[Then('the list of returned shares has :count shares')]
	public function theListOfReturnedSharesHasShares(int $count): void {
		$this->theHTTPStatusCodeShouldBe(200);
		$this->theOCSStatusCodeShouldBe(100);

		$returnedShares = $this->getXmlResponse()->data[0];

		\PHPUnit\Framework\Assert::assertEquals($count, count($returnedShares->element));
	}

	#[Then('share is returned with')]
	public function shareIsReturnedWith(TableNode $body): void {
		$this->shareXIsReturnedWith(0, $body);
	}

	#[Then('share :number is returned with')]
	public function shareXIsReturnedWith(int $number, TableNode $body): void {
		$this->theHTTPStatusCodeShouldBe(200);
		$this->theOCSStatusCodeShouldBe(100);

		if (!($body instanceof TableNode)) {
			return;
		}

		$returnedShare = $this->getXmlResponse()->data[0];
		if ($returnedShare->element) {
			$returnedShare = (array)$returnedShare;
			$returnedShare = $returnedShare['element'];
			if (is_array($returnedShare)) {
				usort($returnedShare, static function ($share1, $share2) {
					return (int)$share1->id - (int)$share2->id;
				});
			}

			$returnedShare = $returnedShare[$number];
		}

		$defaultExpectedFields = [
			'id' => 'A_NUMBER',
			'share_type' => '10', // IShare::TYPE_ROOM,
			'permissions' => '19',
			'stime' => 'A_NUMBER',
			'parent' => '',
			'expiration' => '',
			'token' => '',
			'storage' => 'A_NUMBER',
			'item_source' => 'A_NUMBER',
			'file_source' => 'A_NUMBER',
			'file_parent' => 'A_NUMBER',
			'mail_send' => '0',
		];

		$fields = $body->getRowsHash();
		if (isset($fields['share_type']) && ($fields['share_type'] === '10' || $fields['share_type'] === '11')) {
			$defaultExpectedFields['share_with_link'] = 'URL';
		}

		if (isset($fields['share_type']) && ($fields['share_type'] === '0' || $fields['share_type'] === '1')) {
			/**
			 * This field was changed so often in the server,
			 * that we simply don't care any more. We want to test Talk,
			 * not normal user shares.
			 * Refs:
			 * - https://github.com/nextcloud/server/pull/49898
			 * - https://github.com/nextcloud/server/pull/48381
			 * - https://github.com/nextcloud/spreed/pull/13632
			 */
			unset($defaultExpectedFields['mail_send']);
		}

		$expectedFields = array_merge($defaultExpectedFields, $fields);

		if (!array_key_exists('uid_file_owner', $expectedFields)
				&& array_key_exists('uid_owner', $expectedFields)) {
			$expectedFields['uid_file_owner'] = $expectedFields['uid_owner'];
		}
		if (!array_key_exists('displayname_file_owner', $expectedFields)
				&& array_key_exists('displayname_owner', $expectedFields)) {
			$expectedFields['displayname_file_owner'] = $expectedFields['displayname_owner'];
		}

		if (array_key_exists('share_type', $expectedFields)
				&& $expectedFields['share_type'] == 10 /* IShare::TYPE_ROOM */
				&& array_key_exists('share_with', $expectedFields)) {
			if ($expectedFields['share_with'] === 'private_conversation') {
				$expectedFields['share_with'] = 'REGEXP /^private_conversation_[0-9a-f]{6}$/';
				$expectedFields['share_with_link'] = '';
			} else {
				$expectedFields['share_with'] = FeatureContext::getTokenForIdentifier($expectedFields['share_with']);
			}
		}

		if (array_key_exists('share_with_link', $expectedFields)
			&& $expectedFields['share_with_link'] === 'URL') {
			if (array_key_exists('share_with', $expectedFields)) {
				$expectedFields['share_with_link'] = $this->baseUrl . 'index.php/call/' . $expectedFields['share_with'];
			} else {
				$expectedFields['share_with_link'] = 'REGEXP ' . '/\/call\//';
			}
		}

		foreach ($expectedFields as $field => $value) {
			$share = json_decode(json_encode($returnedShare), true);
			if (isset($share[$field]) && empty($share[$field]) && is_array($share[$field])) {
				// Fix XML parsing fails
				$share[$field] = '';
			}

			if (in_array($field, ['path', 'storage_id'], true)
				&& preg_match('/Transferred from [^ ]* on (\d{4}-\d{2}-\d{2} \d{2}-\d{2}-\d{2})/', $share[$field], $matches)) {
				// We have to replace strings like
				// "Transferred from participant1-displayname on 2025-06-30 12-31-32"
				// with something neutral that works in tests
				$share[$field] = str_replace($matches[1], '{{DATE AND TIME}}', $share[$field]);
			}

			$this->assertFieldIsInReturnedShare($field, $value, $share);
		}
	}

	/**
	 * Each sharee is specified as "| room name | room test identifier |"; the
	 * name is checked against the returned "label" value, and the room test
	 * identifier is used to get the room token, which is checked against the
	 * returned "shareWith" value. The returned "shareType" value is expected to
	 * always be "IShare::TYPE_ROOM", so there is no need to specify it.
	 */
	#[Then('/^"([^"]*)" sharees returned (are|is empty)$/')]
	public function shareesReturnedAreIsEmpty(string $shareeType, string $isEmpty, ?TableNode $shareesList = null): void {
		if ($isEmpty !== 'is empty') {
			$sharees = [];
			foreach ($shareesList->getRows() as $row) {
				$expectedSharee = [$row[0]];
				$expectedSharee[] = 10; // IShare::TYPE_ROOM
				$expectedSharee[] = FeatureContext::getTokenForIdentifier($row[1]);
				$sharees[] = $expectedSharee;
			}
			$respondedArray = $this->getArrayOfShareesResponded($this->getXmlResponse(), $shareeType);
			usort($sharees, function ($a, $b) {
				return $a[2] <=> $b[2]; // Sort by token
			});
			usort($respondedArray, function ($a, $b) {
				return $a[2] <=> $b[2]; // Sort by token
			});
			\PHPUnit\Framework\Assert::assertEquals($sharees, $respondedArray);
		} else {
			$respondedArray = $this->getArrayOfShareesResponded($this->getXmlResponse(), $shareeType);
			\PHPUnit\Framework\Assert::assertEmpty($respondedArray);
		}
	}

	#[Then('the list of returned files for :user is')]
	public function theListOfReturnedFilesForIs(string $user, ?TableNode $table = null): void {
		$xmlResponse = $this->getXmlResponse();
		$xmlResponse->registerXPathNamespace('d', 'DAV:');

		$hrefs = [];
		foreach ($xmlResponse->xpath('//d:response/d:href') as $href) {
			$hrefs[] = (string)$href;
		}

		$expectedHrefs = [];
		if ($table !== null) {
			foreach ($table->getRows() as $row) {
				$expectedHrefs[] = '/remote.php/dav/files/' . $user . (string)$row[0];
			}
		}

		\PHPUnit\Framework\Assert::assertEquals($expectedHrefs, $hrefs);
	}

	#[Then('the response contains a share-types DAV property with')]
	public function theResponseContainsAShareTypesDavPropertyWith(?TableNode $table = null): void {
		$xmlResponse = $this->getXmlResponse();
		$xmlResponse->registerXPathNamespace('oc', 'http://owncloud.org/ns');

		$shareTypes = [];
		foreach ($xmlResponse->xpath('//oc:share-types/oc:share-type') as $shareType) {
			$shareTypes[] = (int)$shareType;
		}

		$expectedShareTypes = [];
		if ($table !== null) {
			foreach ($table->getRows() as $row) {
				$expectedShareTypes[] = (int)$row[0];
			}
		}

		\PHPUnit\Framework\Assert::assertEquals($expectedShareTypes, $shareTypes);
	}

	#[Then('the response contains a share-types file property for :path with')]
	public function theResponseContainsAShareTypesFilesPropertyForWith(string $path, ?TableNode $table = null): void {
		if ($this->responseBody === null) {
			$this->responseBody = $this->response->getBody()->getContents();
		}
		$response = json_decode($this->responseBody);

		$fileForPath = array_filter($response->files, function ($file) use ($path) {
			$filePath = $file->path . (substr($file->path, -1) === '/'? '': '/');
			return ($filePath . $file->name) === $path;
		});

		if (empty($fileForPath)) {
			\PHPUnit\Framework\Assert::fail("$path not found in the response");
		}

		$fileForPath = array_shift($fileForPath);

		$shareTypes = [];
		if (property_exists($fileForPath, 'shareTypes')) {
			foreach ($fileForPath->shareTypes as $shareType) {
				$shareTypes[] = (int)$shareType;
			}
		}

		$expectedShareTypes = [];
		if ($table !== null) {
			foreach ($table->getRows() as $row) {
				$expectedShareTypes[] = (int)$row[0];
			}
		}

		\PHPUnit\Framework\Assert::assertEquals($expectedShareTypes, $shareTypes);
	}

	private function userSharesWith(string $user, string $path, int $shareType, string $shareWith, ?TableNode $body = null): void {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares';

		$parameters = [];
		$parameters[] = 'path=' . $path;
		$parameters[] = 'shareType=' . $shareType;
		$parameters[] = 'shareWith=' . $shareWith;

		$talkMetaData = [];

		if ($body instanceof TableNode) {
			foreach ($body->getRowsHash() as $key => $value) {
				if ($key === 'expireDate' && $value !== 'invalid date') {
					$value = date('Y-m-d', strtotime($value));
				}
				if ($key === 'talkMetaData.replyTo') {
					$value = FeatureContext::getMessageIdForText($value);
				}
				if (str_starts_with($key, 'talkMetaData.')) {
					$talkMetaData[substr($key, 13)] = $value;
				} else {
					$parameters[] = $key . '=' . $value;
				}
			}
		}

		if (!empty($talkMetaData)) {
			$parameters[] = 'talkMetaData=' . json_encode($talkMetaData);
		}

		$url .= '?' . implode('&', $parameters);

		$this->sendingTo('POST', $url);

		$this->lastCreatedShareData = $this->getXmlResponse();
	}

	private function sendingTo(string $verb, string $url, ?TableNode $body = null): void {
		$fullUrl = $this->baseUrl . 'ocs/v1.php' . $url;
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$this->currentUser, $this->regularUserPassword];
		}
		$options['headers'] = [
			'OCS_APIREQUEST' => 'true'
		];
		if ($body instanceof TableNode) {
			$fd = $body->getRowsHash();
			if (array_key_exists('expireDate', $fd)) {
				$fd['expireDate'] = date('Y-m-d', strtotime($fd['expireDate']));
			}
			$options['form_params'] = $fd;
		}

		try {
			$this->response = $client->request($verb, $fullUrl, $options);
			$this->responseBody = null;
		} catch (GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
			$this->responseBody = null;
		}
	}

	private function sendingToDav(string $verb, string $url, ?array $headers = null, ?string $body = null): void {
		$fullUrl = $this->baseUrl . 'remote.php/dav/files' . $url;
		$client = new Client();
		$options = [];
		if ($this->currentUser === 'admin') {
			$options['auth'] = $this->adminUser;
		} else {
			$options['auth'] = [$this->currentUser, $this->regularUserPassword];
		}
		$options['headers'] = [
			'OCS_APIREQUEST' => 'true'
		];
		if ($headers !== null) {
			$options['headers'] = array_merge($options['headers'], $headers);
		}
		if ($body !== null) {
			$options['body'] = $body;
		}

		try {
			$this->response = $client->request($verb, $fullUrl, $options);
			$this->responseBody = null;
		} catch (GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
			$this->responseBody = null;
		}
	}

	private function sendingToWithRequestToken(string $verb, string $url, string $requestToken, CookieJar $cookieJar): void {
		$fullUrl = $this->baseUrl . $url;

		$client = new Client();
		try {
			$this->response = $client->request(
				$verb,
				$fullUrl,
				[
					'cookies' => $cookieJar,
					'headers' => [
						'requesttoken' => $requestToken
					]
				]
			);
			$this->responseBody = null;
		} catch (GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
			$this->responseBody = null;
		}
	}

	private function extractRequestTokenFromResponse(ResponseInterface $response): string {
		return substr(preg_replace('/(.*)data-requesttoken="(.*)">(.*)/sm', '\2', $response->getBody()->getContents()), 0, 89);
	}

	private function loggingInUsingWebAs(string $user): array {
		$loginUrl = $this->baseUrl . '/login';

		$cookieJar = new CookieJar();

		// Request a new session and extract CSRF token
		$client = new Client();
		$response = $client->get(
			$loginUrl,
			[
				'cookies' => $cookieJar,
			]
		);
		$requestToken = $this->extractRequestTokenFromResponse($response);

		// Login and extract new token
		$password = ($user === 'admin') ? $this->adminUser[1] : $this->regularUserPassword;
		$client = new Client();
		$response = $client->post(
			$loginUrl,
			[
				'form_params' => [
					'user' => $user,
					'password' => $password,
					'requesttoken' => $requestToken,
				],
				'cookies' => $cookieJar,
				'headers' => [
					'Origin' => $this->baseUrl,
				],
			]
		);
		$requestToken = $this->extractRequestTokenFromResponse($response);

		return [$requestToken, $cookieJar];
	}

	public function getLastShareToken(): string {
		return (string)$this->lastCreatedShareData->data[0]->token;
	}

	private function getLastShareId(): string {
		return (string)$this->lastCreatedShareData->data[0]->id;
	}

	private function getXmlResponse(): \SimpleXMLElement|false {
		if ($this->responseBody === null) {
			$this->responseBody = $this->response->getBody()->getContents();
		}
		return simplexml_load_string($this->responseBody);
	}

	private function assertFieldIsInReturnedShare(string $field, string $contentExpected, array $returnedShare): void {
		if ($contentExpected === 'IGNORE') {
			return;
		}

		if (!array_key_exists($field, $returnedShare)) {
			\PHPUnit\Framework\Assert::fail("$field was not found in response");
		}

		if ($field === 'expiration' && !empty($contentExpected)) {
			$contentExpected = date('Y-m-d', strtotime($contentExpected)) . ' 00:00:00';
		}

		if ($contentExpected === 'A_NUMBER') {
			\PHPUnit\Framework\Assert::assertTrue(is_numeric((string)$returnedShare[$field]), "Field '$field' is not a number: " . $returnedShare[$field]);
		} elseif ($contentExpected === 'A_TOKEN') {
			// A token is composed by 15 characters from
			// ISecureRandom::CHAR_HUMAN_READABLE.
			\PHPUnit\Framework\Assert::assertMatchesRegularExpression('/^[abcdefgijkmnopqrstwxyzABCDEFGHJKLMNPQRSTWXYZ23456789]{15}$/', (string)$returnedShare[$field], "Field '$field' is not a token");
		} elseif (strpos($contentExpected, 'REGEXP ') === 0) {
			\PHPUnit\Framework\Assert::assertMatchesRegularExpression(substr($contentExpected, strlen('REGEXP ')), (string)$returnedShare[$field], "Field '$field' does not match");
		} else {
			\PHPUnit\Framework\Assert::assertEquals($contentExpected, (string)$returnedShare[$field], "Field '$field' does not match");
		}
	}

	private function getArrayOfShareesResponded(\SimpleXMLElement $response, string $shareeType): array {
		$elements = $response->data;
		$elements = json_decode(json_encode($elements), true);
		if (strpos($shareeType, 'exact ') === 0) {
			$elements = $elements['exact'];
			$shareeType = substr($shareeType, 6);
		}

		// "simplexml_load_string" creates a SimpleXMLElement object for each
		// XML element with child elements. In turn, each child is indexed by
		// its tag in the SimpleXMLElement object. However, when there are
		// several child XML elements with the same tag, an array with all the
		// children with the same tag is indexed instead. Therefore, when the
		// XML contains
		// <rooms>
		//   <element>
		//     <label>...</label>
		//     <value>...</value>
		//   </element>
		// </rooms>
		// the "$elements[$shareeType]" variable contains an "element" key which
		// in turn contains "label" and "value" keys, but when the XML contains
		// <rooms>
		//   <element>
		//     <label>...</label>
		//     <value>...</value>
		//   </element>
		//   <element>
		//     <label>...</label>
		//     <value>...</value>
		//   </element>
		// </rooms>
		// the "$elements[$shareeType]" variable contains an "element" key which
		// in turn contains "0" and "1" keys, and in turn each one contains
		// "label" and "value" keys.
		$elements = $elements[$shareeType];
		if (array_key_exists('element', $elements) && is_int(array_keys($elements['element'])[0])) {
			$elements = $elements['element'];
		}

		$sharees = [];
		foreach ($elements as $element) {
			$sharees[] = [$element['label'], $element['value']['shareType'], $element['value']['shareWith']];
		}
		return $sharees;
	}
}
