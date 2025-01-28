/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

const STAT_VALUE_TYPE = {
	CUMULATIVE: 0,
	RELATIVE: 1,
}

/**
 * Helper to calculate the average of the last N instances of an RTCStatsReport
 * value.
 *
 * The average is a weighted average in which the latest elements have a higher
 * weight. Specifically, the first item has a weight of 1, the last item has a
 * weight of 3, and all the intermediate items have a weight that increases
 * linearly from 1 to 3. The weights can be set when the AverageStatValue is
 * created by specifying the weight of the last item.
 *
 * The number of items to keep track of must be set when the AverageStatValue is
 * created. Once N items have been added adding a new one will discard the
 * oldest value. "hasEnoughData()" can be used to check if enough items have
 * been added already and the average is reliable.
 *
 * An RTCStatsReport value can be cumulative since the creation of the
 * RTCPeerConnection (like a sent packet count), or it can be an independent
 * value at a certain point of time (like the round trip time). To be able to
 * calculate the average the AverageStatValue converts cumulative values to
 * relative ones. When the AverageStatValue is created it must be set whether
 * the values that will be added are cumulative or not.
 *
 * The conversion from cumulative to relative is done automatically. Note,
 * however, that the first value added to a cumulative AverageStatValue after
 * creating or resetting it will be treated as 0 in the average calculation,
 * as it will be the base from which the rest of relative values are calculated.
 * Therefore, if the values added to an AverageStatValue are relative,
 * "hasEnoughData()" will not return true until at least N items were added,
 * but if the values are cumulative, it will not return true until at least N+1
 * items were added.
 *
 * Besides the weighted average it is possible to "peek" the last value, either
 * the raw value that was added or the relative one after the conversion (which,
 * for non cumulative values, will be the raw value too).
 *
 * A string representation of the current relative values can be got by calling
 * "toString()".
 *
 * @param {number} count the number of instances to take into account.
 * @param {STAT_VALUE_TYPE} type whether the value is cumulative or relative.
 * @param {number} lastValueWeight the value to calculate the weights of all the
 *        items, from the first (weight 1) to the last one.
 */
function AverageStatValue(count, type = STAT_VALUE_TYPE.CUMULATIVE, lastValueWeight = 3) {
	this._count = count
	this._type = type
	this._extraWeightForEachElement = (lastValueWeight - 1) / (count - 1)

	this._rawValues = []
	this._relativeValues = []

	this._hasEnoughData = false
}
AverageStatValue.prototype = {

	reset() {
		this._rawValues = []
		this._relativeValues = []

		this._hasEnoughData = false
	},

	add(value) {
		if ((this._type === STAT_VALUE_TYPE.CUMULATIVE && this._rawValues.length === this._count)
			|| (this._type === STAT_VALUE_TYPE.RELATIVE && this._rawValues.length >= (this._count - 1))
		) {
			this._hasEnoughData = true
		}

		if (this._rawValues.length === this._count) {
			this._rawValues.shift()
			this._relativeValues.shift()
		}

		let relativeValue = value
		if (this._type === STAT_VALUE_TYPE.CUMULATIVE) {
			// The first added value will be meaningless as it will be 0 and
			// used as the base for the rest of values.
			const lastRawValue = this._rawValues.length ? this._rawValues.at(-1) : value
			relativeValue = value - lastRawValue
		}

		this._rawValues.push(value)
		this._relativeValues.push(relativeValue)
	},

	getLastRawValue() {
		if (this._rawValues.length < 1) {
			return NaN
		}

		return this._rawValues.at(-1)
	},

	getLastRelativeValue() {
		if (this._relativeValues.length < 1) {
			return NaN
		}

		return this._relativeValues.at(-1)
	},

	hasEnoughData() {
		return this._hasEnoughData
	},

	getWeightedAverage() {
		let weightedValues = 0
		let weightsSum = 0

		for (let i = 0; i < this._relativeValues.length; i++) {
			const weight = 1 + (i * this._extraWeightForEachElement)

			weightedValues += this._relativeValues[i] * weight
			weightsSum += weight
		}

		return weightedValues / weightsSum
	},

	toString() {
		if (!this._relativeValues.length) {
			return '[]'
		}

		let relativeValuesAsString = '[' + this._relativeValues[0]

		for (let i = 1; i < this._relativeValues.length; i++) {
			relativeValuesAsString += ', ' + this._relativeValues[i]
		}

		relativeValuesAsString += ']'

		return relativeValuesAsString
	},

}

export {
	STAT_VALUE_TYPE,
	AverageStatValue,
}
