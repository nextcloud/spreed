/**
 * Calculates the stopwatch string given the time (ms)
 *
 * @param time the time in ms
 * @param [condensed=false] the format of string to show
 */
function formattedTime(time: number, condensed: boolean = false): string {
	if (!time) {
		return condensed ? '--:--' : '-- : --'
	}

	const seconds = Math.floor((time / 1000) % 60)
	const minutes = Math.floor((time / (1000 * 60)) % 60)
	const hours = Math.floor((time / (1000 * 60 * 60)) % 24)

	return [
		hours,
		minutes.toString().padStart(2, '0'),
		seconds.toString().padStart(2, '0'),
	].filter(num => !!num).join(condensed ? ':' : ' : ')
}

/**
 * Calculates the future relative time string given the time (ms)
 *
 * @param time the time in ms
 */
function futureRelativeTime(time: number): string {
	const diff = time - Date.now()
	const hours = Math.floor(diff / (60 * 60 * 1000))
	const minutes = Math.floor((diff - hours * 60 * 60 * 1000) / (60 * 1000))
	if (hours >= 1) {
		if (minutes === 0) {
			// TRANSLATORS: hint for the time when the meeting starts (only hours)
			return n('spreed', 'In %n hour', 'In %n hours', hours)
		} else if (hours === 1) {
			// TRANSLATORS: hint for the time when the meeting starts (1 hour and minutes)
			return n('spreed', 'In 1 hour and %n minute', 'In 1 hour and %n minutes', minutes)
		} else {
			// TRANSLATORS: hint for the time when the meeting starts (hours and minutes)
			return n('spreed', 'In {hours} hours and %n minute', 'In {hours} hours and %n minutes', minutes, { hours })
		}
	} else {
		// TRANSLATORS: hint for the time when the meeting starts (only minutes)
		return n('spreed', 'In %n minute', 'In %n minutes', minutes)
	}
}

export {
	formattedTime,
	futureRelativeTime,
}
