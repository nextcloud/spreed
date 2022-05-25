declare module '@nextcloud/capabilities' {
	export interface Capabilities {
		spreed: {
			config: {
				attachments: {
					allowed: boolean;
				}
			}
		};
	}

	export function getCapabilities(): Capabilities;
}