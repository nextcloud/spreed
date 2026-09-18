#!/usr/bin/env python3
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
"""Interoperability check of the PHP Olm/Megolm implementation against vodozemac.

    pip install vodozemac
    python3 tests/interop/vodozemac_check.py

Exit code 0 = every direction interoperates.
"""
import json
import os
import subprocess
import sys
import tempfile

import vodozemac as vz

HERE = os.path.dirname(os.path.abspath(__file__))
STATE = tempfile.mktemp(suffix='.json')


import base64


def b64(raw):
    return base64.b64encode(raw).decode().rstrip('=')


def unb64(text):
    return base64.b64decode(text + '=' * (-len(text) % 4))


def parts(msg):
    t, b = msg.to_parts()
    return t, (b64(b) if isinstance(b, bytes) else b)


def any_message(t, body):
    return vz.AnyOlmMessage.from_parts(t, unb64(body))


def php(**cmd):
    proc = subprocess.run(['php', os.path.join(HERE, 'php-peer.php'), STATE], input=json.dumps(cmd).encode(), capture_output=True)
    if proc.returncode != 0:
        raise SystemExit(f"php peer failed for {cmd['cmd']}: {proc.stderr.decode()}\n{proc.stdout.decode()}")
    return json.loads(proc.stdout.decode())


def check(label, condition):
    print(('ok   ' if condition else 'FAIL ') + label)
    if not condition:
        global failures
        failures += 1


failures = 0
php_keys = php(cmd='keys')

# --- signatures -------------------------------------------------------------
sig = php(cmd='sign', message='hello')['signature']
try:
    vz.Ed25519PublicKey.from_base64(php_keys['ed25519']).verify_signature(b'hello', vz.Ed25519Signature.from_base64(sig))
    check('PHP Ed25519 signature verified by vodozemac', True)
except Exception as e:  # noqa: BLE001
    check(f'PHP Ed25519 signature verified by vodozemac ({e})', False)

dk = php_keys['device_keys']
canonical = json.dumps({k: v for k, v in dk.items() if k not in ('signatures', 'unsigned')}, separators=(',', ':'), sort_keys=True, ensure_ascii=False).encode()
try:
    vz.Ed25519PublicKey.from_base64(php_keys['ed25519']).verify_signature(canonical, vz.Ed25519Signature.from_base64(dk['signatures']['@php:hs']['ed25519:PHPDEV']))
    check('PHP device keys signature (canonical JSON) verified by vodozemac', True)
except Exception as e:  # noqa: BLE001
    check(f'PHP device keys signature verified by vodozemac ({e})', False)

# --- Olm: PHP is Alice (outbound), vodozemac is Bob ------------------------------
bob = vz.Account()
bob.generate_one_time_keys(1)
bob_otk_id, bob_otk = next(iter(bob.one_time_keys.items()))
bob.mark_keys_as_published()
php(cmd='olm_outbound', name='a2b', their_curve25519=bob.curve25519_key.to_base64(), their_one_time_key=bob_otk.to_base64())
m1 = php(cmd='olm_encrypt', name='a2b', plaintext='hello from php')
check('PHP first Olm message is a pre-key message', m1['type'] == 0)
prekey = vz.PreKeyMessage.from_base64(m1['body'])
bob_session, plaintext = bob.create_inbound_session(vz.Curve25519PublicKey.from_base64(php_keys['curve25519']), prekey)
check('vodozemac decrypts PHP pre-key message', plaintext == b'hello from php')
php_sid = php(cmd='olm_session_id', name='a2b')['session_id']
check(f'session ids agree (PHP outbound {php_sid} vs vodozemac inbound {bob_session.session_id} / prekey {prekey.session_id})', bob_session.session_id == php_sid)
m2 = php(cmd='olm_encrypt', name='a2b', plaintext='second pre-key')
check('vodozemac decrypts second PHP pre-key message', bob_session.decrypt(vz.PreKeyMessage.from_base64(m2['body']).to_any()) == b'second pre-key')
reply = bob_session.encrypt(b'hello from vodozemac')
rtype, rbody = parts(reply)
check('vodozemac reply is a normal message', rtype == 1)
check('PHP decrypts vodozemac reply (ratchet advance)', php(cmd='olm_decrypt', name='a2b', type=rtype, body=rbody)['plaintext'] == 'hello from vodozemac')
m3 = php(cmd='olm_encrypt', name='a2b', plaintext='third, normal')
check('PHP message after reply is normal', m3['type'] == 1)
check('vodozemac decrypts PHP normal message', bob_session.decrypt(any_message(m3['type'], m3['body'])) == b'third, normal')
r2 = bob_session.encrypt(b'two')
r3 = bob_session.encrypt(b'three')
t3, b3 = parts(r3)
t2, b2 = parts(r2)
check('PHP decrypts out-of-order vodozemac messages (3 then 2)',
      php(cmd='olm_decrypt', name='a2b', type=t3, body=b3)['plaintext'] == 'three' and php(cmd='olm_decrypt', name='a2b', type=t2, body=b2)['plaintext'] == 'two')

