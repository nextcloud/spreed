<?php

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Behat\Behat\Context\Context;
use Behat\Gherkin\Node\TableNode;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Psr\Http\Message\ResponseInterface;

class SharingContext implements Context {
	private string $baseUrl;
	private string $currentServer;
	private ?ResponseInterface $response = null;
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

	/**
	 * @param string $currentServer
	 * @param string $baseUrl
	 */
	public function setCurrentServer(string $currentServer, string $baseUrl) {
		$this->currentServer = $currentServer;
		$this->baseUrl = $baseUrl;
	}

	/**
	 * @Given user :user creates folder :destination
	 *
	 * @param string $user
	 * @param string $destination
	 */
	public function userCreatesFolder($user, $destination) {
		$this->currentUser = $user;

		$url = "/$user/$destination/";

		$this->sendingToDav('MKCOL', $url);

		$this->theHTTPStatusCodeShouldBe(201);
	}

	/**
	 * @Given user :user moves file :source to :destination
	 *
	 * @param string $user
	 * @param string $source
	 * @param string $destination
	 */
	public function userMovesFileTo(string $user, string $source, string $destination) {
		$this->currentUser = $user;

		$url = "/$user/$source";

		$headers = [];
		$headers['Destination'] = $this->baseUrl . "remote.php/dav/files/$user/" . $destination;

		$this->sendingToDav('MOVE', $url, $headers);
	}

	/**
	 * @Given user :user moves file :source to :destination with :statusCode
	 *
	 * @param string $user
	 * @param string $source
	 * @param string $destination
	 * @param int statusCode
	 */
	public function userMovesFileToWith(string $user, string $source, string $destination, int $statusCode) {
		$this->userMovesFileTo($user, $source, $destination);
		$this->theHTTPStatusCodeShouldBe($statusCode);
	}

	/**
	 * @Given user :user deletes file :file
	 *
	 * @param string $user
	 * @param string $file
	 */
	public function userDeletesFile($user, $file) {
		$this->currentUser = $user;

		$url = "/$user/$file";

		$this->sendingToDav('DELETE', $url);

		$this->theHTTPStatusCodeShouldBe(204);
	}

	/**
	 * @When user :user shares :path with user :sharee
	 *
	 * @param string $user
	 * @param string $path
	 * @param string $sharee
	 * @param TableNode|null $body
	 */
	public function userSharesWithUser(string $user, string $path, string $sharee, ?TableNode $body = null) {
		$this->userSharesWith($user, $path, 0 /*IShare::TYPE_USER*/, $sharee, $body);
	}

