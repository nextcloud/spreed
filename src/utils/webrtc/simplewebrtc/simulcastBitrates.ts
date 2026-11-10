/**
 * SPDX-FileCopyrightText: 2025 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/**
 * Default maximum bitrate for a single published stream
 * when the signaling server does not advertise a limit. (1 Mbps)
 */
export const DEFAULT_MAX_STREAM_BITS = 1_048_576
/**
 * Expected maximum bitrate for a hi-quality stream (1920x1080, 30fps)
 * Over-provisioning for a minimum "high" resolution (1280x720, 30fps)
 */
export const HIGH_MAX_STREAM_BITS = 2_764_800
/**
 * Expected maximum bitrate for a mid-quality stream (640x360, 30fps)
 */
export const MEDIUM_MAX_STREAM_BITS = 300_000
/**
 * Expected maximum bitrate for a low-quality stream (320x180, 30fps)
 */
export const LOW_MAX_STREAM_BITS = 100_000

/**
 * Compute per-layer simulcast maxBitrate ceilings from the publisher's total
 * bandwidth limit. Low and medium layers use fixed, resolution-appropriate
 * caps; only the high layer scales with the available budget.
 *
 * Firefox only (applied via RTCRtpSender.setParameters());
 * Chromium/Safari use SDP munging and let REMB distribute the bitrate.
 *
 * @param totalBps - Total bandwidth in bps.
 */
export function getSimulcastMaxBitrates(totalBps: number = DEFAULT_MAX_STREAM_BITS) {
	if (typeof totalBps !== 'number' || totalBps <= 0) {
		totalBps = DEFAULT_MAX_STREAM_BITS
	}
	return {
		high: Math.min(Math.round(0.9 * totalBps), HIGH_MAX_STREAM_BITS),
		medium: MEDIUM_MAX_STREAM_BITS,
		low: LOW_MAX_STREAM_BITS,
	}
}
