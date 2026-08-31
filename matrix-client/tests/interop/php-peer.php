<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Minimal JSON-RPC-ish peer used by vodozemac_check.py: one command per
 * invocation, state kept in a pickle file. Not part of the library API.
 */

require __DIR__ . '/../../vendor/autoload.php';

use Nextcloud\Matrix\Crypto\Base64;
use Nextcloud\Matrix\Crypto\Megolm\InboundSession;
use Nextcloud\Matrix\Crypto\Megolm\OutboundSession;
use Nextcloud\Matrix\Crypto\Olm\Account;
use Nextcloud\Matrix\Crypto\Olm\Session;
use Nextcloud\Matrix\Crypto\Verification\Sas;

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
		$session = Session::createInbound($account, Base64::decode($request['body']), Base64::decode($request['their_curve25519']));
		$sessions[$request['name']] = $session->pickle();
		$out = ['session_id' => $session->getId(), 'plaintext' => $session->decrypt(0, $request['body'])];
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
	case 'sas_bytes':
		$out = ['bytes' => Base64::encode(Sas::unpickle($sessions['sas'])->rawBytes($request['info'], 6))];
		break;
	case 'sas_emoji':
		$bytes = Sas::unpickle($sessions['sas'])->rawBytes($request['info'], 6);
		$emoji = \Nextcloud\Matrix\Crypto\Verification\Emoji::fromSasBytes($bytes);
		$names = array_column(\Nextcloud\Matrix\Crypto\Verification\Emoji::TABLE, 1);
		$out = ['indices' => array_map(static fn (array $e) => array_search($e['name'], $names, true), $emoji), 'decimals' => \Nextcloud\Matrix\Crypto\Verification\Emoji::decimalFromSasBytes($bytes)];
		break;
	case 'sas_mac':
		$out = ['mac' => Sas::unpickle($sessions['sas'])->rawMac($request['value'], $request['info'])];
		break;
	case 'backup_key':
		$pair = \Nextcloud\Matrix\Crypto\Keys::curve25519KeyPair();
		$sessions['backup'] = Base64::encode($pair['secret']);
		$out = ['public_key' => Base64::encode($pair['public'])];
		break;
	case 'backup_decrypt':
		$out = ['plaintext' => json_encode(\Nextcloud\Matrix\Crypto\Backup::decryptSessionData(Base64::decode($sessions['backup']), $request['session_data']))];
		break;
	case 'backup_encrypt':
		$out = \Nextcloud\Matrix\Crypto\Backup::encryptSessionData(Base64::decode($request['public_key']), $request['data']);
		break;
	default:
		fwrite(STDERR, 'unknown cmd');
		exit(1);
}
file_put_contents($stateFile, json_encode(['account' => $account->pickle(), 'sessions' => $sessions]));
echo json_encode($out);