	/**
	 * @When user :user shares :path with user :sharee with OCS :statusCode
	 *
	 * @param string $user
	 * @param string $path
	 * @param string $sharee
	 * @param int $statusCode
	 */
	public function userSharesWithUserWithOcs(string $user, string $path, string $sharee, int $statusCode) {
		$this->userSharesWithUser($user, $path, $sharee);
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	/**
	 * @When user :user shares :path with group :sharee
	 *
	 * @param string $user
	 * @param string $path
	 * @param string $sharee
	 * @param TableNode|null $body
	 */
	public function userSharesWithGroup(string $user, string $path, string $sharee, ?TableNode $body = null) {
		$this->userSharesWith($user, $path, 1 /*IShare::TYPE_GROUP*/, $sharee, $body);
	}

	/**
	 * @When user :user shares :path with group :sharee with OCS :statusCode
	 *
	 * @param string $user
	 * @param string $path
	 * @param string $sharee
	 * @param int $statusCode
	 */
	public function userSharesWithGroupWithOcs(string $user, string $path, string $sharee, int $statusCode) {
		$this->userSharesWithGroup($user, $path, $sharee);
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	/**
	 * @When user :user shares :path with team :sharee
	 *
	 * @param string $user
	 * @param string $path
	 * @param string $sharee
	 * @param TableNode|null $body
	 */
	public function userSharesWithTeam(string $user, string $path, string $sharee, ?TableNode $body = null) {
		$this->userSharesWith($user, $path, 7 /*IShare::TYPE_CIRCLE*/, $sharee, $body);
	}

	/**
	 * @When user :user shares :path with team :sharee with OCS :statusCode
	 *
	 * @param string $user
	 * @param string $path
	 * @param string $sharee
	 * @param int $statusCode
	 */
	public function userSharesWithTeamWithOcs(string $user, string $path, string $sharee, int $statusCode) {
		$this->userSharesWithTeam($user, $path, FeatureContext::getTeamIdForLabel($this->currentServer, $sharee));
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	/**
	 * @When user :user shares :path with room :room
	 *
	 * @param string $user
	 * @param string $path
	 * @param string $room
	 * @param TableNode|null $body
	 */
	public function userSharesWithRoom(string $user, string $path, string $room, ?TableNode $body = null) {
		$this->userSharesWith($user, $path, 10 /*IShare::TYPE_ROOM*/, FeatureContext::getTokenForIdentifier($room), $body);
	}

	/**
	 * @When user :user shares :path with :amount rooms
	 *
	 * @param string $user
	 * @param string $path
	 * @param string $room
	 * @param TableNode|null $body
	 */
	public function userSharesWithManyRooms(string $user, string $path, int $amount, ?TableNode $body = null) {
		for ($i = 1; $i <= $amount; $i++) {
			$identifier = 'room' . $i;
			$this->userSharesWith($user, $path, 10 /*IShare::TYPE_ROOM*/, FeatureContext::getTokenForIdentifier($identifier), $body);
		}
	}

	/**
	 * @When user :user shares :path with room :room with OCS :statusCode
	 *
	 * @param string $user
	 * @param string $path
	 * @param string $room
	 * @param int $statusCode
	 */
	public function userSharesWithRoomWithOcs(string $user, string $path, string $room, int $statusCode) {
		$this->userSharesWithRoom($user, $path, $room);
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	/**
	 * @When user :user shares :path by link
	 *
	 * @param string $user
	 * @param string $path
	 * @param TableNode|null $body
	 */
	public function userSharesByLink(string $user, string $path, ?TableNode $body = null) {
		$this->userSharesWith($user, $path, 3 /*IShare::TYPE_LINK*/, '', $body);
	}

	/**
	 * @When user :user shares :path by link with OCS :statusCode
	 *
	 * @param string $user
	 * @param string $path
	 * @param int $statusCode
	 * @param TableNode|null $body
	 */
	public function userSharesByLinkWithOcs(string $user, string $path, int $statusCode, ?TableNode $body = null) {
		$this->userSharesByLink($user, $path, $body);
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	/**
	 * @When user :user updates last share with
	 *
	 * @param string $user
	 * @param TableNode $body
	 */
	public function userUpdatesLastShareWith(string $user, TableNode $body) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares/' . $this->getLastShareId();

		$this->sendingTo('PUT', $url, $body);
	}

	/**
	 * @When user :user deletes last share
	 *
	 * @param string $user
	 */
	public function userDeletesLastShare(string $user) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares/' . $this->getLastShareId();

		$this->sendingTo('DELETE', $url);
	}

	/**
	 * @When user :user deletes last share with OCS :statusCode
	 *
	 * @param string $user
	 * @param int $statusCode
	 */
	public function userDeletesLastShareWithOcs(string $user, int $statusCode) {
		$this->userDeletesLastShare($user);
		$this->theOCSStatusCodeShouldBe($statusCode);
	}

	/**
	 * @When user :user restores last share
	 *
	 * @param string $user
	 */
	public function userRestoresLastShareWithOcs(string $user) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/deletedshares/ocRoomShare:' . $this->getLastShareId();

		$this->sendingTo('POST', $url);
	}

	/**
	 * @When user :user gets last share
	 */
	public function userGetsLastShare(string $user) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares/' . $this->getLastShareId();

		$this->sendingTo('GET', $url);
	}

	/**
	 * @When user :user accepts last share
	 *
	 * @param string $user
	 */
	public function userAcceptsLastShare(string $user) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares/pending/' . $this->getLastShareId();

		$this->sendingTo('POST', $url);

		$this->theHTTPStatusCodeShouldBe(200);
		$this->theOCSStatusCodeShouldBe(100);
	}

