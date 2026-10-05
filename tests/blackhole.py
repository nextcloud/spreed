#
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
import socket, time
s = socket.socket(); s.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
s.bind(('127.0.0.1', 9999)); s.listen(0)
fill = [socket.create_connection(('127.0.0.1', 9999)) for _ in range(2)]
print('ready', flush=True); time.sleep(3600)
