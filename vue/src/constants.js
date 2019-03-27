/**
 * @copyright Copyright (c) 2019 Joas Schilling <coding@schilljs.com>
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

const Participant = {
	/* Must stay in sync with values in "lib/Participant.php". */
	TYPE_OWNER: 1,
	TYPE_MODERATOR: 2,
	TYPE_USER: 3,
	TYPE_GUEST: 4,
	TYPE_USER_SELFJOINED: 5,
	TYPE_GUEST_MODERATOR: 6,

	/* Must stay in sync with values in "lib/Participant.php". */
	NOTIFY_DEFAULT: 0,
	NOTIFY_ALWAYS: 1,
	NOTIFY_MENTION: 2,
	NOTIFY_NEVER: 3
}

const Conversation = {
	/* Must stay in sync with values in "lib/Room.php". */
	FLAG_DISCONNECTED: 0,
	FLAG_IN_CALL: 1,
	FLAG_WITH_AUDIO: 2,
	FLAG_WITH_VIDEO: 4,

	/* Must stay in sync with values in "lib/Room.php". */
	TYPE_ONE_TO_ONE: 1,
	TYPE_GROUP: 2,
	TYPE_PUBLIC: 3,
	TYPE_CHANGELOG: 4
}

export { Participant, Conversation }
