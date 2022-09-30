/* eslint-disable no-unused-vars */
/// <reference types="@nextcloud/typings" />

declare global {
	const OC: Nextcloud.v24.OC &
	{
		dialogs: {
			confirm: (arg1: any, arg2: any, arg3: any) => void,
		}
	}
}

declare module '*.vue' {
  import type { DefineComponent } from 'vue'
  const component: DefineComponent<{}, {}, any>
  export default component
}

export {}
