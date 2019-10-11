/**
 *
 * @copyright Copyright (c) 2019, Daniel Calviño Sánchez (danxuliu@gmail.com)
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

import Vue from 'vue'
import { DatetimePicker } from 'nextcloud-vue'

(function(OCA) {

	Vue.prototype.t = t
	Vue.prototype.n = n

	OCA.Talk = Object.assign({}, OCA.Talk)
	OCA.Talk.Views = Object.assign({}, OCA.Talk.Views)

	OCA.Talk.Views.LobbyTimerPicker = Vue.extend({
		components: {
			DatetimePicker
		},
		props: {
			value: {
				type: Date,
				default: function() {
					return null
				}
			},
			disabled: {
				type: Boolean,
				default: false
			}
		},
		render: function(createElement) {
			return createElement('DatetimePicker', {
				attrs: {
					type: 'datetime',
					format: 'YYYY-MM-DD HH:mm',
					firstDayOfWeek: window.firstDay + 1,	// Provided by server
					lang: {
						days: window.dayNamesShort,			// Provided by server
						months: window.monthNamesShort,		// Provided by server
						placeholder: {
							date: t('spreed', 'Start time (optional)')
						}
					},
					disabled: this.disabled
				},
				props: {
					value: this.value
				},
				on: {
					// In a real Vue component the event should be propagated
					// instead of changing the property itself, but as this Vue
					// instance acts as a boundary between Vue and Marionette
					// the property is modified to simplify things.
					'change': value => { this.value = value },
					'focus': () => { this.$emit('focus') },
					'blur': () => { this.$emit('blur') }
				}
			})
		}
	})

})(window.OCA)
