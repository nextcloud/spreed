<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Minimal JSON-RPC-ish peer used by vodozemac_check.py: one command per
 * invocation, state kept in a pickle file.
 */

require __DIR__ . '/../../../vendor/autoload.php';

use OCA\Talk\Matrix\Client\Crypto\Base64;
use OCA\Talk\Matrix\Client\Crypto\Megolm\InboundSession;
use OCA\Talk\Matrix\Client\Crypto\Megolm\OutboundSession;
use OCA\Talk\Matrix\Client\Crypto\Olm\Account;
use OCA\Talk\Matrix\Client\Crypto\Olm\Session;
use OCA\Talk\Matrix\Client\Crypto\Verification\Emoji;
use OCA\Talk\Matrix\Client\Crypto\Verification\Sas;

$stateFile = $argv[1];
$request = json_decode((string)file_get_contents('php://stdin'), true);
$state = is_file($stateFile) ? json_decode((string)file_get_contents($stateFile), true) : [];
$account = isset($state['account']) ? Account::unpickle($state['account']) : Account::create();
$sessions = $state['sessions'] ?? [];
$out = [];

switch ($request['cmd']) {
	case 'keys':
		$account->generateOneTimeKeys(2);
		$otk = $account->getUnpublishedOneTimeKeys();
		$out = ['curve25519' => $account->getIdentityKeyBase64(), 'ed25519' => $account->getSigningKeyBase64(), 'one_time_key' => array_values($otk)[0], 'device_keys' => $account->deviceKeys('@php:hs', 'PHPDEV')];
		break;
	case 'sign':
		$out = ['signature' => Base64::encode($account->sign($request['message']))];
		break;
	case 'olm_outbound':
		$session = Session::createOutbound($account, Base64::decode($request['their_curve25519']), Base64::decode($request['their_one_time_key']));
		$sessions[$request['name']] = $session->pickle();
		$out = ['session_id' => $session->getId()];
		break;
	case 'olm_inbound':
		['session' => $session, 'plaintext' => $plaintext] = Session::fromPreKeyMessage($account, $request['body'], Base64::decode($request['their_curve25519']));
		$sessions[$request['name']] = $session->pickle();
		$out = ['session_id' => $session->getId(), 'plaintext' => $plaintext];
		break;
	case 'olm_session_id':
		$out = ['session_id' => Session::unpickle($sessions[$request['name']])->getId()];
		break;
	case 'olm_encrypt':
		$session = Session::unpickle($sessions[$request['name']]);
		$out = $session->encrypt($request['plaintext']);
		$sessions[$request['name']] = $session->pickle();
		break;
	case 'olm_decrypt':
		$session = Session::unpickle($sessions[$request['name']]);
		$out = ['plaintext' => $session->decrypt((int)$request['type'], $request['body'])];
		$sessions[$request['name']] = $session->pickle();
		break;
	case 'megolm_outbound':
		$session = OutboundSession::create(0);
		$sessions[$request['name']] = $session->pickle();
		$out = ['session_id' => $session->getId(), 'session_key' => $session->sessionKey()];
		break;
	case 'megolm_encrypt':
		$session = OutboundSession::unpickle($sessions[$request['name']]);
		$out = ['ciphertext' => $session->encrypt($request['plaintext'])];
		$sessions[$request['name']] = $session->pickle();
		break;
	case 'megolm_inbound':
		$session = isset($request['exported']) ? InboundSession::fromExportedKey($request['exported']) : InboundSession::fromSessionKey($request['session_key']);
		$sessions[$request['name']] = $session->pickle();
		$out = ['session_id' => $session->getId(), 'first_known_index' => $session->getFirstKnownIndex()];
		break;
	case 'megolm_decrypt':
		$session = InboundSession::unpickle($sessions[$request['name']]);
		$out = $session->decrypt($request['ciphertext']);
		break;
	case 'megolm_export':
		$session = InboundSession::unpickle($sessions[$request['name']]);
		$out = ['exported' => $session->export()];
		break;
	case 'sas_public':
		$sas = new Sas();
		$sessions['sas'] = $sas->pickle();
		$out = ['public_key' => $sas->getPublicKey()];
		break;
	case 'sas_establish':
		$sas = Sas::unpickle($sessions['sas']);
		$sas->establish($request['their_public_key']);
		$sessions['sas'] = $sas->pickle();
		$out = ['ok' => true];
		break;
	case 'sas_emoji':
		$names = array_column(Emoji::TABLE, 1);
		$emoji = Emoji::fromSasBytes(Sas::unpickle($sessions['sas'])->rawBytes($request['info']));
		$out = ['indices' => array_map(static fn (array $e) => array_search($e['name'], $names, true), $emoji)];
		break;
	case 'sas_mac':
		$out = ['mac' => Sas::unpickle($sessions['sas'])->rawMac($request['value'], $request['info'])];
		break;
	default:
		fwrite(STDERR, 'unknown cmd');
		exit(1);
}
file_put_contents($stateFile, json_encode(['account' => $account->pickle(), 'sessions' => $sessions]));
echo json_encode($out);