# --- Olm: vodozemac is Alice (outbound), PHP is Bob ------------------------------
alice = vz.Account()
alice_session = alice.create_outbound_session(vz.Curve25519PublicKey.from_base64(php_keys['curve25519']), vz.Curve25519PublicKey.from_base64(php_keys['one_time_key']))
msg = alice_session.encrypt(b'hello php bob')
mtype, mbody = parts(msg)
check('vodozemac first message is pre-key', mtype == 0)
inbound = php(cmd='olm_inbound', name='b2a', body=mbody, their_curve25519=alice.curve25519_key.to_base64())
check('PHP decrypts vodozemac pre-key message', inbound['plaintext'] == 'hello php bob')
check('session ids agree (vodozemac outbound vs PHP inbound)', inbound['session_id'] == alice_session.session_id)
reply = php(cmd='olm_encrypt', name='b2a', plaintext='reply from php bob')
check('PHP reply is normal message', reply['type'] == 1)
check('vodozemac decrypts PHP reply', alice_session.decrypt(any_message(reply['type'], reply['body'])) == b'reply from php bob')
again = alice_session.encrypt(b'again from alice')
at, ab = parts(again)
check('PHP decrypts vodozemac follow-up (new ratchet key)', php(cmd='olm_decrypt', name='b2a', type=at, body=ab)['plaintext'] == 'again from alice')

# --- Megolm: vodozemac outbound → PHP inbound -------------------------------------
group = vz.GroupSession()
php_in = php(cmd='megolm_inbound', name='vz2php', session_key=group.session_key.to_base64())
check('Megolm session id agrees', php_in['session_id'] == group.session_id)
ciphertexts = [group.encrypt(f'message {i}'.encode()).to_base64() for i in range(0, 300)]
check('PHP decrypts vodozemac Megolm message 0', php(cmd='megolm_decrypt', name='vz2php', ciphertext=ciphertexts[0]) == {'plaintext': 'message 0', 'index': 0})
check('PHP decrypts vodozemac Megolm message 299 (ratchet part rollover)', php(cmd='megolm_decrypt', name='vz2php', ciphertext=ciphertexts[299]) == {'plaintext': 'message 299', 'index': 299})
check('PHP decrypts vodozemac Megolm message 255', php(cmd='megolm_decrypt', name='vz2php', ciphertext=ciphertexts[255])['plaintext'] == 'message 255')
exported = php(cmd='megolm_export', name='vz2php')['exported']
vz_reimport = vz.InboundGroupSession.import_session(vz.ExportedSessionKey(exported))
check('vodozemac imports PHP-exported session and decrypts', vz_reimport.decrypt(vz.MegolmMessage.from_base64(ciphertexts[7])).plaintext == b'message 7')

