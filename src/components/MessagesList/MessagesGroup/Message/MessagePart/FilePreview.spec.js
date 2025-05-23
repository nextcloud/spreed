import { generateRemoteUrl, imagePath } from '@nextcloud/router'
import { getUploader } from '@nextcloud/upload'
/**
 * SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
import { createLocalVue, shallowMount } from '@vue/test-utils'
import { cloneDeep } from 'lodash'
import { createPinia, setActivePinia } from 'pinia'
import Vuex from 'vuex'
import NcButton from '@nextcloud/vue/components/NcButton'
import PlayCircleOutline from 'vue-material-design-icons/PlayCircleOutline.vue'
import FilePreview from './FilePreview.vue'
import storeConfig from '../../../../../store/storeConfig.js'

describe('FilePreview.vue', () => {
	let store
	let localVue
	let testStoreConfig
	let propsData
	let getUserIdMock
	let oldPixelRatio

	beforeEach(() => {
		localVue = createLocalVue()
		localVue.use(Vuex)
		setActivePinia(createPinia())

		oldPixelRatio = window.devicePixelRatio

		testStoreConfig = cloneDeep(storeConfig)
		getUserIdMock = jest.fn().mockReturnValue('current-user-id')
		testStoreConfig.modules.actorStore.getters.getUserId = () => getUserIdMock
		store = new Vuex.Store(testStoreConfig)

		propsData = {
			token: 'TOKEN',
			file: {
				id: '123',
				name: 'test.jpg',
				path: 'path/to/test.jpg',
				size: '128',
				etag: '1872ade88f3013edeb33decd74a4f947',
				permissions: '15',
				mimetype: 'image/jpeg',
				'preview-available': 'yes',
			},
		}
	})

	afterEach(() => {
		jest.clearAllMocks()
		window.devicePixelRatio = oldPixelRatio
	})

	/**
	 * @param {string} url Relative URL to parse (starting with / )
	 */
	function parseRelativeUrl(url) {
		return new URL('https://localhost' + url)
	}

	describe('file preview rendering', () => {
		test('renders file preview', async () => {
			const wrapper = shallowMount(FilePreview, {
				localVue,
				store,
				propsData,
			})

			await wrapper.find('img').trigger('load')

			expect(wrapper.element.tagName).toBe('A')
			const imageUrl = parseRelativeUrl(wrapper.find('img').attributes('src'))
			expect(imageUrl.pathname).toBe('/nc-webroot/core/preview')
			expect(imageUrl.searchParams.get('fileId')).toBe('123')
			expect(imageUrl.searchParams.get('x')).toBe('-1')
			expect(imageUrl.searchParams.get('y')).toBe('384')
			expect(imageUrl.searchParams.get('a')).toBe('1')

			expect(wrapper.find('.loading').exists()).toBe(false)
		})

		test('renders file preview for guests', async () => {
			propsData.file.link = 'https://localhost/nc-webroot/s/xtokenx'
			getUserIdMock.mockClear().mockReturnValue(null)

			const wrapper = shallowMount(FilePreview, {
				localVue,
				store,
				propsData,
			})

			await wrapper.find('img').trigger('load')

			expect(wrapper.element.tagName).toBe('A')
			const imageUrl = parseRelativeUrl(wrapper.find('img').attributes('src'))
			expect(imageUrl.pathname).toBe('/nc-webroot/apps/files_sharing/publicpreview/xtokenx')
			expect(imageUrl.searchParams.has('fileId')).toBe(false)
			expect(imageUrl.searchParams.get('x')).toBe('-1')
			expect(imageUrl.searchParams.get('y')).toBe('384')
			expect(imageUrl.searchParams.get('a')).toBe('1')

			expect(wrapper.find('.loading').exists()).toBe(false)
		})

		test('calculates preview size based on window pixel ratio', async () => {
			window.devicePixelRatio = 1.5

			const wrapper = shallowMount(FilePreview, {
				localVue,
				store,
				propsData,
			})

			await wrapper.find('img').trigger('load')

			expect(wrapper.element.tagName).toBe('A')
			const imageUrl = parseRelativeUrl(wrapper.find('img').attributes('src'))
			expect(imageUrl.searchParams.get('y')).toBe('576')
		})

		test('renders small previews when requested', async () => {
			propsData.smallPreview = true

			const wrapper = shallowMount(FilePreview, {
				localVue,
				store,
				propsData,
			})

			await wrapper.find('img').trigger('load')

			expect(wrapper.element.tagName).toBe('A')
			const imageUrl = parseRelativeUrl(wrapper.find('img').attributes('src'))
			expect(imageUrl.searchParams.get('y')).toBe('32')
		})

		describe('uploading', () => {
			const path = '/Talk/path-to-file.png'
			let getUploadFileMock

			beforeEach(() => {
				getUploadFileMock = jest.fn(() => ({
					sharePath: path,
					status: 'uploading',
				}))
				testStoreConfig.modules.fileUploadStore.getters.getUploadFile = () => getUploadFileMock
				store = new Vuex.Store(testStoreConfig)
			})

			test('renders progress bar while uploading', async () => {
				getUploader.mockImplementation(() => ({
					queue: [{
						_source: path,
						_uploaded: 85,
						_size: 100,
					}],
				}))

				propsData.file.id = 'temp-123'
				propsData.file.index = 'index-1'
				propsData.file.uploadId = 1000
				propsData.file.localUrl = 'blob:XYZ'

				const wrapper = shallowMount(FilePreview, {
					localVue,
					store,
					propsData,
				})

				await wrapper.find('img').trigger('load')

				expect(wrapper.element.tagName).toBe('DIV')
				expect(wrapper.find('img').attributes('src')).toBe('blob:XYZ')

				const progressEl = wrapper.findComponent({ name: 'NcProgressBar' })
				expect(progressEl.exists()).toBe(true)
				expect(progressEl.props('value')).toBe(85)

				expect(getUploadFileMock).toHaveBeenCalledWith(1000, 'index-1')
			})
		})

		test('renders spinner while loading', () => {
			const wrapper = shallowMount(FilePreview, {
				localVue,
				store,
				propsData,
			})

			expect(wrapper.element.tagName).toBe('A')
			const spinner = wrapper.findComponent({ name: 'NcLoadingIcon' })
			expect(spinner.exists()).toBe(true)
		})

		test('renders default mime icon on load error', async () => {
			OC.MimeType.getIconUrl.mockReturnValueOnce(imagePath('core', 'image/jpeg'))
			const wrapper = shallowMount(FilePreview, {
				localVue,
				store,
				propsData,
			})

			await wrapper.find('img').trigger('error')

			expect(wrapper.element.tagName).toBe('A')
			const imageUrl = wrapper.find('img').attributes('src')
			expect(imageUrl).toBe(imagePath('core', 'image/jpeg'))
		})

		test('renders generic mime type icon for unknown mime types', async () => {
			propsData.file['preview-available'] = 'no'
			OC.MimeType.getIconUrl.mockReturnValueOnce(imagePath('core', 'image/jpeg'))

			const wrapper = shallowMount(FilePreview, {
				localVue,
				store,
				propsData,
			})

			await wrapper.find('img').trigger('load')

			expect(wrapper.element.tagName).toBe('A')
			const imageUrl = wrapper.find('img').attributes('src')
			expect(imageUrl).toBe(imagePath('core', 'image/jpeg'))

			expect(OC.MimeType.getIconUrl).toHaveBeenCalledWith('image/jpeg')
		})

		describe('gif rendering', () => {
			beforeEach(() => {
				propsData.file.mimetype = 'image/gif'
				propsData.file.name = 'test %20.gif'
				propsData.file.path = 'path/to/test %20.gif'
			})
			test('directly renders small GIF files', async () => {
				propsData.file.size = '128'

				const wrapper = shallowMount(FilePreview, {
					localVue,
					store,
					propsData,
				})

				await wrapper.find('img').trigger('load')

				expect(wrapper.element.tagName).toBe('A')
				expect(wrapper.find('img').attributes('src'))
					.toBe(generateRemoteUrl('dav/files/current-user-id') + '/path/to/test%20%2520.gif')
			})

			test('directly renders small GIF files (absolute path)', async () => {
				propsData.file.size = '128'
				propsData.file.path = '/path/to/test %20.gif'

				const wrapper = shallowMount(FilePreview, {
					localVue,
					store,
					propsData,
				})

				await wrapper.find('img').trigger('load')

				expect(wrapper.element.tagName).toBe('A')
				expect(wrapper.find('img').attributes('src'))
					.toBe(generateRemoteUrl('dav/files/current-user-id') + '/path/to/test%20%2520.gif')
			})

			test('directly renders small GIF files for guests', async () => {
				propsData.file.size = '128'
				propsData.file.link = 'https://localhost/nc-webroot/s/xtokenx'
				getUserIdMock.mockClear().mockReturnValue(null)

				const wrapper = shallowMount(FilePreview, {
					localVue,
					store,
					propsData,
				})

				await wrapper.find('img').trigger('load')

				expect(wrapper.element.tagName).toBe('A')
				expect(wrapper.find('img').attributes('src'))
					.toBe(propsData.file.link + '/download/test%20%2520.gif')
			})

			test('renders static preview for big GIF files', async () => {
				// 4 MB, bigger than max from capability (3 MB)
				propsData.file.size = '4194304'

				const wrapper = shallowMount(FilePreview, {
					localVue,
					store,
					propsData,
				})

				await wrapper.find('img').trigger('load')

				expect(wrapper.element.tagName).toBe('A')
				const imageUrl = parseRelativeUrl(wrapper.find('img').attributes('src'))
				expect(imageUrl.pathname).toBe('/nc-webroot/core/preview')
				expect(imageUrl.searchParams.get('fileId')).toBe('123')
				expect(imageUrl.searchParams.get('x')).toBe('-1')
				expect(imageUrl.searchParams.get('y')).toBe('384')
			})
		})

		describe('triggering viewer', () => {
			let oldViewer
			let oldFiles

			beforeEach(() => {
				oldViewer = OCA.Viewer
				oldFiles = OCA.Files

				OCA.Files = {
					Sidebar: {
						state: {
						},
					},
				}

				store = new Vuex.Store(testStoreConfig)
			})
			afterEach(() => {
				if (oldViewer) {
					OCA.Viewer = oldViewer
				} else {
					delete OCA.Viewer
				}

				if (oldFiles) {
					OCA.Files = oldFiles
				} else {
					delete OCA.Files
				}
			})

			test('opens viewer when clicking if viewer available', async () => {
				OCA.Viewer = {
					open: jest.fn(),
					availableHandlers: [{
						mimes: ['image/png', 'image/jpeg'],
					}],
					mimetypes: ['image/png', 'image/jpeg'],
				}

				const wrapper = shallowMount(FilePreview, {
					localVue,
					store,
					propsData,
				})

				await wrapper.find('img').trigger('load')

				await wrapper.find('a').trigger('click')

				expect(OCA.Viewer.open).toHaveBeenCalledWith(expect.objectContaining({
					list: [{
						basename: 'test.jpg',
						etag: '1872ade88f3013edeb33decd74a4f947',
						fileid: 123,
						filename: '/path/to/test.jpg',
						hasPreview: true,
						mime: 'image/jpeg',
						permissions: 'CKGWD',
					}],
					path: '/path/to/test.jpg',
				}))

				expect(OCA.Files.Sidebar.state.file).toBe('/path/to/test.jpg')
			})

			test('does not open viewer when clicking if no mime handler available', async () => {
				OCA.Viewer = {
					open: jest.fn(),
					availableHandlers: [{
						mimes: ['image/png'],
					}],
				}

				const wrapper = shallowMount(FilePreview, {
					localVue,
					store,
					propsData,
				})

				await wrapper.find('img').trigger('load')

				await wrapper.find('a').trigger('click')

				expect(OCA.Viewer.open).not.toHaveBeenCalled()
			})

			test('does not open viewer when clicking if viewer is not available', async () => {
				delete OCA.Viewer
				const wrapper = shallowMount(FilePreview, {
					localVue,
					store,
					propsData,
				})

				await wrapper.find('img').trigger('load')

				// no error
				await wrapper.find('a').trigger('click')
			})

			describe('play icon for video', () => {
				beforeEach(() => {
					propsData.file.mimetype = 'video/mp4'
					propsData.file.name = 'test.mp4'
					propsData.file.path = 'path/to/test.mp4'

					// viewer needs to be available
					OCA.Viewer = {
						open: jest.fn(),
						availableHandlers: [{
							mimes: ['video/mp4', 'image/jpeg', 'image/png', 'image/gif'],
						}],
						mimetypes: ['video/mp4', 'image/jpeg', 'image/png', 'image/gif'],
					}
				})

				/**
				 * @param {boolean} visible Whether or not the play button is visible
				 */
				async function testPlayButtonVisible(visible) {
					const wrapper = shallowMount(FilePreview, {
						localVue,
						store,
						propsData,
					})

					await wrapper.find('img').trigger('load')

					const buttonEl = wrapper.findComponent(PlayCircleOutline)
					expect(buttonEl.exists()).toBe(visible)
				}

				test('renders play icon for video previews', async () => {
					await testPlayButtonVisible(true)
				})

				test('does not render play icon for direct renders', async () => {
					// gif is directly rendered
					propsData.file.mimetype = 'image/gif'
					propsData.file.name = 'test.gif'
					propsData.file.path = 'path/to/test.gif'

					await testPlayButtonVisible(false)
				})

				test('render play icon gif previews with big size', async () => {
					// gif is directly rendered
					propsData.file.mimetype = 'image/gif'
					propsData.file.name = 'test.gif'
					propsData.file.path = 'path/to/test.gif'
					propsData.file.size = '10000000' // bigger than default max

					await testPlayButtonVisible(true)
				})

				test('does not render play icon for small previews', async () => {
					propsData.smallPreview = true
					await testPlayButtonVisible(false)
				})

				test('does not render play icon for failed videos', async () => {
					const wrapper = shallowMount(FilePreview, {
						localVue,
						store,
						propsData,
					})

					await wrapper.find('img').trigger('error')

					const buttonEl = wrapper.findComponent(PlayCircleOutline)
					expect(buttonEl.exists()).toBe(false)
				})

				test('does not render play icon if viewer not available', async () => {
					delete OCA.Viewer
					await testPlayButtonVisible(false)
				})

				test('does not render play icon for non-videos', async () => {
					// viewer supported, but not a video
					propsData.file.mimetype = 'image/png'
					propsData.file.name = 'test.png'
					propsData.file.path = 'path/to/test.png'
					await testPlayButtonVisible(false)
				})
			})
		})
	})

	describe('in upload editor', () => {
		beforeEach(() => {
			propsData.isUploadEditor = true
		})
		test('emits event when clicking remove button when inside upload editor', async () => {
			const wrapper = shallowMount(FilePreview, {
				localVue,
				store,
				propsData,
			})

			await wrapper.find('img').trigger('load')

			expect(wrapper.element.tagName).toBe('DIV')
			await wrapper.findComponent(NcButton).trigger('click')
			expect(wrapper.emitted()['remove-file']).toStrictEqual([['123']])
		})
	})
})