	/**
	 * @When user :user gets all shares
	 *
	 * @param string $user
	 */
	public function userGetsAllShares(string $user) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares';

		$this->sendingTo('GET', $url);
	}

	/**
	 * @When user :user gets all shares and reshares
	 *
	 * @param string $user
	 */
	public function userGetsAllSharesAndReshares(string $user) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?reshares=true';

		$this->sendingTo('GET', $url);
	}

	/**
	 * @When user :user gets all shares for :path
	 *
	 * @param string $user
	 * @param string $path
	 */
	public function userGetsAllSharesFor(string $user, string $path) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?path=' . $path;

		$this->sendingTo('GET', $url);
	}

	/**
	 * @When user :user gets all shares and reshares for :path
	 *
	 * @param string $user
	 * @param string $path
	 */
	public function userGetsAllSharesAndResharesFor(string $user, string $path) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?reshares=true&path=' . $path;

		$this->sendingTo('GET', $url);
	}

	/**
	 * @When user :user gets all shares for :path and its subfiles
	 *
	 * @param string $user
	 * @param string $path
	 */
	public function userGetsAllSharesForAndItsSubfiles(string $user, string $path) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?subfiles=true&path=' . $path;

		$this->sendingTo('GET', $url);
	}

	/**
	 * @When user :user gets all received shares
	 *
	 * @param string $user
	 */
	public function userGetsAllReceivedShares(string $user) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?shared_with_me=true';

		$this->sendingTo('GET', $url);
	}

	/**
	 * @When user :user gets all received shares for :path
	 *
	 * @param string $user
	 * @param string $path
	 */
	public function userGetsAllReceivedSharesFor(string $user, string $path) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/shares?shared_with_me=true&path=' . $path;

		$this->sendingTo('GET', $url);
	}

	/**
	 * @When user :user gets deleted shares
	 *
	 * @param string $user
	 */
	public function userGetsDeletedShares(string $user) {
		$this->currentUser = $user;

		$url = '/apps/files_sharing/api/v1/deletedshares';

		$this->sendingTo('GET', $url);
	}

	/**
	 * @When /^user "([^"]*)" gets sharees for$/
	 *
	 * @param string $user
	 * @param TableNode $body
	 */
	public function userGetsShareesFor(string $user, TableNode $body) {
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

	/**
	 * @When user :user gets the DAV properties for :path
	 *
	 * @param string $user
	 * @param string $path
	 */
	public function userGetsTheDavPropertiesFor(string $user, string $path) {
		$this->currentUser = $user;

		$url = "/$user/$path";

		$this->sendingToDav('PROPFIND', $url);

		$this->theHTTPStatusCodeShouldBe(207);
	}

	/**
	 * @When user :user gets the share-type DAV property for :path
	 *
	 * @param string $user
	 * @param string $path
	 */
	public function userGetsTheShareTypeDavPropertyFor(string $user, string $path) {
		$this->currentUser = $user;

		$url = "/$user/$path";

		$headers = null;

		$body = '<d:propfind xmlns:d="DAV:" xmlns:oc="http://owncloud.org/ns">' .
				'	<d:prop>' .
				'		<oc:share-types/>' .
				'	</d:prop>' .
				'</d:propfind>';

		$this->sendingToDav('PROPFIND', $url, $headers, $body);

		$this->theHTTPStatusCodeShouldBe(207);
	}

	/**
	 * @When user :user gets recent files
	 *
	 * @param string $user
	 */
	public function userGetsRecentFiles(string $user) {
		// Recents endpoint is not an OCS endpoint, so a request token must be
		// provided.
		[$requestToken, $cookieJar] = $this->loggingInUsingWebAs($user);

		$url = '/index.php/apps/files/api/v1/recent';

		$this->sendingToWithRequestToken('GET', $url, $requestToken, $cookieJar);
	}

	/**
	 * @When transfering ownership from :user1 to :user2
	 *
	 * @param string $user1
	 * @param string $user2
	 */
	public function transferingOwnershipFromTo(string $user1, string $user2) {
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

	/**
	 * @Then the OCS status code should be :statusCode
	 *
	 * @param int $statusCode
	 */
	public function theOCSStatusCodeShouldBe(int $statusCode) {
		$meta = $this->getXmlResponse()->meta[0];

		\PHPUnit\Framework\Assert::assertEquals($statusCode, (int)$meta->statuscode, 'Response message: ' . (string)$meta->message);
	}

	/**
	 * @Then the HTTP status code should be :statusCode
	 *
	 * @param int $statusCode
	 */
	public function theHTTPStatusCodeShouldBe(int $statusCode) {
		\PHPUnit\Framework\Assert::assertEquals($statusCode, $this->response->getStatusCode());
	}

	/**
	 * @Then the list of returned shares has :count shares
	 */
	public function theListOfReturnedSharesHasShares(int $count) {
		$this->theHTTPStatusCodeShouldBe(200);
		$this->theOCSStatusCodeShouldBe(100);

		$returnedShares = $this->getXmlResponse()->data[0];

		\PHPUnit\Framework\Assert::assertEquals($count, count($returnedShares->element));
	}

	/**
	 * @Then share is returned with
	 *
	 * @param TableNode $body
	 */
	public function shareIsReturnedWith(TableNode $body) {
		$this->shareXIsReturnedWith(0, $body);
	}

	/**
	 * @Then share :number is returned with
	 *
	 * @param int $number
	 * @param TableNode $body
	 */
	public function shareXIsReturnedWith(int $number, TableNode $body) {
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

		if (!array_key_exists('uid_file_owner', $expectedFields) &&
				array_key_exists('uid_owner', $expectedFields)) {
			$expectedFields['uid_file_owner'] = $expectedFields['uid_owner'];
		}
		if (!array_key_exists('displayname_file_owner', $expectedFields) &&
				array_key_exists('displayname_owner', $expectedFields)) {
			$expectedFields['displayname_file_owner'] = $expectedFields['displayname_owner'];
		}

		if (array_key_exists('share_type', $expectedFields) &&
				$expectedFields['share_type'] == 10 /* IShare::TYPE_ROOM */ &&
				array_key_exists('share_with', $expectedFields)) {
			if ($expectedFields['share_with'] === 'private_conversation') {
				$expectedFields['share_with'] = 'REGEXP /^private_conversation_[0-9a-f]{6}$/';
				$expectedFields['share_with_link'] = '';
			} else {
				$expectedFields['share_with'] = FeatureContext::getTokenForIdentifier($expectedFields['share_with']);
			}
		}

		if (array_key_exists('share_with_link', $expectedFields) &&
			$expectedFields['share_with_link'] === 'URL') {
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
			$this->assertFieldIsInReturnedShare($field, $value, $share);
		}
	}

	/**
	 * @Then /^"([^"]*)" sharees returned (are|is empty)$/
	 *
	 * Each sharee is specified as "| room name | room test identifier |"; the
	 * name is checked against the returned "label" value, and the room test
	 * identifier is used to get the room token, which is checked against the
	 * returned "shareWith" value. The returned "shareType" value is expected to
	 * always be "IShare::TYPE_ROOM", so there is no need to specify it.
	 *
	 * @param string $shareeType
	 * @param string $isEmpty
	 * @param TableNode|null $shareesList
	 */
	public function shareesReturnedAreIsEmpty(string $shareeType, string $isEmpty, ?TableNode $shareesList = null) {
		if ($isEmpty !== 'is empty') {
			$sharees = [];
			foreach ($shareesList->getRows() as $row) {
				$expectedSharee = [$row[0]];
				$expectedSharee[] = 10; // IShare::TYPE_ROOM
				$expectedSharee[] = FeatureContext::getTokenForIdentifier($row[1]);
				$sharees[] = $expectedSharee;
			}
			$respondedArray = $this->getArrayOfShareesResponded($this->response, $shareeType);
			usort($sharees, function ($a, $b) {
				return $a[2] <=> $b[2]; // Sort by token
			});
			usort($respondedArray, function ($a, $b) {
				return $a[2] <=> $b[2]; // Sort by token
			});
			\PHPUnit\Framework\Assert::assertEquals($sharees, $respondedArray);
		} else {
			$respondedArray = $this->getArrayOfShareesResponded($this->response, $shareeType);
			\PHPUnit\Framework\Assert::assertEmpty($respondedArray);
		}
	}

	/**
	 * @Then the list of returned files for :user is
	 *
	 * @param string $user
	 * @param TableNode|null $table
	 */
	public function theListOfReturnedFilesForIs(string $user, ?TableNode $table = null) {
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

	/**
	 * @Then the response contains a share-types DAV property with
	 *
	 * @param TableNode|null $table
	 */
	public function theResponseContainsAShareTypesDavPropertyWith(?TableNode $table = null) {
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

	/**
	 * @Then the response contains a share-types file property for :path with
	 *
	 * @param string $path
	 * @param TableNode|null $table
	 */
	public function theResponseContainsAShareTypesFilesPropertyForWith(string $path, ?TableNode $table = null) {
		$response = json_decode($this->response->getBody());

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

	/**
	 * @param string $user
	 * @param string $path
	 * @param string $shareType
	 * @param string $shareWith
	 * @param TableNode|null $body
	 */
	private function userSharesWith(string $user, string $path, string $shareType, string $shareWith, ?TableNode $body = null) {
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

	/**
	 * @param string $verb
	 * @param string $url
	 * @param TableNode $body
	 */
	private function sendingTo(string $verb, string $url, ?TableNode $body = null) {
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
		} catch (GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	/**
	 * @param string $verb
	 * @param string $url
	 * @param array $headers
	 * @param string $body
	 */
	private function sendingToDav(string $verb, string $url, ?array $headers = null, ?string $body = null) {
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
		} catch (GuzzleHttp\Exception\ClientException $ex) {
			$this->response = $ex->getResponse();
		}
	}

	/**
	 * @param string $verb
	 * @param string $url
	 * @param string $requestToken
	 * @param CookieJar $cookieJar
	 */
	private function sendingToWithRequestToken(string $verb, string $url, string $requestToken, CookieJar $cookieJar) {
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
		} catch (GuzzleHttp\Exception\ClientException $e) {
			$this->response = $e->getResponse();
		}
	}

	/**
	 * @param ResponseInterface $response
	 * @return string
	 */
	private function extractRequestTokenFromResponse(ResponseInterface $response): string {
		return substr(preg_replace('/(.*)data-requesttoken="(.*)">(.*)/sm', '\2', $response->getBody()->getContents()), 0, 89);
	}

	/**
	 * @param string $user
	 */
	private function loggingInUsingWebAs(string $user) {
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

	/**
	 * @return string
	 */
	public function getLastShareToken(): string {
		return (string)$this->lastCreatedShareData->data[0]->token;
	}

	/**
	 * @return string
	 */
	private function getLastShareId(): string {
		return (string)$this->lastCreatedShareData->data[0]->id;
	}

	/**
	 * @return SimpleXMLElement
	 */
	private function getXmlResponse(): \SimpleXMLElement {
		return simplexml_load_string($this->response->getBody());
	}

	/**
	 * @param string $field
	 * @param string $contentExpected
	 * @param \SimpleXMLElement $returnedShare
	 */
	private function assertFieldIsInReturnedShare(string $field, string $contentExpected, array $returnedShare) {
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

	private function getArrayOfShareesResponded(ResponseInterface $response, string $shareeType) {
		$elements = simplexml_load_string($response->getBody())->data;
		$elements = json_decode(json_encode($elements), 1);
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
