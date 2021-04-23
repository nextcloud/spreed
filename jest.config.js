/*
 * @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
 *
 * @author Marco Ambrosini <marcoambrosini@pm.me>
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

module.exports = {
	preset: '@vue/cli-plugin-unit-jest/presets/no-babel',
	// Allow tests in the src and in tests/unit folders
	testMatch: ['<rootDir>/src/**/*.(spec|test).(ts|js)'],
	resetMocks: false,
	setupFiles: ['jest-localstorage-mock'],
	setupFilesAfterEnv: ['<rootDir>/src/test-setup.js'],
	transform: {
		// process `*.js` files with `babel-jest`
		'.*\\.(js)$': 'babel-jest',
	},
}