# --- Megolm: PHP outbound → vodozemac inbound -------------------------------------
php_out = php(cmd='megolm_outbound', name='php2vz')
vz_in = vz.InboundGroupSession(vz.SessionKey(php_out['session_key']))
check('vodozemac accepts PHP session key (signature ok) and ids agree', vz_in.session_id == php_out['session_id'])
for i in range(0, 260):
    c = php(cmd='megolm_encrypt', name='php2vz', plaintext=f'php {i}')['ciphertext'] if i in (0, 1, 259) else None
    if c is None:
        # advance the PHP side without shipping every message through python
        php(cmd='megolm_encrypt', name='php2vz', plaintext='skip')
        continue
    d = vz_in.decrypt(vz.MegolmMessage.from_base64(c))
    check(f'vodozemac decrypts PHP Megolm message {i}', d.plaintext == f'php {i}'.encode() and d.message_index == i)
vz_exported = vz_in.export_at(0).to_base64()
php_from_export = php(cmd='megolm_inbound', name='php_reimport', exported=vz_exported)
check('PHP imports vodozemac-exported session', php_from_export['session_id'] == php_out['session_id'] and php_from_export['first_known_index'] == 0)

# --- SAS verification maths -----------------------------------------------------------
vz_sas = vz.Sas()
php_pub = php(cmd='sas_public')['public_key']
established = vz_sas.diffie_hellman(vz.Curve25519PublicKey.from_base64(php_pub))
php(cmd='sas_establish', their_public_key=vz_sas.public_key.to_base64())
info = 'MATRIX_KEY_VERIFICATION_SAS|@a:hs|DEV1|' + vz_sas.public_key.to_base64() + '|@a:hs|DEV2|' + php_pub + '|txn'
vz_bytes = established.bytes(info)
php_sas = php(cmd='sas_emoji', info=info)
check('SAS emoji indices agree with vodozemac', list(vz_bytes.emoji_indices) == php_sas['indices'])
check('SAS decimals agree with vodozemac', list(vz_bytes.decimals) == php_sas['decimals'])
mac_info = 'MATRIX_KEY_VERIFICATION_MAC@a:hsDEV2@a:hsDEV1txned25519:DEV2'
php_mac = php(cmd='sas_mac', value='somekey', info=mac_info)['mac']
vz_mac = established.calculate_mac('somekey', mac_info)
check('SAS MAC agrees with vodozemac', vz_mac.rstrip('=') == php_mac)
try:
    established.verify_mac('somekey', mac_info, php_mac)
    check('vodozemac verifies PHP SAS MAC', True)
except Exception as e:  # noqa: BLE001
    check(f'vodozemac verifies PHP SAS MAC ({e})', False)

# --- Key backup (PkEncryption / PkDecryption) ------------------------------------------
php_backup_pub = php(cmd='backup_key')['public_key']
pk_enc = vz.PkEncryption.from_key(vz.Curve25519PublicKey.from_base64(php_backup_pub))
payload = json.dumps({'algorithm': 'm.megolm.v1.aes-sha2', 'session_key': 'AQ', 'sender_key': 'k'})
msg = pk_enc.encrypt(payload.encode())
session_data = {'ephemeral': b64(msg.ephemeral_key) if isinstance(msg.ephemeral_key, bytes) else msg.ephemeral_key.to_base64(), 'ciphertext': b64(msg.ciphertext) if isinstance(msg.ciphertext, bytes) else msg.ciphertext, 'mac': b64(msg.mac) if isinstance(msg.mac, bytes) else msg.mac}
check('PHP decrypts vodozemac backup session data', json.loads(php(cmd='backup_decrypt', session_data=session_data)['plaintext'])['session_key'] == 'AQ')
pk_dec = vz.PkDecryption()
enc = php(cmd='backup_encrypt', public_key=pk_dec.public_key.to_base64(), data={'session_key': 'BQ'})
decrypted = pk_dec.decrypt(vz.Message.from_base64(enc['ciphertext'], enc['mac'], enc['ephemeral']))
check('vodozemac decrypts PHP backup session data', decrypted is not None and json.loads(bytes(decrypted).decode())['session_key'] == 'BQ')

os.remove(STATE)
print(f'\n{failures} failure(s)')
sys.exit(1 if failures else 0)
